<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Reclamo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente\BilleteraOperador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TesoreriaController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');

        // Traemos tanto los Aprobados como los Reembolsados
        $reclamos = Reclamo::where('IdCliente', $clienteId)
            ->whereIn('Estado', ['Aprobado por Calidad', 'Reembolsado'])
            ->with([
                'pedido.cliente', 
                'pedido.sucursal',
                'detalles.producto'
            ])
            ->orderBy('FechaActualizacion', 'desc')
            ->get();

        // Enriquecer los reclamos con el nombre del operador y el monto
        $reclamosProcesados = $reclamos->map(function ($reclamo) {
            $montoTotal = 0;
            
            // Calcular monto (Sirve tanto para Aprobados como para Reembolsados)
            foreach ($reclamo->detalles as $detalle) {
                $precioProducto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('pedidos_clientes_detalle')
                    ->where('IdPedidoCliente', $reclamo->IdPedidoCliente)
                    ->where('IdProducto', $detalle->IdProducto)
                    ->value('Precio');

                if ($detalle->CantidadAprobada > 0 && $precioProducto) {
                    $montoTotal += ($detalle->CantidadAprobada * $precioProducto);
                }
            }
            $reclamo->monto_a_devolver = round($montoTotal, 2);

            // Obtener nombre del operador (JOIN directo para evitar fallos)
            $operadorData = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $reclamo->pedido->IdOperador)
                ->first(['todos_identificador.Nombre as nombre']);
            
            $reclamo->nombre_operador = $operadorData->nombre ?? 'Sin operador';

            return $reclamo;
        });

        return Inertia::render('Operacion/ClientesMayoristas/Tesoreria/Index', [
            'reclamos' => $reclamosProcesados,
        ]);
    }

    public function devolver(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        $conexion = DB::connection('mysql_gestion_comercial_alimentos');

        try {
            $conexion->beginTransaction();

            $reclamo = Reclamo::where('IdCliente', $clienteId)
                ->where('IdReclamo', $id)
                ->lockForUpdate()
                ->first();

            if (!$reclamo) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Reclamo no encontrado.'], 404);
            }

            if ($reclamo->Estado !== 'Aprobado por Calidad') {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Este reclamo no está listo para devolución.'], 422);
            }

            $montoTotal = 0;
            foreach ($reclamo->detalles as $detalle) {
                $precioProducto = $conexion->table('pedidos_clientes_detalle')
                    ->where('IdPedidoCliente', $reclamo->IdPedidoCliente)
                    ->where('IdProducto', $detalle->IdProducto)
                    ->value('Precio');
                
                if ($detalle->CantidadAprobada > 0 && $precioProducto) {
                    $montoTotal += ($detalle->CantidadAprobada * $precioProducto);
                }
            }

            if ($montoTotal <= 0) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'El monto a devolver es 0 o no hay cantidades aprobadas.'], 422);
            }

            $pedido = PedidoCliente::find($reclamo->IdPedidoCliente);
            if (!$pedido) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado.'], 404);
            }

            $billetera = BilleteraOperador::where('IdOperador', $pedido->IdOperador)
                ->where('ActivoInactivo', 0)
                ->lockForUpdate()
                ->first();

            if (!$billetera) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'El operador no tiene una billetera activa para recibir la devolución.'], 422);
            }

            $billetera->devolverPorReclamo(
                $montoTotal,
                $reclamo->IdPedidoCliente,
                "Devolución Ticket #{$reclamo->IdReclamo}",
                $operadorId
            );

            $reclamo->update([
                'Estado' => 'Reembolsado',
                'FechaActualizacion' => now(),
                'RespuestaCalidad' => $reclamo->RespuestaCalidad . " | Reembolsado Bs. {$montoTotal} por Tesorería.",
            ]);

            $conexion->commit();

            return response()->json([
                'success' => true,
                'message' => "Se devolvieron Bs. {$montoTotal} a la billetera del cliente.",
                'monto_devuelto' => $montoTotal,
            ]);

        } catch (\Exception $e) {
            $conexion->rollBack();
            Log::error('Error al devolver reclamo: ' . $e->getMessage(), ['IdReclamo' => $id]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}