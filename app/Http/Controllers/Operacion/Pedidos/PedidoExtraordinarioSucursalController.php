<?php

namespace App\Http\Controllers\Operacion\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\Pedido;
use App\Models\Operacion\Pedidos\TipoPedido;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PedidoExtraordinarioSucursalController extends Controller
{
    /**
     * Mostrar formulario de pedido extraordinario por sucursal
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $operadorNombre = session('operador_nombre');

        // ✅ OBTENER LA SUCURSAL CORRECTAMENTE
        $sucursal = ClienteSucursal::where('IdClienteSucursal', $sucursalId)
            ->first();

        $sucursalTexto = $sucursal 
            ? $sucursal->NumeroSucursal . ' - ' . $sucursal->Nombre 
            : 'Sin sucursal asignada';

        // ✅ Obtener pedidos del día de hoy
        $fechaActual = Carbon::now('America/La_Paz')->format('Y-m-d');
        
        $pedidos = Pedido::porContexto()
            ->porOperador()
            ->where('DistribuidoSiNo', 0)
            ->whereDate('FechaDelPedido', $fechaActual)
            ->with(['producto', 'tipoPedido'])
            ->orderBy('IdPedidos')
            ->get();

        // Productos para el select
        $productos = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'codigo' => $item->Codigo,
                    'descripcion' => $item->Descripcion,
                    'texto' => $item->Codigo . ' - ' . $item->Descripcion,
                ];
            });

        $horaActual = Carbon::now('America/La_Paz')->format('H:i');
        $horaLimiteExtra = '07:00';

        return Inertia::render('Operacion/Pedidos/PedidoExtraordinarioSucursal/Index', [
            'pedidos' => $pedidos,
            'productos' => $productos,
            'sucursalId' => $sucursalId,
            'sucursalTexto' => $sucursalTexto,  // ✅ Enviar el texto de la sucursal
            'fechaHoraServidor' => Carbon::now('America/La_Paz')->format('Y-m-d H:i:s'),
            'fechaActual' => $fechaActual,
            'horaActual' => $horaActual,
            'horaLimiteExtra' => $horaLimiteExtra,
            'operadorId' => $operadorId,
            'operadorNombre' => $operadorNombre,
        ]);
    }

    /**
     * Guardar o actualizar pedido extraordinario por sucursal
     */
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:operacion_ventas_pedidos,IdPedidos',
            'FechaDelPedido' => 'required|date',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Unidades' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $fechaPedido = $request->FechaDelPedido;
        $fechaActual = Carbon::now('America/La_Paz')->format('Y-m-d');
        $horaActual = Carbon::now('America/La_Paz')->format('H:i');

        // Validación: fecha actual
        if ($fechaPedido != $fechaActual) {
            return response()->json([
                'success' => false,
                'message' => 'El pedido extraordinario solo se puede realizar para el día de hoy'
            ], 422);
        }

        // Validación: hora límite 07:00 AM
        if ($horaActual > '07:00') {
            return response()->json([
                'success' => false,
                'message' => 'La hora Autorizada para realizar los pedidos extraordinarios es 07:00...!'
            ], 422);
        }

        // Validación: Unidades > 0
        if ($request->Unidades <= 0) {
            return response()->json([
                'success' => false,
                'message' => '¡Las unidades deben ser mayor a cero...!'
            ], 422);
        }

        // Obtener tipo "Ordinario"
        $tipoOrdinario = TipoPedido::porContexto()
            ->where('Detalle', 'Ordinario')
            ->first();
        
        if (!$tipoOrdinario) {
            $tipoOrdinario = TipoPedido::find(1);
        }
        
        $idTipoOrdinario = $tipoOrdinario ? $tipoOrdinario->IdTipoPedido : 1;

        DB::beginTransaction();

        try {
            if ($request->id && $request->id > 0) {
                $pedido = Pedido::porContexto()
                    ->porOperador()
                    ->findOrFail($request->id);

                if ($pedido->FechaDelPedido != $fechaActual) {
                    throw new \Exception('No se puede editar un pedido que no sea del día de hoy');
                }

                $pedido->update([
                    'IdProducto' => $request->IdProducto,
                    'Unidades' => $request->Unidades,
                    'UnidadesAutoriza' => $request->Unidades,
                ]);

                $mensaje = 'Pedido actualizado correctamente';

            } else {
                Pedido::create([
                    'IdTipoPedido' => $idTipoOrdinario,
                    'ProduceDistribuye' => 0,
                    'FechaRealiza' => Carbon::now('America/La_Paz'),
                    'FechaDelPedido' => $fechaPedido,
                    'IdProducto' => $request->IdProducto,
                    'Unidades' => $request->Unidades,
                    'AutorizadoNoAutorizado' => 0,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'idOperador' => $operadorId,
                    'IdOperadorPedidoExtraordinario' => 0,
                    'UnidadesAutoriza' => $request->Unidades,
                    'IdOperadorAutoriza' => 0,
                    'IdDistribucion' => 0,
                    'UnidadesDistribuidas' => 0,
                    'DistribuidoSiNo' => 0,
                    'IdOperadorRecibe' => 0,
                    'UnidadesRecibidas' => 0,
                ]);

                $mensaje = 'Pedido guardado correctamente';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $mensaje
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en pedido extraordinario sucursal: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener datos de un pedido para editar
     */
    public function edit($id)
    {
        try {
            $pedido = Pedido::porContexto()
                ->porOperador()
                ->with(['producto'])
                ->findOrFail($id);

            $fechaActual = Carbon::now('America/La_Paz')->format('Y-m-d');
            
            if ($pedido->FechaDelPedido != $fechaActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar un pedido que no sea del día de hoy'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $pedido->IdPedidos,
                    'FechaDelPedido' => $pedido->FechaDelPedido,
                    'IdProducto' => $pedido->IdProducto,
                    'Unidades' => $pedido->Unidades,
                    'producto_texto' => $pedido->producto ? 
                        $pedido->producto->Codigo . ' - ' . $pedido->producto->Descripcion : '',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar pedido
     */
    public function destroy($id)
    {
        try {
            $pedido = Pedido::porContexto()
                ->porOperador()
                ->findOrFail($id);

            $fechaActual = Carbon::now('America/La_Paz')->format('Y-m-d');
            
            if ($pedido->FechaDelPedido != $fechaActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un pedido que no sea del día de hoy'
                ], 422);
            }

            $pedido->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pedido eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Validar hora (07:00 AM)
     */
    public function apiValidarHora()
    {
        $horaActual = Carbon::now('America/La_Paz')->format('H:i');
        $horaLimite = '07:00';
        
        $valido = $horaActual <= $horaLimite;
        $mensaje = $valido ? null : 'La hora Autorizada para realizar los pedidos extraordinarios es 07:00...!';

        return response()->json([
            'success' => true,
            'valido' => $valido,
            'hora_actual' => $horaActual,
            'hora_limite' => $horaLimite,
            'mensaje' => $mensaje,
        ]);
    }
}