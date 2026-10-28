<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Reclamo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ReclamoDetalle;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\HistorialPedido;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalidadController extends Controller
{
    public function index()
    {
        // 1. Traemos todos los reclamos con sus relaciones
        $reclamos = Reclamo::with([
                'pedido' => fn($q) => $q->select('IdPedidoCliente', 'IdCliente', 'IdOperador', 'IdSucursal', 'NumeroPedido'),
                'pedido.cliente',
                'pedido.sucursal',
                'detalles' => fn($q) => $q->select('IdReclamoDetalle', 'IdReclamo', 'IdProducto', 'CantidadReclamada', 'CantidadAprobada', 'Motivo'),
                'detalles.producto',
            ])
            ->orderBy('FechaCreacion', 'desc')
            ->get();

        // 2. Buscamos los nombres de los operadores
        $operadorIds = $reclamos->pluck('pedido.IdOperador')->filter()->unique()->values();
        $nombresOperadores = $operadorIds->isEmpty() ? collect() : DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->whereIn('todos_operador.IdOperador', $operadorIds)
            ->pluck('todos_identificador.Nombre', 'todos_operador.IdOperador');

        // 3. Agrupamos PRIMERO por Operador
        $operadores = $reclamos->groupBy(fn($r) => $r->pedido->IdOperador)->map(function ($grupoOperador, $idOperador) use ($nombresOperadores) {
            
            $nombreOperador = $nombresOperadores[$idOperador] ?? 'Operador Desconocido';

            // 4. Dentro del Operador, agrupamos por Cliente
            $clientes = $grupoOperador->groupBy(fn($r) => $r->pedido->IdCliente)->map(function ($grupoCliente, $idCliente) use ($nombreOperador) {
                $primero = $grupoCliente->first();
                
                // Inyectamos el nombre del operador a cada reclamo
                $grupoCliente->each(function ($reclamo) use ($nombreOperador) {
                    $reclamo->nombre_operador = $nombreOperador;
                });

                return [
                    'IdCliente'         => $idCliente,
                    'NombreCliente'     => $primero->pedido->cliente->Nombre ?? 'Cliente sin nombre',
                    'NombreSucursal'    => $primero->pedido->sucursal->Nombre ?? '—',
                    'Reclamos'          => $grupoCliente->values(),
                ];
            })->values();

            return [
                'IdOperador'     => $idOperador,
                'NombreOperador' => $nombreOperador,
                'Clientes'       => $clientes,
            ];
        })->values();

        return Inertia::render('Operacion/ClientesMayoristas/Calidad/Index', [
            'operadores' => $operadores,
        ]);
    }

    // ============================================================
    // Los métodos de acción se quedan EXACTAMENTE igual que antes
    // ============================================================
    public function recibirEnFabrica(Request $request, $id)
    {
        $conexion = DB::connection('mysql_gestion_comercial_alimentos');
        try {
            $conexion->beginTransaction();
            $reclamo = Reclamo::where('IdReclamo', $id)->lockForUpdate()->first();
            if (!$reclamo) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'Reclamo no encontrado.'], 404); }
            if ($reclamo->esFaltante()) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'Reclamo por faltante. No requiere recepción.'], 422); }
            if ($reclamo->Estado !== 'Pendiente de Revisión') { $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'El ticket no está pendiente de recepción.'], 422); }
            $reclamo->update(['Estado' => 'En Revisión', 'FechaActualizacion' => now()]);
            $conexion->commit();
            return response()->json(['success' => true, 'message' => 'Productos recibidos en fábrica.']);
        } catch (\Exception $e) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => $e->getMessage()], 500); }
    }

    public function aprobar(Request $request, $id)
    {
        $request->validate([
            'observacion' => 'nullable|string|max:500',
            'productos_aprobados' => 'required|array|min:1',
            'productos_aprobados.*.IdReclamoDetalle' => 'required|exists:pedidos_clientes_reclamos_detalle,IdReclamoDetalle',
            'productos_aprobados.*.CantidadAprobada' => 'required|integer|min:0',
        ]);
        $conexion = DB::connection('mysql_gestion_comercial_alimentos');
        try {
            $conexion->beginTransaction();
            $reclamo = Reclamo::where('IdReclamo', $id)->lockForUpdate()->first();
            if (!$reclamo) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'Reclamo no encontrado.'], 404); }
            $estadosValidos = $reclamo->esFaltante() ? ['Pendiente de Revisión', 'En Revisión'] : ['En Revisión'];
            if (!in_array($reclamo->Estado, $estadosValidos, true)) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'Estado inválido.'], 422); }
            foreach ($request->productos_aprobados as $prod) {
                $detalle = ReclamoDetalle::where('IdReclamoDetalle', $prod['IdReclamoDetalle'])->where('IdReclamo', $reclamo->IdReclamo)->lockForUpdate()->first();
                if (!$detalle || $prod['CantidadAprobada'] < 0 || $prod['CantidadAprobada'] > $detalle->CantidadReclamada) {
                    $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'Cantidad inválida.'], 422);
                }
                $detalle->CantidadAprobada = $prod['CantidadAprobada'];
                $detalle->save();
            }
            $reclamo->update(['Estado' => 'Aprobado por Calidad', 'FechaActualizacion' => now(), 'RespuestaCalidad' => $request->observacion ?? 'Aprobado.']);
            $pedido = PedidoCliente::find($reclamo->IdPedidoCliente);
            if ($pedido) {
                HistorialPedido::create([
                    'IdPedidoCliente' => $pedido->IdPedidoCliente, 'EstadoAnterior' => $pedido->EstadoPedido, 'EstadoNuevo' => $pedido->EstadoPedido,
                    'IdOperador' => session('operador_id'), 'Observacion' => "[CALIDAD-APROBADO] Reclamo #{$reclamo->IdReclamo}. " . ($request->observacion ?? ''), 'FechaCambio' => now(),
                ]);
            }
            $conexion->commit();
            return response()->json(['success' => true, 'message' => 'Aprobado.']);
        } catch (\Exception $e) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => $e->getMessage()], 500); }
    }

    public function rechazar(Request $request, $id)
    {
        $request->validate(['motivo_rechazo' => 'required|string|max:500']);
        $conexion = DB::connection('mysql_gestion_comercial_alimentos');
        try {
            $conexion->beginTransaction();
            $reclamo = Reclamo::where('IdReclamo', $id)->lockForUpdate()->first();
            if (!$reclamo) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'No encontrado.'], 404); }
            $estadosValidos = $reclamo->esFaltante() ? ['Pendiente de Revisión', 'En Revisión'] : ['En Revisión'];
            if (!in_array($reclamo->Estado, $estadosValidos, true)) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => 'Estado inválido.'], 422); }
            $reclamo->update(['Estado' => 'Rechazado', 'FechaActualizacion' => now(), 'RespuestaCalidad' => $request->motivo_rechazo]);
            $pedido = PedidoCliente::where('IdPedidoCliente', $reclamo->IdPedidoCliente)->lockForUpdate()->first();
            if ($pedido) {
                $quedanAbiertos = Reclamo::where('IdPedidoCliente', $pedido->IdPedidoCliente)->where('IdReclamo', '!=', $reclamo->IdReclamo)->whereIn('Estado', ['Pendiente de Revisión', 'En Revisión', 'Aprobado por Calidad'])->exists();
                if (!$quedanAbiertos) {
                    $pedido->update(['EstadoPedido' => 'Recibido', 'IdOperadorActualiza' => session('operador_id'), 'FechaActualiza' => now()]);
                }
            }
            $conexion->commit();
            return response()->json(['success' => true, 'message' => 'Rechazado.']);
        } catch (\Exception $e) { $conexion->rollBack(); return response()->json(['success' => false, 'message' => $e->getMessage()], 500); }
    }
}