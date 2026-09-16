<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\OperadorTipo;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\OperadorSucursalDb;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\OperadorPedidoCliente; // ✅ NUEVO
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperadorPedidoClientesController extends Controller
{
    /**
     * Mostrar listado de operadores PedidoClientes
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        // ✅ Filtrar operadores que tengan asignación en ESTE cliente
        $query = Operador::whereHas('tipo', function($q) {
                $q->where('Detalle', 'PedidoClientes');
            })
            ->whereHas('asignacionesSucursal', function($q) use ($clienteId) {
                $q->where('IdCliente', $clienteId);
            })
            ->with([
                'identificador', 
                'tipo',
                'pedidoClienteConfig',  // ✅ Cargar Ciudad, Provincia, Destino
            ]);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('identificador', function($q2) use ($search) {
                    $q2->where('Nombre', 'like', "%{$search}%")
                    ->orWhere('CI_NIT', 'like', "%{$search}%");
                })->orWhere('NombreAcceso', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }

        $operadores = $query->orderBy('IdOperador', 'desc')
            ->paginate(20)
            ->withQueryString();

        // ✅ Asignaciones filtradas por cliente
        $asignaciones = OperadorSucursalDb::where('IdCliente', $clienteId)
            ->with(['sucursal', 'operador'])
            ->get()
            ->groupBy('IdOperador')
            ->map(function($items) {
                return $items->first();
            });

        // ✅ Sucursales filtradas por cliente
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);

        // ✅ Identificadores: solo los que YA tienen operador en este cliente
        $idsConOperador = $asignaciones->pluck('IdOperador')->filter()->toArray();
        
        $identificadores = Identificador::whereIn('IdIdentificador',
                Operador::whereIn('IdOperador', $idsConOperador)
                    ->pluck('IdIdentificador')
                    ->filter()
                    ->unique()
            )
            ->orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $tipoOperador = OperadorTipo::where('Detalle', 'PedidoClientes')->first();

        return Inertia::render('Operacion/ClientesMayoristas/OperadoresClientes/Index', [
            'operadores' => $operadores,
            'asignaciones' => $asignaciones,
            'sucursales' => $sucursales,
            'identificadores' => $identificadores,
            'tipoOperador' => $tipoOperador,
            'filtros' => [
                'search' => $request->search,
                'estado' => $request->estado,
            ],
        ]);
    }

    /**
     * Almacenar un nuevo operador PedidoClientes
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        if (!$clienteId) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar una empresa primero'
            ], 422);
        }

        $tipoOperador = OperadorTipo::where('Detalle', 'PedidoClientes')->first();
        
        if (!$tipoOperador) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el tipo de operador "PedidoClientes"'
            ], 422);
        }

        // Convertir teléfonos a string
        $request->merge([
            'TelefonoDomicilio' => $request->TelefonoDomicilio !== null && $request->TelefonoDomicilio !== '' 
                ? (string) $request->TelefonoDomicilio 
                : null,
            'NumeroCelular' => $request->NumeroCelular !== null && $request->NumeroCelular !== '' 
                ? (string) $request->NumeroCelular 
                : null,
        ]);

        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'Iniciales' => 'required|string|max:5',
            'Clave' => 'required|string|min:4|max:15',
            'NombreAcceso' => 'required|string|max:20|unique:mysql_gestion_comercial_alimentos.todos_operador,NombreAcceso',
            'DireccionDomicilio' => 'nullable|string',
            'TelefonoDomicilio' => 'nullable|string|max:20',
            'NumeroCelular' => 'nullable|string|max:20',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            
            // ✅ Nuevos campos
            'Ciudad' => 'nullable|boolean',
            'Provincia' => 'nullable|boolean',
            'Destino' => 'nullable|string|max:150',
        ]);

        try {
            DB::beginTransaction();

            // 1. Crear el operador genérico
            $operador = Operador::create([
                'IdIdentificador' => $request->IdIdentificador,
                'Iniciales' => strtoupper($request->Iniciales),
                'Clave' => $request->Clave,
                'NombreAcceso' => $request->NombreAcceso,
                'DireccionDomicilio' => $request->DireccionDomicilio,
                'TelefonoDomicilio' => $request->TelefonoDomicilio,
                'NumeroCelular' => $request->NumeroCelular,
                'IdOperadorTipo' => $tipoOperador->IdOperadorTipo,
                'ActivoInactivo' => 0,
            ]);

            // 2. Asignar a la sucursal
            $asignacion = OperadorSucursalDb::create([
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
                'IdOperador' => $operador->IdOperador,
            ]);

            // ✅ 3. Crear la config específica de PedidoClientes
            OperadorPedidoCliente::create([
                'IdOperador' => $operador->IdOperador,
                'Ciudad' => $request->boolean('Ciudad') ? 1 : 0,
                'Provincia' => $request->boolean('Provincia') ? 1 : 0,
                'Destino' => $request->Destino,
                'ActivoInactivo' => 1,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now('America/La_Paz'),
            ]);

            DB::commit();

            $operador->load(['identificador', 'tipo', 'pedidoClienteConfig']);
            $asignacion->load(['sucursal', 'operador.identificador']);

            return response()->json([
                'success' => true,
                'message' => 'Operador PedidoClientes creado correctamente',
                'operador' => $operador,
                'asignacion' => $asignacion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear operador PedidoClientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear operador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar operador
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        $operador = Operador::findOrFail($id);

        // Convertir teléfonos a string
        $request->merge([
            'TelefonoDomicilio' => $request->TelefonoDomicilio !== null && $request->TelefonoDomicilio !== '' 
                ? (string) $request->TelefonoDomicilio 
                : null,
            'NumeroCelular' => $request->NumeroCelular !== null && $request->NumeroCelular !== '' 
                ? (string) $request->NumeroCelular 
                : null,
        ]);

        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'Iniciales' => 'required|string|max:5',
            'Clave' => 'nullable|string|min:4|max:15',
            'NombreAcceso' => 'required|string|max:20|unique:mysql_gestion_comercial_alimentos.todos_operador,NombreAcceso,' . $id . ',IdOperador',
            'DireccionDomicilio' => 'nullable|string',
            'TelefonoDomicilio' => 'nullable|string|max:20',
            'NumeroCelular' => 'nullable|string|max:20',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            
            // ✅ Nuevos campos
            'Ciudad' => 'nullable|boolean',
            'Provincia' => 'nullable|boolean',
            'Destino' => 'nullable|string|max:150',
        ]);

        try {
            DB::beginTransaction();

            // 1. Actualizar operador genérico
            $datos = [
                'IdIdentificador' => $request->IdIdentificador,
                'Iniciales' => strtoupper($request->Iniciales),
                'NombreAcceso' => $request->NombreAcceso,
                'DireccionDomicilio' => $request->DireccionDomicilio,
                'TelefonoDomicilio' => $request->TelefonoDomicilio,
                'NumeroCelular' => $request->NumeroCelular,
            ];

            if ($request->filled('Clave')) {
                $datos['Clave'] = $request->Clave;
            }

            $operador->update($datos);

            // 2. Actualizar asignación de sucursal
            if ($clienteId) {
                OperadorSucursalDb::updateOrCreate(
                    [
                        'IdCliente' => $clienteId,
                        'IdOperador' => $id,
                    ],
                    [
                        'IdSucursal' => $request->IdSucursal,
                    ]
                );
            }

            // ✅ 3. Actualizar o crear la config específica
            OperadorPedidoCliente::updateOrCreate(
                ['IdOperador' => $operador->IdOperador],
                [
                    'Ciudad' => $request->boolean('Ciudad') ? 1 : 0,
                    'Provincia' => $request->boolean('Provincia') ? 1 : 0,
                    'Destino' => $request->Destino,
                    'ActivoInactivo' => 1,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now('America/La_Paz'),
                ]
            );

            DB::commit();

            $operador->load(['identificador', 'tipo', 'pedidoClienteConfig']);

            return response()->json([
                'success' => true,
                'message' => 'Operador actualizado correctamente',
                'operador' => $operador,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar operador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar operador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado (activar/desactivar)
     */
    public function toggle($id)
    {
        $operador = Operador::findOrFail($id);
        
        try {
            $nuevoEstado = $operador->ActivoInactivo == 0 ? 1 : 0;
            $operador->update(['ActivoInactivo' => $nuevoEstado]);

            $mensaje = $nuevoEstado == 0 ? 'activado' : 'desactivado';

            return response()->json([
                'success' => true,
                'message' => "Operador {$mensaje} correctamente",
                'estado' => $nuevoEstado,
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
     * Eliminar (desactivar lógicamente)
     */
    public function destroy($id)
    {
        $operador = Operador::findOrFail($id);
        
        try {
            $operador->update(['ActivoInactivo' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Operador desactivado correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al desactivar operador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar operador: ' . $e->getMessage()
            ], 500);
        }
    }
}