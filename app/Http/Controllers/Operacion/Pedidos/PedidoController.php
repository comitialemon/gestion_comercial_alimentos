<?php

namespace App\Http\Controllers\Operacion\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\Pedido;
use App\Models\Operacion\Pedidos\TipoPedido;
use App\Models\Operacion\Pedidos\HoraLimite;
use App\Models\Operacion\Produccion\Cronograma;
use App\Models\Gestion\Inventario\ProductoDetalle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PedidoController extends Controller
{
    public function index()
    {
        $pedidos = Pedido::porContexto()
            ->porOperador()
            ->where('DistribuidoSiNo', 0)
            ->with(['producto', 'tipoPedido'])
            ->orderBy('FechaDelPedido')
            ->orderBy('IdPedidos')
            ->get();

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

        $tiposPedido = TipoPedido::porContexto()
            ->get(['IdTipoPedido as id', 'Detalle as nombre']);

        return Inertia::render('Operacion/Pedidos/Pedido/Index', [
            'pedidos' => $pedidos,
            'productos' => $productos,
            'tiposPedido' => $tiposPedido,
            'fechaHoraServidor' => Carbon::now('America/La_Paz')->format('Y-m-d H:i:s'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pedidos' => 'required|array',
            'pedidos.*.id' => 'nullable|integer',
            'pedidos.*.FechaDelPedido' => 'required|date|after:today',
            'pedidos.*.IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'pedidos.*.Unidades' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Obtener ID del tipo "Ordinario"
        $tipoOrdinario = TipoPedido::porContexto()
            ->where('Detalle', 'Ordinario')
            ->first();
        
        if (!$tipoOrdinario) {
            $tipoOrdinario = TipoPedido::find(1);
        }
        
        $idTipoOrdinario = $tipoOrdinario ? $tipoOrdinario->IdTipoPedido : 1;

        DB::beginTransaction();

        try {
            foreach ($request->pedidos as $pedidoData) {
                $fechaPedido = $pedidoData['FechaDelPedido'];
                $diaSemana = date('N', strtotime($fechaPedido));
                
                $mapaDias = [
                    1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves',
                    5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo',
                ];
                
                $diaColumna = $mapaDias[$diaSemana];

                // ✅ VALIDACIÓN: Producto debe estar en cronograma
                $cronograma = Cronograma::porContexto()
                    ->where($diaColumna, $pedidoData['IdProducto'])
                    ->exists();

                if (!$cronograma) {
                    throw new \Exception("Producto no programado para {$diaColumna}");
                }

                // ✅ VALIDACIÓN: Hora límite (SOLO por cliente)
                $fechaManana = Carbon::now('America/La_Paz')->addDay()->format('Y-m-d');
                
                if ($fechaPedido == $fechaManana) {
                    $horaLimite = HoraLimite::porContexto()
                        ->activos()  // ✅ SIN FILTRO POR SUCURSAL
                        ->first();
                    
                    $horaActual = (int) Carbon::now('America/La_Paz')->format('H');
                    
                    if ($horaLimite && $horaActual >= $horaLimite->Hora) {
                        throw new \Exception("Hora máxima para realizar pedido es {$horaLimite->Hora}:00!");
                    }
                }

                // ✅ VALIDACIÓN: Unidades > 0
                if ($pedidoData['Unidades'] <= 0) {
                    throw new \Exception("Las unidades deben ser mayor a cero!");
                }

                // Guardar o actualizar
                if (isset($pedidoData['id']) && $pedidoData['id'] > 0) {
                    $pedido = Pedido::porContexto()
                        ->porOperador()
                        ->find($pedidoData['id']);
                    
                    if ($pedido) {
                        $pedido->update([
                            'FechaDelPedido' => $fechaPedido,
                            'IdProducto' => $pedidoData['IdProducto'],
                            'Unidades' => $pedidoData['Unidades'],
                            'UnidadesAutoriza' => $pedidoData['Unidades'], // ✅ Se actualiza automáticamente
                        ]);
                    }
                } else {
                    // ✅ CREAR NUEVO - CON TODOS LOS CAMPOS OBLIGATORIOS
                    Pedido::create([
                        'IdTipoPedido' => $idTipoOrdinario,
                        'ProduceDistribuye' => 0,
                        'FechaRealiza' => Carbon::now('America/La_Paz'),
                        'FechaDelPedido' => $fechaPedido,
                        'IdProducto' => $pedidoData['IdProducto'],
                        'Unidades' => $pedidoData['Unidades'],
                        'AutorizadoNoAutorizado' => 0,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'idOperador' => $operadorId,
                        'IdOperadorPedidoExtraordinario' => 0, // ✅ CAMPO OBLIGATORIO
                        'UnidadesAutoriza' => $pedidoData['Unidades'],
                        'IdOperadorAutoriza' => 0,
                        'IdDistribucion' => 0,
                        'UnidadesDistribuidas' => 0,
                        'DistribuidoSiNo' => 0,
                        'IdOperadorRecibe' => 0,
                        'UnidadesRecibidas' => 0,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pedidos guardados correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar pedidos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $pedido = Pedido::porContexto()
                ->porOperador()
                ->findOrFail($id);
            
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
     * API: Validar si producto está en cronograma
     */
    public function apiValidarProducto(Request $request)
    {
        $request->validate([
            'IdProducto' => 'required|integer',
            'FechaDelPedido' => 'required|date',
        ]);

        $diaSemana = date('N', strtotime($request->FechaDelPedido));
        
        $mapaDias = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo',
        ];
        
        $diaColumna = $mapaDias[$diaSemana];

        $cronograma = Cronograma::porContexto()
            ->where($diaColumna, $request->IdProducto)
            ->exists();

        return response()->json([
            'success' => true,
            'valido' => $cronograma,
            'mensaje' => $cronograma ? null : "Producto no programado para {$diaColumna}",
            'dia' => $diaColumna,
        ]);
    }

    /**
     * API: Validar hora límite
     */
    public function apiValidarHoraLimite(Request $request)
    {
        $request->validate([
            'FechaDelPedido' => 'required|date',
        ]);

        $fechaManana = Carbon::now('America/La_Paz')->addDay()->format('Y-m-d');
        
        if ($request->FechaDelPedido != $fechaManana) {
            return response()->json([
                'success' => true,
                'valido' => true,
                'fecha_manana' => false,
            ]);
        }

        // ✅ SIN FILTRO POR SUCURSAL
        $horaLimite = HoraLimite::porContexto()
            ->activos()
            ->first();
        
        $horaActual = (int) Carbon::now('America/La_Paz')->format('H');

        $valido = true;
        $mensaje = null;

        if ($horaLimite && $horaActual >= $horaLimite->Hora) {
            $valido = false;
            $mensaje = "Hora máxima para realizar pedido es {$horaLimite->Hora}:00!";
        }

        return response()->json([
            'success' => true,
            'valido' => $valido,
            'fecha_manana' => true,
            'hora_actual' => $horaActual,
            'hora_limite' => $horaLimite ? $horaLimite->Hora : null,
            'mensaje' => $mensaje,
        ]);
    }

    /**
     * API: Obtener fecha y hora del servidor
     */
    public function apiGetFechaHora()
    {
        return response()->json([
            'success' => true,
            'fecha_hora' => Carbon::now('America/La_Paz')->format('Y-m-d H:i:s'),
            'fecha_hora_formateada' => Carbon::now('America/La_Paz')->format('d/m/Y H:i:s'),
        ]);
    }
}