<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoClienteDetalle;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoClienteProducto;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoAnalisisMinimo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ProductoMinimo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoClienteMinimo;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GrupoClienteController extends Controller
{
    // ============================================================
    // LISTADO DE GRUPOS
    // ============================================================
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $query = GrupoCliente::porCliente($clienteId)
            ->porSucursal($sucursalId)
            ->with(['sucursal']);

        // Filtro por estado
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }

        // Filtro por búsqueda
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('Nombre', 'LIKE', "%{$buscar}%")
                  ->orWhere('Descripcion', 'LIKE', "%{$buscar}%");
            });
        }

        $grupos = $query->orderBy('Nombre', 'asc')
            ->paginate(20)
            ->appends($request->all());

        // Transformar para incluir contadores
        $grupos->getCollection()->transform(function ($grupo) {
            return [
                'IdGrupoCliente' => $grupo->IdGrupoCliente,
                'Nombre' => $grupo->Nombre,
                'Descripcion' => $grupo->Descripcion,
                'ActivoInactivo' => $grupo->ActivoInactivo,
                'EstadoTexto' => $grupo->EstadoTexto,
                'FechaInserta' => $grupo->FechaInsertaFormateada,
                'TotalClientes' => $grupo->total_clientes,
                'TotalProductos' => $grupo->total_productos,
                'sucursal' => $grupo->sucursal ? [
                    'Nombre' => $grupo->sucursal->Nombre,
                    'NumeroSucursal' => $grupo->sucursal->NumeroSucursal,
                ] : null,
            ];
        });

        return Inertia::render('Operacion/ClientesMayoristas/GruposClientes/Index', [
            'grupos' => $grupos,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
        ]);
    }

    // ============================================================
    // FORMULARIO DE CREACIÓN
    // ============================================================
    public function create()
    {
        return Inertia::render('Operacion/ClientesMayoristas/GruposClientes/Create', [
            'grupo' => null,
        ]);
    }

    // ============================================================
    // GUARDAR NUEVO GRUPO
    // ============================================================
    public function store(Request $request)
    {
        $request->validate([
            'Nombre' => 'required|string|max:150',
            'Descripcion' => 'nullable|string',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Verificar que no exista otro con el mismo nombre
        $existe = GrupoCliente::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('Nombre', $request->Nombre)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un grupo con este nombre'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $grupo = GrupoCliente::create([
                'Nombre' => $request->Nombre,
                'Descripcion' => $request->Descripcion,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'ActivoInactivo' => 1,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => Carbon::now('America/La_Paz'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado correctamente',
                'grupo' => [
                    'IdGrupoCliente' => $grupo->IdGrupoCliente,
                    'Nombre' => $grupo->Nombre,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear grupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // FORMULARIO DE EDICIÓN
    // ============================================================
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $grupo = GrupoCliente::porCliente($clienteId)
            ->porSucursal($sucursalId)
            ->where('IdGrupoCliente', $id)
            ->firstOrFail();

        return Inertia::render('Operacion/ClientesMayoristas/GruposClientes/Edit', [
            'grupo' => [
                'IdGrupoCliente' => $grupo->IdGrupoCliente,
                'Nombre' => $grupo->Nombre,
                'Descripcion' => $grupo->Descripcion,
                'ActivoInactivo' => $grupo->ActivoInactivo,
            ],
        ]);
    }

    // ============================================================
    // ACTUALIZAR GRUPO (solo cabecera)
    // ============================================================
    public function update(Request $request, $id)
    {
        $request->validate([
            'Nombre' => 'required|string|max:150',
            'Descripcion' => 'nullable|string',
            'ActivoInactivo' => 'nullable|integer|in:0,1',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $grupo = GrupoCliente::porCliente($clienteId)
            ->porSucursal($sucursalId)
            ->where('IdGrupoCliente', $id)
            ->firstOrFail();

        // Verificar que no exista otro con el mismo nombre
        $existe = GrupoCliente::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('Nombre', $request->Nombre)
            ->where('IdGrupoCliente', '!=', $id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe otro grupo con este nombre'
            ], 422);
        }

        try {
            $grupo->update([
                'Nombre' => $request->Nombre,
                'Descripcion' => $request->Descripcion,
                'ActivoInactivo' => $request->ActivoInactivo ?? $grupo->ActivoInactivo,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado correctamente',
                'grupo' => $grupo,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar grupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // CAMBIAR ESTADO (activar/inactivar)
    // ============================================================
    public function cambiarEstado($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            $nuevoEstado = $grupo->ActivoInactivo == 1 ? 0 : 1;

            $grupo->update([
                'ActivoInactivo' => $nuevoEstado,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'message' => $nuevoEstado == 1 ? 'Grupo activado' : 'Grupo desactivado',
                'nuevo_estado' => $nuevoEstado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cambiar estado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // ELIMINAR GRUPO
    // ============================================================
    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            DB::beginTransaction();

            // Eliminar detalles (FK con CASCADE debería hacerlo solo, pero por seguridad)
            GrupoClienteDetalle::where('IdGrupoCliente', $id)->delete();
            GrupoClienteProducto::where('IdGrupoCliente', $id)->delete();

            $grupo->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar grupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // PESTAÑA 2: CLIENTES DEL GRUPO (Operadores PedidoClientes)
    // ============================================================

    public function getClientesDisponibles($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            $idsEnEsteGrupo = GrupoClienteDetalle::where('IdGrupoCliente', $id)
                ->where('ActivoInactivo', 1)
                ->pluck('IdIdentificador')
                ->toArray();

            $idsEnOtroGrupo = GrupoClienteDetalle::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdGrupoCliente', '!=', $id)
                ->where('ActivoInactivo', 1)
                ->pluck('IdIdentificador')
                ->toArray();

            $clientes = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador as i')
                ->join('todos_operador as o', 'i.IdIdentificador', '=', 'o.IdIdentificador')
                ->join('todos_operador_tipo as ot', 'o.IdOperadorTipo', '=', 'ot.IdOperadorTipo')
                ->where('ot.Detalle', 'PedidoClientes')
                ->where('o.ActivoInactivo', 0)
                ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
                ->orderBy('i.Nombre')
                ->distinct()
                ->get()
                ->map(function ($item) use ($idsEnEsteGrupo, $idsEnOtroGrupo) {
                    return [
                        'IdIdentificador' => $item->IdIdentificador,
                        'Nombre' => $item->Nombre,
                        'CI_NIT' => $item->CI_NIT,
                        'EnEsteGrupo' => in_array($item->IdIdentificador, $idsEnEsteGrupo),
                        'EnOtroGrupo' => in_array($item->IdIdentificador, $idsEnOtroGrupo),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $clientes,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener clientes disponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function asignarClientes(Request $request, $id)
    {
        $request->validate([
            'identificadores' => 'required|array',
            'identificadores.*' => 'integer|exists:todos_identificador,IdIdentificador',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            DB::beginTransaction();

            $idsActuales = GrupoClienteDetalle::where('IdGrupoCliente', $id)
                ->where('ActivoInactivo', 1)
                ->pluck('IdIdentificador')
                ->toArray();

            $idsNuevos = $request->identificadores;

            // Agregar los que no estaban
            $agregar = array_diff($idsNuevos, $idsActuales);
            foreach ($agregar as $identificadorId) {
                $enOtroGrupo = GrupoClienteDetalle::where('IdIdentificador', $identificadorId)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdGrupoCliente', '!=', $id)
                    ->where('ActivoInactivo', 1)
                    ->exists();

                if ($enOtroGrupo) {
                    continue;
                }

                GrupoClienteDetalle::create([
                    'IdGrupoCliente' => $id,
                    'IdIdentificador' => $identificadorId,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'ActivoInactivo' => 1,
                    'IdOperadorInserta' => $operadorId,
                    'FechaInserta' => Carbon::now('America/La_Paz'),
                ]);
            }

            // Quitar los que ya no están
            $quitar = array_diff($idsActuales, $idsNuevos);
            if (!empty($quitar)) {
                GrupoClienteDetalle::where('IdGrupoCliente', $id)
                    ->whereIn('IdIdentificador', $quitar)
                    ->update([
                        'ActivoInactivo' => 0,
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => Carbon::now('America/La_Paz'),
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Clientes sincronizados correctamente',
                'agregados' => count($agregar),
                'removidos' => count($quitar),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al asignar clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // PESTAÑA 3: PRECIOS POR GRUPO
    // ============================================================

    /**
     * Obtener todos los grupos de análisis con sus productos.
     * Marca cuáles ya tienen precio en este grupo.
     */
    public function getProductos($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');  // ✅ NUEVO

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            // ✅ NUEVO: Obtener solo los grupos de análisis que aplican (ActivoInactivo = 1)
            $gruposConMinimo = GrupoAnalisisMinimo::obtenerIdsActivos($clienteId, $sucursalId);
            $gruposConMinimo = array_map('intval', $gruposConMinimo);

            // Si no hay grupos con mínimo, devolver vacío
            if (empty($gruposConMinimo)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'mensaje' => 'No hay grupos de análisis configurados para pedidos. Configura primero los mínimos globales.',
                ]);
            }

            // ✅ MODIFICADO: Solo los grupos con mínimo
            $gruposAnalisis = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
                ->whereIn('IdGrupoAnalisis', $gruposConMinimo)  // ✅ FILTRO
                ->orderBy('Grupo')
                ->get(['IdGrupoAnalisis', 'Grupo']);

            $preciosGuardados = GrupoClienteProducto::where('IdGrupoCliente', $id)
                ->where('ActivoInactivo', 1)
                ->get()
                ->keyBy('IdProducto');

            $resultado = [];
            foreach ($gruposAnalisis as $grupoAnalisis) {
                // ✅ NUEVO: Solo productos que tengan mínimo configurado (cualquier valor > 0)
                $idsProductosConMinimo = ProductoMinimo::porContexto($clienteId, $sucursalId)
                    ->activos()
                    ->where('IdGrupoAnalisis', $grupoAnalisis->IdGrupoAnalisis)
                    ->pluck('IdProducto')
                    ->toArray();

                // Si el grupo no tiene productos con mínimo, saltarlo
                if (empty($idsProductosConMinimo)) {
                    continue;
                }

                $productos = ProductoDetalle::where('IdCliente', $clienteId)
                    ->where('IdGrupoAnalisis', $grupoAnalisis->IdGrupoAnalisis)
                    ->whereIn('IdProducto', $idsProductosConMinimo)  // ✅ FILTRO
                    ->where('ActivoInactivo', 0)
                    ->orderBy('Descripcion')
                    ->get(['IdProducto', 'Codigo', 'Descripcion', 'IdGrupoAnalisis']);

                $productosConPrecio = $productos->map(function ($prod) use ($preciosGuardados) {
                    $precio = $preciosGuardados[$prod->IdProducto] ?? null;

                    return [
                        'IdProducto' => $prod->IdProducto,
                        'Codigo' => $prod->Codigo,
                        'Descripcion' => $prod->Descripcion,
                        'IdGrupoAnalisis' => $prod->IdGrupoAnalisis,
                        'PrecioSinFactura' => $precio?->PrecioSinFactura,
                        'PrecioConFactura' => $precio?->PrecioConFactura,
                        'ActivoInactivo' => $precio?->ActivoInactivo ?? 0,
                        'TienePrecio' => $precio !== null,
                    ];
                });

                $resultado[] = [
                    'IdGrupoAnalisis' => $grupoAnalisis->IdGrupoAnalisis,
                    'NombreGrupo' => $grupoAnalisis->Grupo,
                    'Productos' => $productosConPrecio,
                    'TotalProductos' => $productosConPrecio->count(),
                    'TotalConPrecio' => $productosConPrecio->where('TienePrecio', true)->count(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener productos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar precios en masa para un grupo.
     * Solo se guardan los productos que tengan precio.
     */
    public function asignarProductos(Request $request, $id)
    {
        $request->validate([
            'productos' => 'required|array',
            'productos.*.IdProducto' => 'required|integer|exists:inventario_productodetalle,IdProducto',
            'productos.*.PrecioSinFactura' => 'nullable|numeric|min:0',
            'productos.*.PrecioConFactura' => 'nullable|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            DB::beginTransaction();

            $guardados = 0;
            $eliminados = 0;

            foreach ($request->productos as $prod) {
                $sinFactura = $prod['PrecioSinFactura'] ?? null;
                $conFactura = $prod['PrecioConFactura'] ?? null;

                // Si no tiene ningún precio, se omite/elimina
                $tieneAlgunPrecio = ($sinFactura !== null && $sinFactura > 0)
                    || ($conFactura !== null && $conFactura > 0);

                if (!$tieneAlgunPrecio) {
                    $deleted = GrupoClienteProducto::where('IdGrupoCliente', $id)
                        ->where('IdProducto', $prod['IdProducto'])
                        ->delete();
                    
                    if ($deleted) $eliminados++;
                    continue;
                }

                // Upsert (solo precios, sin PedidoMinimo)
                GrupoClienteProducto::updateOrCreate(
                    [
                        'IdGrupoCliente' => $id,
                        'IdProducto' => $prod['IdProducto'],
                    ],
                    [
                        'PrecioSinFactura' => $sinFactura,
                        'PrecioConFactura' => $conFactura,
                        'ActivoInactivo' => 1,
                        'IdOperadorInserta' => $operadorId,
                        'FechaInserta' => Carbon::now('America/La_Paz'),
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => Carbon::now('America/La_Paz'),
                    ]
                );

                $guardados++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Precios guardados: {$guardados}, eliminados: {$eliminados}",
                'guardados' => $guardados,
                'eliminados' => $eliminados,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al asignar productos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un precio específico
     */
    public function eliminarProducto($id, $idProducto)
    {
        $clienteId = session('cliente_id');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            GrupoClienteProducto::where('IdGrupoCliente', $id)
                ->where('IdProducto', $idProducto)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Precio eliminado correctamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    // ============================================================
    // PESTAÑA 4: MÍNIMOS POR GRUPO DE ANÁLISIS (POR GRUPO DE CLIENTES)
    // ============================================================

    /**
     * Obtener los grupos de análisis ACTIVOS con su mínimo actual para este grupo de clientes.
     */
    public function getMinimos($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            // ✅ Solo los grupos de análisis ACTIVOS (los que activó en MinimosGlobales)
            $gruposActivos = GrupoAnalisisMinimo::obtenerGruposActivosConNombre($clienteId, $sucursalId);

            // ✅ Mapa de mínimos ya guardados para este grupo de clientes
            $mapaGuardado = GrupoClienteMinimo::obtenerMapaPorGrupoCliente($id, $clienteId, $sucursalId);

            $resultado = [];
            foreach ($gruposActivos as $g) {
                $idGrupoAnalisis = $g['IdGrupoAnalisis'];
                $cantidad = $mapaGuardado[$idGrupoAnalisis] ?? 0;

                // Total de productos activos de ese grupo
                $totalProductos = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdCliente', $clienteId)
                    ->where('IdGrupoAnalisis', $idGrupoAnalisis)
                    ->where('ActivoInactivo', 0)
                    ->count();

                $resultado[] = [
                    'IdGrupoAnalisis' => $idGrupoAnalisis,
                    'NombreGrupo' => $g['Grupo'],
                    'CantidadMinimaGrupo' => (float) $cantidad,
                    'TotalProductos' => $totalProductos,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener mínimos por grupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar mínimos por grupo de análisis para este grupo de clientes (bulk upsert).
     */
    public function guardarMinimos(Request $request, $id)
    {
        $request->validate([
            'grupos' => 'required|array',
            'grupos.*.IdGrupoAnalisis' => 'required|integer|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'grupos.*.CantidadMinima' => 'nullable|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $ahora = Carbon::now('America/La_Paz');

        try {
            $grupo = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('IdGrupoCliente', $id)
                ->firstOrFail();

            DB::beginTransaction();

            $aGuardar = [];
            $aEliminar = [];

            foreach ($request->grupos as $g) {
                $cantidad = (float) ($g['CantidadMinima'] ?? 0);

                if ($cantidad > 0) {
                    $aGuardar[] = [
                        'IdGrupoCliente' => $id,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'IdGrupoAnalisis' => $g['IdGrupoAnalisis'],
                        'CantidadMinimaGrupo' => $cantidad,
                        'ActivoInactivo' => 1,
                        'IdOperadorInserta' => $operadorId,
                        'FechaInserta' => $ahora,
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => $ahora,
                    ];
                } else {
                    $aEliminar[] = $g['IdGrupoAnalisis'];
                }
            }

            // Bulk upsert
            if (!empty($aGuardar)) {
                GrupoClienteMinimo::upsert(
                    $aGuardar,
                    ['IdGrupoCliente', 'IdGrupoAnalisis'],
                    ['CantidadMinimaGrupo', 'ActivoInactivo', 'IdOperadorActualiza', 'FechaActualiza']
                );
            }

            // Eliminar los que quedaron en 0
            if (!empty($aEliminar)) {
                GrupoClienteMinimo::porGrupoCliente($id)
                    ->whereIn('IdGrupoAnalisis', $aEliminar)
                    ->delete();
            }

            DB::commit();

            // Invalidar caché
            GrupoClienteMinimo::invalidarCache($id, $clienteId, $sucursalId);

            return response()->json([
                'success' => true,
                'message' => 'Mínimos guardados correctamente',
                'resumen' => [
                    'guardados' => count($aGuardar),
                    'eliminados' => count($aEliminar),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar mínimos por grupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    // ============================================================
    // BUSCAR CLIENTE Y SU GRUPO
    // ============================================================

    /**
     * Busca clientes por nombre o CI/NIT y devuelve a qué grupo pertenecen.
     */
    public function buscarCliente(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $termino = trim($request->get('q', ''));

        if (strlen($termino) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            // Buscar clientes (operadores PedidoClientes)
            $clientes = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador as i')
                ->join('todos_operador as o', 'i.IdIdentificador', '=', 'o.IdIdentificador')
                ->join('todos_operador_tipo as ot', 'o.IdOperadorTipo', '=', 'ot.IdOperadorTipo')
                ->where('ot.Detalle', 'PedidoClientes')
                ->where('o.ActivoInactivo', 0)
                ->where(function ($q) use ($termino) {
                    $q->where('i.Nombre', 'LIKE', "%{$termino}%")
                    ->orWhere('i.CI_NIT', 'LIKE', "%{$termino}%");
                })
                ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
                ->orderBy('i.Nombre')
                ->limit(20)
                ->distinct()
                ->get();

            if ($clientes->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $idsIdentificadores = $clientes->pluck('IdIdentificador')->toArray();

            // Buscar en qué grupo está cada uno (detalle activo)
            $detalles = GrupoClienteDetalle::whereIn('IdIdentificador', $idsIdentificadores)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->get(['IdIdentificador', 'IdGrupoCliente'])
                ->keyBy('IdIdentificador');

            // Obtener info de los grupos
            $idsGrupos = $detalles->pluck('IdGrupoCliente')->unique()->toArray();
            $grupos = GrupoCliente::whereIn('IdGrupoCliente', $idsGrupos)
                ->get(['IdGrupoCliente', 'Nombre', 'ActivoInactivo'])
                ->keyBy('IdGrupoCliente');

            $resultado = $clientes->map(function ($c) use ($detalles, $grupos) {
                $detalle = $detalles[$c->IdIdentificador] ?? null;
                $grupo = $detalle ? ($grupos[$detalle->IdGrupoCliente] ?? null) : null;

                return [
                    'IdIdentificador' => $c->IdIdentificador,
                    'Nombre' => $c->Nombre,
                    'CI_NIT' => $c->CI_NIT,
                    'IdGrupoCliente' => $grupo ? $grupo->IdGrupoCliente : null,
                    'NombreGrupo' => $grupo ? $grupo->Nombre : null,
                    'GrupoActivo' => $grupo ? ($grupo->ActivoInactivo == 1) : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al buscar cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar: ' . $e->getMessage()
            ], 500);
        }
    }
}