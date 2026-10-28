<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Reclamo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ReclamoDetalle;
use App\Models\Operacion\Pedidos\ClientesMayoristas\HistorialPedido;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReclamoController extends Controller
{
    public function store(Request $request, $id)
    {
        $request->validate([
            'tipo_reclamo' => 'required|in:DEFECTUOSO,FALTANTE',
            'descripcion'  => 'required|string|max:1000',
            'productos'    => 'required|array|min:1',
            'productos.*.IdProducto'         => 'required|exists:inventario_productodetalle,IdProducto',
            'productos.*.CantidadReclamada'  => 'required|integer|min:1',
            'productos.*.Motivo'             => 'nullable|string|max:255',
        ]);

        $clienteId  = session('cliente_id');
        $operadorId = session('operador_id');
        $conexion   = DB::connection('mysql_gestion_comercial_alimentos');

        try {
            $conexion->beginTransaction();

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->lockForUpdate()
                ->first();

            if (!$pedido) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado.'], 404);
            }

            if (!in_array($pedido->EstadoPedido, ['Recibido', 'Con Reclamo'])) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Solo se pueden reclamar pedidos que ya fueron recibidos.'], 422);
            }

            if (Reclamo::where('IdPedidoCliente', $id)->exists()) {
                $conexion->rollBack();
                return response()->json(['success' => false, 'message' => 'Este pedido ya tiene un reclamo registrado.'], 422);
            }

            // Validación explícita: el producto debe pertenecer al pedido y no reclamar más de lo pedido
            foreach ($request->productos as $prod) {
                $cantidadTotalPedida = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                    ->where('IdProducto', $prod['IdProducto'])
                    ->sum('Cantidad');

                if ($cantidadTotalPedida <= 0) {
                    $conexion->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Uno de los productos seleccionados no pertenece a este pedido.'
                    ], 422);
                }

                if ($prod['CantidadReclamada'] > $cantidadTotalPedida) {
                    $conexion->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Error: Intentaste reclamar {$prod['CantidadReclamada']} unidades, pero el pedido solo tiene {$cantidadTotalPedida} de ese producto."
                    ], 422);
                }
            }

            $reclamo = Reclamo::create([
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'IdCliente'       => $clienteId,
                'IdOperador'      => $operadorId,
                'TipoReclamo'     => $request->tipo_reclamo,
                'Estado'          => 'Pendiente de Revisión',
                'Descripcion'     => $request->descripcion,
                'FechaCreacion'   => now(),
            ]);

            foreach ($request->productos as $prod) {
                ReclamoDetalle::create([
                    'IdReclamo'         => $reclamo->IdReclamo,
                    'IdProducto'        => $prod['IdProducto'],
                    'CantidadReclamada' => $prod['CantidadReclamada'],
                    'Motivo'            => $prod['Motivo'] ?? null,
                ]);
            }

            $estadoAnterior = $pedido->EstadoPedido;

            $pedido->update([
                'EstadoPedido'        => 'Con Reclamo',
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza'      => now(),
            ]);

            HistorialPedido::create([
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'EstadoAnterior'  => $estadoAnterior,
                'EstadoNuevo'     => 'Con Reclamo',
                'IdOperador'      => $operadorId,
                'Observacion'     => "[{$request->tipo_reclamo}] Reclamo creado: {$request->descripcion}",
                'FechaCambio'     => now(),
            ]);

            $conexion->commit();

            return response()->json([
                'success'    => true,
                'message'    => 'Reclamo enviado al área de calidad.',
                'id_reclamo' => $reclamo->IdReclamo,
            ]);

        } catch (\Exception $e) {
            $conexion->rollBack();
            Log::error('Error creando reclamo: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}