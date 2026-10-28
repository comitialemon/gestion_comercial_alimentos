<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\OperadorTipo;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\OperadorSucursalDb;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\OperadorPedidoCliente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperadorPedidoClientesController extends Controller
{
    /**
     * Listado de operadores PedidoClientes de la empresa actual.
     * Cada operador viene con sus N asignaciones a sucursales (de esta empresa)
     * y la config Ciudad/Provincia/Destino de cada asignación.
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id'); // 🔥 Traemos la sucursal de la sesión

        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $query = Operador::whereHas('tipo', function ($q) {
                $q->where('Detalle', 'PedidoClientes');
            })
            ->whereHas('asignacionesSucursal', function ($q) use ($clienteId) {
                // 🔥 Solo operadores que tengan asignación en ESTA empresa
                $q->where('IdCliente', $clienteId);
            })
            ->with([
                'identificador',
                'tipo',
                'asignacionesSucursal' => function ($q) use ($clienteId) {
                    // 🔥 Solo traemos las asignaciones de ESTA empresa
                    $q->where('IdCliente', $clienteId)
                      ->with(['sucursal', 'pedidoClienteConfig']);
                },
            ]);

        // Filtro búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('identificador', function ($q2) use ($search) {
                    $q2->where('Nombre', 'like', "%{$search}%")
                       ->orWhere('CI_NIT', 'like', "%{$search}%");
                })->orWhere('NombreAcceso', 'like', "%{$search}%");
            });
        }

        // Filtro estado
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }

        $operadores = $query->orderBy('IdOperador', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Sucursales activas de esta empresa
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);

        // Identificadores que YA tienen un operador PedidoClientes en esta empresa
        $idsIdentificadoresConOperador = Operador::whereHas('tipo', function ($q) {
                $q->where('Detalle', 'PedidoClientes');
            })
            ->whereHas('asignacionesSucursal', function ($q) use ($clienteId) {
                $q->where('IdCliente', $clienteId);
            })
            ->pluck('IdIdentificador')
            ->filter()
            ->unique()
            ->toArray();

        // Identificadores disponibles para crear
        $identificadores = Identificador::whereNotIn('IdIdentificador', $idsIdentificadoresConOperador)
            ->orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $tipoOperador = OperadorTipo::where('Detalle', 'PedidoClientes')->first();

        return Inertia::render('Operacion/ClientesMayoristas/OperadoresClientes/Index', [
            'operadores' => $operadores,
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
     * Buscar operador PedidoClientes por identificador.
     * Usado por el modal para autocompletar cuando el usuario elige
     * un CI/NIT que ya tiene operador en esta u otra empresa.
     */
    public function buscarPorIdentificador($idIdentificador)
    {
        $clienteId = session('cliente_id');

        $operador = Operador::where('IdIdentificador', $idIdentificador)
            ->whereHas('tipo', fn($q) => $q->where('Detalle', 'PedidoClientes'))
            ->with([
                'identificador',
                'asignacionesSucursal' => function ($q) use ($clienteId) {
                    $q->where('IdCliente', $clienteId)
                      ->with(['sucursal', 'pedidoClienteConfig']);
                },
            ])
            ->first();

        if (!$operador) {
            return response()->json(['existe' => false, 'operador' => null]);
        }

        // ¿Ya tiene asignación en esta empresa?
        $asignacionActual = $operador->asignacionesSucursal->first();

        return response()->json([
            'existe' => true,
            'ya_en_esta_empresa' => (bool) $asignacionActual,
            'operador' => [
                'IdOperador'        => $operador->IdOperador,
                'IdIdentificador'   => $operador->IdIdentificador,
                'Iniciales'         => $operador->Iniciales,
                'NombreAcceso'      => $operador->NombreAcceso,
                'DireccionDomicilio'=> $operador->DireccionDomicilio,
                'TelefonoDomicilio' => $operador->TelefonoDomicilio,
                'NumeroCelular'     => $operador->NumeroCelular,
                'ActivoInactivo'    => $operador->ActivoInactivo,
                'nombre_persona'    => $operador->identificador?->Nombre,
                'ci_nit'            => $operador->identificador?->CI_NIT,
                'asignaciones_en_esta_empresa' => $operador->asignacionesSucursal->map(function ($a) {
                    return [
                        'IdSucursalDB'    => $a->IdSucursalDB,
                        'IdSucursal'      => $a->IdSucursal,
                        'sucursal_nombre' => $a->sucursal?->Nombre,
                        'Ciudad'          => $a->pedidoClienteConfig?->Ciudad,
                        'Provincia'       => $a->pedidoClienteConfig?->Provincia,
                        'Destino'         => $a->pedidoClienteConfig?->Destino,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Crear operador nuevo O agregar sucursal a un operador existente.
     *
     * Flujo:
     *  1. ¿Existe ya un Operador PedidoClientes con este IdIdentificador?
     *     - SÍ  → Reutilizarlo (actualizar datos si los editaron).
     *             NO se crea otro operador. Solo se agrega una fila en
     *             OperadorSucursalDb + una config en OperadorPedidoCliente.
     *     - NO  → Crear Operador + asignación + config.
     *  2. La asignación (OperadorSucursalDb) se crea con firstOrCreate,
     *     así no falla si ya existe (empresa + sucursal + operador).
     *  3. La config (OperadorPedidoCliente) se crea con updateOrCreate
     *     usando IdSucursalDB como clave única.
     */
    public function store(Request $request)
    {
        $clienteId  = session('cliente_id');
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

        // Convertir teléfonos a string antes de validar
        $request->merge([
            'TelefonoDomicilio' => $request->TelefonoDomicilio !== null && $request->TelefonoDomicilio !== ''
                ? (string) $request->TelefonoDomicilio : null,
            'NumeroCelular' => $request->NumeroCelular !== null && $request->NumeroCelular !== ''
                ? (string) $request->NumeroCelular : null,
        ]);

        $request->validate([
            'IdIdentificador'   => 'required|exists:todos_identificador,IdIdentificador',
            'Iniciales'         => 'required|string|max:5',
            'Clave'             => 'nullable|string|min:4|max:15',
            'NombreAcceso'      => 'required|string|max:20',
            'DireccionDomicilio'=> 'nullable|string',
            'TelefonoDomicilio' => 'nullable|string|max:20',
            'NumeroCelular'     => 'nullable|string|max:20',
            'IdSucursal'        => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Ciudad'            => 'nullable|boolean',
            'Provincia'         => 'nullable|boolean',
            'Destino'           => 'nullable|string|max:150',
        ]);

        try {
            DB::beginTransaction();

            // 1. ¿Existe ya un Operador PedidoClientes con este IdIdentificador?
            $operador = Operador::where('IdIdentificador', $request->IdIdentificador)
                ->whereHas('tipo', fn($q) => $q->where('Detalle', 'PedidoClientes'))
                ->first();

            if ($operador) {
                // REUTILIZAR: actualizar datos personales si los editaron.
                // Nota: como el operador es global (IdIdentificador único),
                // estos cambios afectan también a las otras empresas donde
                // esté asignado. Eso es coherente con el modelo actual.
                $operador->update([
                    'Iniciales'         => strtoupper($request->Iniciales),
                    'NombreAcceso'      => $request->NombreAcceso,
                    'DireccionDomicilio'=> $request->DireccionDomicilio,
                    'TelefonoDomicilio' => $request->TelefonoDomicilio,
                    'NumeroCelular'     => $request->NumeroCelular,
                ]);

                // Solo cambiar clave si la enviaron
                if ($request->filled('Clave')) {
                    $operador->update(['Clave' => $request->Clave]);
                }
            } else {
                // CREAR: la clave es obligatoria
                $request->validate(['Clave' => 'required|string|min:4|max:15']);

                $operador = Operador::create([
                    'IdIdentificador'   => $request->IdIdentificador,
                    'Iniciales'         => strtoupper($request->Iniciales),
                    'Clave'             => $request->Clave,
                    'NombreAcceso'      => $request->NombreAcceso,
                    'DireccionDomicilio'=> $request->DireccionDomicilio,
                    'TelefonoDomicilio' => $request->TelefonoDomicilio,
                    'NumeroCelular'     => $request->NumeroCelular,
                    'IdOperadorTipo'    => $tipoOperador->IdOperadorTipo,
                    'ActivoInactivo'    => 0,
                ]);
            }

            // 2. Asignación a la sucursal (idempotente)
            $asignacion = OperadorSucursalDb::firstOrCreate(
                [
                    'IdCliente'  => $clienteId,
                    'IdSucursal' => $request->IdSucursal,
                    'IdOperador' => $operador->IdOperador,
                ]
            );

            // 3. Config PedidoClientes por asignación
            OperadorPedidoCliente::updateOrCreate(
                ['IdSucursalDB' => $asignacion->IdSucursalDB],
                [
                    'IdOperador' => $operador->IdOperador,
                    'IdCliente'  => $clienteId,
                    'Ciudad'     => $request->boolean('Ciudad') ? 1 : 0, // 🔥 Cambiado
                    'Provincia'  => $request->boolean('Provincia') ? 1 : 0, // 🔥 Cambiado
                    'Destino'    => $request->Destino,
                    'ActivoInactivo'      => 1,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza'      => now('America/La_Paz'),
                    'IdOperadorInserta'   => $operadorId,
                    'FechaInserta'        => now('America/La_Paz'),
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $operador->wasRecentlyCreated
                    ? 'Operador PedidoClientes creado correctamente'
                    : 'Sucursal agregada al operador existente correctamente',
                'operador'   => $operador->load(['identificador', 'tipo']),
                'asignacion' => $asignacion->load(['sucursal', 'pedidoClienteConfig']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear operador PedidoClientes: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear operador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar datos del operador Y la config de UNA asignación específica.
     * Se identifica por IdSucursalDB (no por IdOperador).
     */
    public function update(Request $request, $id)
    {
        $operadorId = session('operador_id');

        $operador = Operador::findOrFail($id);

        $request->merge([
            'TelefonoDomicilio' => $request->TelefonoDomicilio !== null && $request->TelefonoDomicilio !== ''
                ? (string) $request->TelefonoDomicilio : null,
            'NumeroCelular' => $request->NumeroCelular !== null && $request->NumeroCelular !== ''
                ? (string) $request->NumeroCelular : null,
        ]);

        $request->validate([
            'IdIdentificador'   => 'required|exists:todos_identificador,IdIdentificador',
            'Iniciales'         => 'required|string|max:5',
            'Clave'             => 'nullable|string|min:4|max:15',
            'NombreAcceso'      => 'required|string|max:20',
            'DireccionDomicilio'=> 'nullable|string',
            'TelefonoDomicilio' => 'nullable|string|max:20',
            'NumeroCelular'     => 'nullable|string|max:20',
            'IdSucursalDB'      => 'required|exists:todos_operador_sucursaldb,IdSucursalDB',
            'Ciudad'            => 'nullable|boolean',
            'Provincia'         => 'nullable|boolean',
            'Destino'           => 'nullable|string|max:150',
        ]);

        try {
            DB::beginTransaction();

            // 1. Datos personales del operador
            $datos = [
                'IdIdentificador'   => $request->IdIdentificador,
                'Iniciales'         => strtoupper($request->Iniciales),
                'NombreAcceso'      => $request->NombreAcceso,
                'DireccionDomicilio'=> $request->DireccionDomicilio,
                'TelefonoDomicilio' => $request->TelefonoDomicilio,
                'NumeroCelular'     => $request->NumeroCelular,
            ];
            if ($request->filled('Clave')) {
                $datos['Clave'] = $request->Clave;
            }
            $operador->update($datos);

            // 2. Solo la asignación específica que se está editando
            $asignacion = OperadorSucursalDb::where('IdSucursalDB', $request->IdSucursalDB)
                ->where('IdOperador', $operador->IdOperador)
                ->firstOrFail();

            // 3. Config de esa asignación
            OperadorPedidoCliente::updateOrCreate(
                ['IdSucursalDB' => $asignacion->IdSucursalDB],
                [
                    'IdOperador' => $operador->IdOperador,
                    'IdCliente'  => $asignacion->IdCliente, // viene de la asignación, no de session
                    'Ciudad'     => $request->boolean('Ciudad') ? 1 : 0, // 🔥 Cambiado
                    'Provincia'  => $request->boolean('Provincia') ? 1 : 0, // 🔥 Cambiado
                    'Destino'    => $request->Destino,
                    'ActivoInactivo'      => 1,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza'      => now('America/La_Paz'),
                ]
            );

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => 'Operador actualizado correctamente',
                'operador' => $operador->load(['identificador', 'tipo']),
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
     * Cambiar estado activo/inactivo del operador.
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
                'estado'  => $nuevoEstado,
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
     * Desactivar operador (soft delete).
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