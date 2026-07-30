<?php

namespace App\Http\Controllers\Operacion\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\Pedido;
use App\Models\Operacion\Pedidos\TipoPedido;
use App\Models\Operacion\Pedidos\HoraLimite;
use App\Models\Operacion\Produccion\Cronograma;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PedidoExtraordinarioController extends Controller
{
    /**
     * Mostrar formulario de pedido extraordinario
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        $operadorNombre = session('operador_nombre');

        // Obtener pedidos futuros del operador
        $pedidos = Pedido::porContexto()
            ->porOperador()
            ->where('DistribuidoSiNo', 0)
            ->whereDate('FechaDelPedido', '>', Carbon::now('America/La_Paz')->format('Y-m-d'))
            ->with(['producto', 'tipoPedido', 'sucursal'])
            ->orderBy('FechaDelPedido')
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

        // ✅ SUCURSALES del cliente (formato: "Número - Nombre")
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('NumeroSucursal')
            ->get(['IdClienteSucursal as id', 'NumeroSucursal', 'Nombre'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'texto' => $item->NumeroSucursal . ' - ' . $item->Nombre,
                ];
            });

        // Tipos de pedido
        $tiposPedido = TipoPedido::porContexto()
            ->get(['IdTipoPedido as id', 'Detalle as nombre']);

        // Obtener hora límite activa
        $horaLimite = HoraLimite::porContexto()
            ->activos()
            ->first();

        // Hora límite + 2 horas para extraordinarios
        $horaLimiteExtra = null;
        if ($horaLimite) {
            $horaLimiteExtra = $horaLimite->Hora + 2;
            if ($horaLimiteExtra > 24) {
                $horaLimiteExtra = 24;
            }
        }

        // ✅ Sucursal por defecto (la del contexto)
        $sucursalDefault = session('cliente_sucursal_id');

        return Inertia::render('Operacion/Pedidos/PedidoExtraordinario/Index', [
            'pedidos' => $pedidos,
            'productos' => $productos,
            'sucursales' => $sucursales,
            'tiposPedido' => $tiposPedido,
            'fechaHoraServidor' => Carbon::now('America/La_Paz')->format('Y-m-d H:i:s'),
            'horaLimite' => $horaLimite ? $horaLimite->Hora : null,
            'horaLimiteExtra' => $horaLimiteExtra,
            'operadorId' => $operadorId,
            'operadorNombre' => $operadorNombre,
            'sucursalDefault' => $sucursalDefault,
        ]);
    }

    /**
     * ✅ Validar cronograma SOLO si ActivaControlDia = 0 (Activo)
     */
    private function validarCronograma($fechaPedido, $productoId)
    {
        $horaLimite = HoraLimite::porContexto()
            ->activos()
            ->first();
        
        if (!$horaLimite) {
            return true;
        }
        
        $diaSemana = date('N', strtotime($fechaPedido));
        $mapaDias = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo',
        ];
        $diaColumna = $mapaDias[$diaSemana];

        $cronograma = Cronograma::porContexto()
            ->where($diaColumna, $productoId)
            ->exists();

        if (!$cronograma) {
            throw new \Exception("Pedido no corresponde a un producto programado para la produccion del dia {$diaColumna}, revisar cronograma de produccion..,! ");
        }

        return true;
    }

    /**
     * Guardar o actualizar pedido extraordinario
     */
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:operacion_ventas_pedidos,IdPedidos',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'FechaDelPedido' => 'required|date|after:today',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Unidades' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = $request->IdSucursal;
        $operadorId = session('operador_id');

        $fechaPedido = $request->FechaDelPedido;
        $fechaActual = Carbon::now('America/La_Paz')->format('Y-m-d');
        
        if ($fechaPedido <= $fechaActual) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha del pedido debe ser futura'
            ], 422);
        }

        try {
            $this->validarCronograma($fechaPedido, $request->IdProducto);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }

        $fechaManana = Carbon::now('America/La_Paz')->addDay()->format('Y-m-d');
        
        if ($fechaPedido == $fechaManana) {
            $horaLimite = HoraLimite::porContexto()
                ->activos()
                ->first();
            
            $horaActual = (int) Carbon::now('America/La_Paz')->format('H');
            
            if ($horaLimite) {
                $horaLimiteExtra = $horaLimite->Hora + 2;
                if ($horaLimiteExtra > 24) {
                    $horaLimiteExtra = 24;
                }
                
                if ($horaActual >= $horaLimiteExtra) {
                    return response()->json([
                        'success' => false,
                        'message' => "Hora maxima para realiar pedido es {$horaLimiteExtra}:00...! comuníquece con supervisor..."
                    ], 422);
                }
            }
        }

        if ($request->Unidades <= 0) {
            return response()->json([
                'success' => false,
                'message' => '¡Las unidades deben ser mayor a cero...!'
            ], 422);
        }

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

                if ($pedido->FechaDelPedido <= $fechaActual) {
                    throw new \Exception('No se puede editar un pedido con fecha pasada');
                }

                $pedido->update([
                    'IdSucursal' => $sucursalId,
                    'FechaDelPedido' => $fechaPedido,
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
            Log::error('Error en pedido extraordinario: ' . $e->getMessage());
            
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
                ->with(['producto', 'sucursal'])
                ->findOrFail($id);

            $fechaActual = Carbon::now('America/La_Paz')->format('Y-m-d');
            if ($pedido->FechaDelPedido <= $fechaActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar un pedido con fecha pasada'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $pedido->IdPedidos,
                    'IdSucursal' => $pedido->IdSucursal,
                    'FechaDelPedido' => $pedido->FechaDelPedido,
                    'IdProducto' => $pedido->IdProducto,
                    'Unidades' => $pedido->Unidades,
                    'producto_texto' => $pedido->producto ? 
                        $pedido->producto->Codigo . ' - ' . $pedido->producto->Descripcion : '',
                    'sucursal_texto' => $pedido->sucursal ? 
                        $pedido->sucursal->NumeroSucursal . ' - ' . $pedido->sucursal->Nombre : '',
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
            if ($pedido->FechaDelPedido <= $fechaActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un pedido con fecha pasada'
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
     * API: Validar producto en cronograma
     */
    public function apiValidarProducto(Request $request)
    {
        $request->validate([
            'IdProducto' => 'required|integer',
            'FechaDelPedido' => 'required|date',
        ]);

        $horaLimite = HoraLimite::porContexto()
            ->activos()
            ->first();
        
        if (!$horaLimite) {
            return response()->json([
                'success' => true,
                'valido' => true,
                'mensaje' => null,
                'dia' => null,
                'control_activo' => false,
            ]);
        }

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
            'mensaje' => $cronograma ? null : "Pedido no corresponde a un producto programado para la produccion del dia {$diaColumna}, revisar cronograma de produccion..,! ",
            'dia' => $diaColumna,
            'control_activo' => true,
        ]);
    }

    /**
     * API: Validar hora límite con +2 horas
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

        $horaLimite = HoraLimite::porContexto()
            ->activos()
            ->first();
        
        $horaActual = (int) Carbon::now('America/La_Paz')->format('H');

        $valido = true;
        $mensaje = null;
        $horaLimiteExtra = null;

        if ($horaLimite) {
            $horaLimiteExtra = $horaLimite->Hora + 2;
            if ($horaLimiteExtra > 24) {
                $horaLimiteExtra = 24;
            }
            
            if ($horaActual >= $horaLimiteExtra) {
                $valido = false;
                $mensaje = "Hora maxima para realiar pedido es {$horaLimiteExtra}:00...! comuníquece con supervisor...";
            }
        }

        return response()->json([
            'success' => true,
            'valido' => $valido,
            'fecha_manana' => true,
            'hora_actual' => $horaActual,
            'hora_limite' => $horaLimite ? $horaLimite->Hora : null,
            'hora_limite_extra' => $horaLimiteExtra,
            'mensaje' => $mensaje,
        ]);
    }
}