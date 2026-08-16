<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ContenedorTipoController extends Controller
{
    /**
     * Lista de tipos de contenedor
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdCliente', $clienteId);
        
        if ($request->filled('sucursal_id')) {
            $query->where('IdSucursal', $request->sucursal_id);
        } else {
            $query->where('IdSucursal', $sucursalId);
        }
        
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }
        
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('Nombre', 'LIKE', "%{$buscar}%");
        }
        
        $tipos = $query->orderBy('Nombre')
            ->paginate(20)
            ->appends($request->all());
        
        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Tipos/Index', [
            'tipos' => $tipos,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
            'sucursalSeleccionada' => $request->sucursal_id,
        ]);
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Tipos/Create', [
            'sucursales' => $sucursales,
        ]);
    }

    /**
     * Guardar nuevo tipo de contenedor
     */
    public function store(Request $request)
    {
        $request->validate([
            'Nombre' => 'required|string|max:100',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // Verificar si ya existe
        $existe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $request->IdSucursal)
            ->where('Nombre', $request->Nombre)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un tipo de contenedor con este nombre en esta sucursal'
            ], 422);
        }

        try {
            $id = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor_tipo')
                ->insertGetId([
                    'Nombre' => $request->Nombre,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $request->IdSucursal,
                    'IdOperadorInserta' => $operadorId,
                    'FechaInserta' => Carbon::now('America/La_Paz'),
                    'ActivoInactivo' => 1,
                ]);

            $nuevoTipo = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor_tipo')
                ->where('IdTipoContenedor', $id)
                ->first();

            return response()->json([
                'success' => true,
                'tipo' => [
                    'id' => $nuevoTipo->IdTipoContenedor,
                    'nombre' => $nuevoTipo->Nombre,
                ],
                'message' => 'Tipo de contenedor creado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear tipo de contenedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Editar tipo de contenedor
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        
        $tipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdCliente', $clienteId)
            ->where('IdTipoContenedor', $id)
            ->first();

        if (!$tipo) {
            return redirect()->route('operacion.pedidos.clientes-mayoristas.contenedores.tipos.index')
                ->with('error', 'Tipo de contenedor no encontrado');
        }

        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Tipos/Edit', [
            'tipo' => $tipo,
            'sucursales' => $sucursales,
        ]);
    }

    /**
     * Actualizar tipo de contenedor
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'Nombre' => 'required|string|max:100',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // Verificar si existe otro con el mismo nombre
        $existe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $request->IdSucursal)
            ->where('Nombre', $request->Nombre)
            ->where('IdTipoContenedor', '!=', $id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un tipo de contenedor con este nombre en esta sucursal'
            ], 422);
        }

        try {
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor_tipo')
                ->where('IdTipoContenedor', $id)
                ->where('IdCliente', $clienteId)
                ->update([
                    'Nombre' => $request->Nombre,
                    'IdSucursal' => $request->IdSucursal,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipo de contenedor actualizado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar tipo de contenedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado (Activar/Inactivar)
     */
    public function cambiarEstado($id)
    {
        try {
            $clienteId = session('cliente_id');
            
            $tipo = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor_tipo')
                ->where('IdCliente', $clienteId)
                ->where('IdTipoContenedor', $id)
                ->first();

            if (!$tipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de contenedor no encontrado'
                ], 404);
            }

            $nuevoEstado = $tipo->ActivoInactivo == 1 ? 0 : 1;

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor_tipo')
                ->where('IdTipoContenedor', $id)
                ->update([
                    'ActivoInactivo' => $nuevoEstado,
                    'IdOperadorActualiza' => session('operador_id'),
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]);

            return response()->json([
                'success' => true,
                'message' => $nuevoEstado == 1 ? 'Tipo activado correctamente' : 'Tipo desactivado correctamente',
                'nuevo_estado' => $nuevoEstado
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cambiar estado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar tipo de contenedor
     */
    public function destroy($id)
    {
        try {
            $clienteId = session('cliente_id');

            // Verificar si tiene contenedores asociados
            $tieneContenedores = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor')
                ->where('IdCliente', $clienteId)
                ->where('IdTipoContenedor', $id)
                ->exists();

            if ($tieneContenedores) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar porque tiene contenedores asociados'
                ], 400);
            }

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor_tipo')
                ->where('IdCliente', $clienteId)
                ->where('IdTipoContenedor', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de contenedor eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar tipo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipos de contenedor (para API/selects)
     */
    public function getTipos(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = $request->get('sucursal_id', session('cliente_sucursal_id'));

        $tipos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->orderBy('Nombre')
            ->get(['IdTipoContenedor as id', 'Nombre as nombre']);

        return response()->json([
            'success' => true,
            'data' => $tipos
        ]);
    }
}