<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Reclamo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ReclamoDetalle;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalidadController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');

        // 🔥 CAMBIO: Traer todos los estados, incluyendo el recién agregado 'Reembolsado'
        $reclamos = Reclamo::where('IdCliente', $clienteId)
            ->whereIn('Estado', ['Pendiente de Revisión', 'En Revisión', 'Aprobado por Calidad', 'Rechazado', 'Reembolsado'])
            ->with([
                'pedido.cliente',
                'pedido.sucursal',
                'detalles' => function($query) {
                    $query->select('IdReclamoDetalle', 'IdReclamo', 'IdProducto', 'CantidadReclamada', 'CantidadAprobada', 'Motivo');
                },
                'detalles.producto'
            ])
            ->orderBy('FechaCreacion', 'desc')
            ->get();

        // Nombre del operador
        $reclamos->each(function ($reclamo) {
            $operadorData = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $reclamo->pedido->IdOperador)
                ->first(['todos_identificador.Nombre as nombre']);
            
            $reclamo->nombre_operador = $operadorData->nombre ?? 'Sin operador';
        });

        return Inertia::render('Operacion/ClientesMayoristas/Calidad/Index', [
            'reclamos' => $reclamos,
        ]);
    }

    // 1. El producto físico llegó a fábrica
    public function recibirEnFabrica(Request $request, $id)
    {
        $reclamo = Reclamo::findOrFail($id);

        if ($reclamo->Estado !== 'Pendiente de Revisión') {
            return response()->json(['success' => false, 'message' => 'El ticket no está pendiente de recepción.'], 422);
        }

        $reclamo->update([
            'Estado' => 'En Revisión',
            'FechaActualizacion' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Productos recibidos en fábrica. Ahora puedes revisarlos.',
        ]);
    }

    // 2. Aprobar (Sí, es falla de fábrica)
    public function aprobar(Request $request, $id)
    {
        $request->validate([
            'observacion' => 'nullable|string|max:500',
            'productos_aprobados' => 'required|array',
            'productos_aprobados.*.IdReclamoDetalle' => 'required|exists:pedidos_clientes_reclamos_detalle,IdReclamoDetalle',
            'productos_aprobados.*.CantidadAprobada' => 'required|integer|min:0',
        ]);

        $conexion = DB::connection('mysql_gestion_comercial_alimentos');

        try {
            $conexion->beginTransaction();

            $reclamo = Reclamo::where('IdReclamo', $id)->lockForUpdate()->first();

            if (!$reclamo) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Reclamo no encontrado.'], 404);
            }

            if ($reclamo->Estado !== 'En Revisión') {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Primero debes recibir los productos en fábrica.'], 422);
            }

            // Guardar las cantidades aprobadas
            foreach ($request->productos_aprobados as $prod) {
                $detalle = ReclamoDetalle::where('IdReclamoDetalle', $prod['IdReclamoDetalle'])
                    ->where('IdReclamo', $reclamo->IdReclamo)
                    ->first();

                if (!$detalle) {
                    $conexion->rollBack();
                    return response()->json(['success' => false, 'message' => 'Producto no pertenece a este reclamo.'], 422);
                }

                if ($prod['CantidadAprobada'] > $detalle->CantidadReclamada) {
                    $conexion->rollBack();
                    return response()->json([
                        'success' => false, 
                        'message' => "Error: Intentaste aprobar {$prod['CantidadAprobada']} unidades, pero solo se reclamaron {$detalle->CantidadReclamada}."
                    ], 422);
                }

                if ($prod['CantidadAprobada'] < 0) {
                    $conexion->rollBack();
                    return response()->json(['success' => false, 'message' => 'La cantidad aprobada no puede ser negativa.'], 422);
                }

                $detalle->CantidadAprobada = $prod['CantidadAprobada'];
                $detalle->save();
            }

            // 🔥 AHORA GUARDAMOS LA RESPUESTA EN SU PROPIO CAMPO
            $reclamo->update([
                'Estado' => 'Aprobado por Calidad',
                'FechaActualizacion' => now(),
                'RespuestaCalidad' => $request->observacion ?? 'Se aprobó devolución parcial/total.',
            ]);

            $conexion->commit();

            return response()->json([
                'success' => true,
                'message' => 'Reclamo aprobado. Enviado a Tesorería para reembolso.',
            ]);

        } catch (\Exception $e) {
            $conexion->rollBack();
            Log::error('Error al aprobar reclamo: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // 3. Rechazar (No es falla de fábrica)
    public function rechazar(Request $request, $id)
    {
        $request->validate([
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        $conexion = DB::connection('mysql_gestion_comercial_alimentos');

        try {
            $conexion->beginTransaction();

            $reclamo = Reclamo::where('IdReclamo', $id)->lockForUpdate()->first();

            if (!$reclamo) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Reclamo no encontrado.'], 404);
            }

            if ($reclamo->Estado !== 'En Revisión') {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Primero debes recibir los productos en fábrica.'], 422);
            }

            // 🔥 GUARDAMOS EL MOTIVO DE RECHAZO EN SU PROPIO CAMPO
            $reclamo->update([
                'Estado' => 'Rechazado',
                'FechaActualizacion' => now(),
                'RespuestaCalidad' => $request->motivo_rechazo,
            ]);

            $pedido = PedidoCliente::find($reclamo->IdPedidoCliente);
            if ($pedido) {
                $pedido->update([
                    'EstadoPedido' => 'Recibido',
                    'IdOperadorActualiza' => session('operador_id'),
                    'FechaActualiza' => now(),
                ]);
            }

            $conexion->commit();

            return response()->json([
                'success' => true,
                'message' => 'Reclamo rechazado. El pedido vuelve a estado Recibido.',
            ]);

        } catch (\Exception $e) {
            $conexion->rollBack();
            Log::error('Error al rechazar reclamo: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
        // ============================================================
    // API: Calcular Monto a Devolver (Para Tesorería)
    // ============================================================
    public function calcularMontoDevolucion($id)
    {
        try {
            $reclamo = Reclamo::with(['detalles', 'pedido'])->findOrFail($id);
            
            $montoTotal = 0;
            
            foreach ($reclamo->detalles as $detalle) {
                // Buscamos el precio que se usó en el pedido para ese producto
                $precioProducto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('pedidos_clientes_detalle')
                    ->where('IdPedidoCliente', $reclamo->IdPedidoCliente)
                    ->where('IdProducto', $detalle->IdProducto)
                    ->value('Precio');

                // Si no se aprueba nada, no suma
                if ($detalle->CantidadAprobada > 0 && $precioProducto) {
                    $montoTotal += ($detalle->CantidadAprobada * $precioProducto);
                }
            }

            return response()->json([
                'success' => true,
                'monto_total' => round($montoTotal, 2),
                'monto_formateado' => number_format($montoTotal, 2, ',', '.'),
                'moneda' => 'BOB',
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}