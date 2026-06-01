<?php

namespace App\Http\Controllers\Operacion\Produccion;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Produccion\Cronograma;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\Fecha;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CronogramaController extends Controller
{
    /**
     * Mostrar el grid editable del cronograma
     */
    public function index()
    {
        // Obtener todos los registros del cronograma para este cliente/operador
        $cronogramas = Cronograma::porContexto()
            ->porOperador()
            ->with([
                'producto_lunes',
                'producto_martes',
                'producto_miercoles',
                'producto_jueves',
                'producto_viernes',
                'producto_sabado',
                'producto_domingo'
            ])
            ->orderBy('IdCronograma')
            ->get();

        // Obtener lista de productos para los selects
        $productos = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto as id', 'Codigo as codigo', 'Descripcion as descripcion'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'codigo' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'texto' => $item->codigo . ' - ' . $item->descripcion,
                ];
            });

        return Inertia::render('Operacion/Produccion/Cronograma/Index', [
            'cronogramas' => $cronogramas,
            'productos' => $productos,
            'dias' => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'],
        ]);
    }

    /**
     * Mostrar vista de solo lectura del cronograma
     */
    public function show()
    {
        // Obtener todos los registros del cronograma para este cliente/operador
        $cronogramas = Cronograma::porContexto()
            ->porOperador()
            ->with([
                'producto_lunes',
                'producto_martes',
                'producto_miercoles',
                'producto_jueves',
                'producto_viernes',
                'producto_sabado',
                'producto_domingo'
            ])
            ->orderBy('IdCronograma')
            ->get();

        // Obtener lista de productos para mostrar nombres
        $productos = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto as id', 'Codigo as codigo', 'Descripcion as descripcion'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'codigo' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'texto' => $item->codigo . ' - ' . $item->descripcion,
                ];
            });

        return Inertia::render('Operacion/Produccion/Cronograma/IndexReadonly', [
            'cronogramas' => $cronogramas,
            'productos' => $productos,
            'dias' => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'],
            'titulo' => 'Consulta de Cronograma de Producción',
            'subtitulo' => 'Visualización de productos programados por día',
        ]);
    }

    /**
     * Guardar o actualizar el cronograma (Grid editable)
     */
    public function store(Request $request)
    {
        Log::info('=== GUARDANDO CRONOGRAMA ===');
        Log::info($request->all());

        $request->validate([
            'cronogramas' => 'required|array',
            'cronogramas.*.id' => 'nullable|integer',
            'cronogramas.*.Lunes' => 'nullable|integer|exists:inventario_productodetalle,IdProducto',
            'cronogramas.*.Martes' => 'nullable|integer|exists:inventario_productodetalle,IdProducto',
            'cronogramas.*.Miercoles' => 'nullable|integer|exists:inventario_productodetalle,IdProducto',
            'cronogramas.*.Jueves' => 'nullable|integer|exists:inventario_productodetalle,IdProducto',
            'cronogramas.*.Viernes' => 'nullable|integer|exists:inventario_productodetalle,IdProducto',
            'cronogramas.*.Sabado' => 'nullable|integer|exists:inventario_productodetalle,IdProducto',
            'cronogramas.*.Domingo' => 'nullable|integer|exists:inventario_productodetalle,IdProducto',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        foreach ($request->cronogramas as $cronogramaData) {
            if (isset($cronogramaData['id']) && $cronogramaData['id'] > 0) {
                // Actualizar existente
                $cronograma = Cronograma::porContexto()
                    ->porOperador()
                    ->find($cronogramaData['id']);
                
                if ($cronograma) {
                    $cronograma->update([
                        'Lunes' => !empty($cronogramaData['Lunes']) ? $cronogramaData['Lunes'] : null,
                        'Martes' => !empty($cronogramaData['Martes']) ? $cronogramaData['Martes'] : null,
                        'Miercoles' => !empty($cronogramaData['Miercoles']) ? $cronogramaData['Miercoles'] : null,
                        'Jueves' => !empty($cronogramaData['Jueves']) ? $cronogramaData['Jueves'] : null,
                        'Viernes' => !empty($cronogramaData['Viernes']) ? $cronogramaData['Viernes'] : null,
                        'Sabado' => !empty($cronogramaData['Sabado']) ? $cronogramaData['Sabado'] : null,
                        'Domingo' => !empty($cronogramaData['Domingo']) ? $cronogramaData['Domingo'] : null,
                    ]);
                }
            } else {
                // Crear nuevo (solo si tiene al menos un día con producto)
                $tieneProducto = false;
                foreach (['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'] as $dia) {
                    if (!empty($cronogramaData[$dia])) {
                        $tieneProducto = true;
                        break;
                    }
                }
                
                if ($tieneProducto) {
                    Cronograma::create([
                        'Lunes' => !empty($cronogramaData['Lunes']) ? $cronogramaData['Lunes'] : null,
                        'Martes' => !empty($cronogramaData['Martes']) ? $cronogramaData['Martes'] : null,
                        'Miercoles' => !empty($cronogramaData['Miercoles']) ? $cronogramaData['Miercoles'] : null,
                        'Jueves' => !empty($cronogramaData['Jueves']) ? $cronogramaData['Jueves'] : null,
                        'Viernes' => !empty($cronogramaData['Viernes']) ? $cronogramaData['Viernes'] : null,
                        'Sabado' => !empty($cronogramaData['Sabado']) ? $cronogramaData['Sabado'] : null,
                        'Domingo' => !empty($cronogramaData['Domingo']) ? $cronogramaData['Domingo'] : null,
                        'IdCliente' => $clienteId,
                        'IdOperador' => $operadorId,
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Cronograma guardado correctamente');
    }

    /**
     * Eliminar una fila del cronograma
     */
    public function destroy($id)
    {
        try {
            $cronograma = Cronograma::porContexto()
                ->porOperador()
                ->find($id);
            
            if ($cronograma) {
                $cronograma->delete();
                return response()->json([
                    'success' => true, 
                    'message' => 'Fila eliminada correctamente'
                ]);
            }
            
            return response()->json([
                'success' => false, 
                'message' => 'Registro no encontrado'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Error al eliminar cronograma: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obtener cronograma para consultas externas
     */
    public function apiGet()
    {
        $cronogramas = Cronograma::porContexto()
            ->porOperador()
            ->with([
                'producto_lunes',
                'producto_martes',
                'producto_miercoles',
                'producto_jueves',
                'producto_viernes',
                'producto_sabado',
                'producto_domingo'
            ])
            ->orderBy('IdCronograma')
            ->get();

        // Formatear para API
        $data = $cronogramas->map(function($item) {
            return [
                'id' => $item->IdCronograma,
                'lunes' => $item->producto_lunes ? [
                    'id' => $item->Lunes,
                    'codigo' => $item->producto_lunes->Codigo,
                    'descripcion' => $item->producto_lunes->Descripcion
                ] : null,
                'martes' => $item->producto_martes ? [
                    'id' => $item->Martes,
                    'codigo' => $item->producto_martes->Codigo,
                    'descripcion' => $item->producto_martes->Descripcion
                ] : null,
                'miercoles' => $item->producto_miercoles ? [
                    'id' => $item->Miercoles,
                    'codigo' => $item->producto_miercoles->Codigo,
                    'descripcion' => $item->producto_miercoles->Descripcion
                ] : null,
                'jueves' => $item->producto_jueves ? [
                    'id' => $item->Jueves,
                    'codigo' => $item->producto_jueves->Codigo,
                    'descripcion' => $item->producto_jueves->Descripcion
                ] : null,
                'viernes' => $item->producto_viernes ? [
                    'id' => $item->Viernes,
                    'codigo' => $item->producto_viernes->Codigo,
                    'descripcion' => $item->producto_viernes->Descripcion
                ] : null,
                'sabado' => $item->producto_sabado ? [
                    'id' => $item->Sabado,
                    'codigo' => $item->producto_sabado->Codigo,
                    'descripcion' => $item->producto_sabado->Descripcion
                ] : null,
                'domingo' => $item->producto_domingo ? [
                    'id' => $item->Domingo,
                    'codigo' => $item->producto_domingo->Codigo,
                    'descripcion' => $item->producto_domingo->Descripcion
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $cronogramas->count()
        ]);
    }

    /**
     * API: Obtener cronograma por fecha específica
     */
    public function apiGetByDate($fecha)
    {
        try {
            // Validar formato de fecha
            if (!strtotime($fecha)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de fecha inválido. Use YYYY-MM-DD',
                    'data' => null
                ], 400);
            }
            
            // Determinar qué día de la semana es la fecha
            $diaSemana = date('w', strtotime($fecha));
            $diasMap = [
                0 => 'Domingo',
                1 => 'Lunes',
                2 => 'Martes',
                3 => 'Miercoles',
                4 => 'Jueves',
                5 => 'Viernes',
                6 => 'Sabado'
            ];
            
            $diaNombre = $diasMap[$diaSemana];
            
            // Buscar el cronograma (normalmente solo hay uno por operador)
            $cronograma = Cronograma::porContexto()
                ->porOperador()
                ->first();
            
            if (!$cronograma) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'fecha' => $fecha,
                        'dia' => $diaNombre,
                        'producto_id' => null,
                        'producto' => null,
                        'message' => 'No hay cronograma configurado'
                    ]
                ]);
            }
            
            $productoId = $cronograma->$diaNombre;
            
            $producto = null;
            if ($productoId) {
                $producto = ProductoDetalle::find($productoId);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'fecha' => $fecha,
                    'dia' => $diaNombre,
                    'producto_id' => $productoId,
                    'producto' => $producto ? [
                        'id' => $producto->IdProducto,
                        'codigo' => $producto->Codigo,
                        'descripcion' => $producto->Descripcion,
                    ] : null,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en apiGetByDate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * API: Obtener producto por día de semana
     */
    public function apiGetByDay($dia)
    {
        try {
            $diasValidos = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
            
            if (!in_array($dia, $diasValidos)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Día inválido. Use: ' . implode(', ', $diasValidos)
                ], 400);
            }
            
            $cronograma = Cronograma::porContexto()
                ->porOperador()
                ->first();
            
            $productoId = $cronograma ? $cronograma->$dia : null;
            
            $producto = null;
            if ($productoId) {
                $producto = ProductoDetalle::find($productoId);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'dia' => $dia,
                    'producto_id' => $productoId,
                    'producto' => $producto ? [
                        'id' => $producto->IdProducto,
                        'codigo' => $producto->Codigo,
                        'descripcion' => $producto->Descripcion,
                    ] : null,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en apiGetByDay: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}