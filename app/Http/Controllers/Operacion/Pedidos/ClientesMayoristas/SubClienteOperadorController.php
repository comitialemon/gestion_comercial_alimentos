<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteSubCliente;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubClienteOperadorController extends Controller
{
    /**
     * ✅ Vista principal: lista de subclientes del operador
     */
    public function index()
    {
        $operadorId = session('operador_id');

        PedidoClienteSubCliente::asegurarSubClientePropio($operadorId);

        $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->where('IdOperador', $operadorId)
            ->value('IdIdentificador');

        $subclientes = PedidoClienteSubCliente::porOperador($operadorId)
            ->with('identificador')
            ->orderBy('IdSubClienteOperador', 'desc')
            ->get()
            ->map(function ($sub) use ($identificadorOperador) {
                return [
                    'IdSubClienteOperador' => (int) $sub->IdSubClienteOperador,
                    'IdIdentificador' => (int) $sub->IdIdentificador,
                    'Alias' => $sub->Alias,
                    'Nombre' => $sub->identificador->Nombre ?? 'Sin nombre',
                    'CI_NIT' => $sub->identificador->CI_NIT ?? '',
                    'ActivoInactivo' => $sub->ActivoInactivo,
                    'FechaInserta' => $sub->FechaInserta,
                    'EsPropio' => $sub->IdIdentificador === $identificadorOperador,
                ];
            });

        return Inertia::render('Operacion/ClientesMayoristas/SubClientes/Index', [
            'subclientes' => $subclientes,
        ]);
    }

    /**
     * ✅ API: Obtener subclientes del operador (con caché)
     */
    public function getSubClientes(Request $request)
    {
        $operadorId = session('operador_id');

        PedidoClienteSubCliente::asegurarSubClientePropio($operadorId);

        $subclientes = PedidoClienteSubCliente::obtenerSubClientesDelOperador($operadorId);

        return response()->json([
            'success' => true,
            'subclientes' => $subclientes,
            'idSubClienteOperadorDefault' => PedidoClienteSubCliente::obtenerSubClientePorDefecto($operadorId),
        ]);
    }

    /**
     * ✅ Crear subcliente (agregar identificador existente)
     */
    public function store(Request $request)
    {
        $request->validate([
            'IdIdentificador' => 'required|exists:mysql_gestion_comercial_alimentos.todos_identificador,IdIdentificador',
            'Alias' => 'nullable|string|max:100',
        ]);

        $operadorId = session('operador_id');

        try {
            $existe = PedidoClienteSubCliente::where('IdOperador', $operadorId)
                ->where('IdIdentificador', $request->IdIdentificador)
                ->first();

            if ($existe) {
                if ($existe->ActivoInactivo == 0) {
                    $existe->update([
                        'ActivoInactivo' => 1,
                        'Alias' => $request->Alias ?? $existe->Alias,
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => Carbon::now('America/La_Paz'),
                    ]);

                    // ✅ Invalidar caché
                    PedidoClienteSubCliente::invalidarCacheOperador($operadorId);

                    return response()->json([
                        'success' => true,
                        'message' => 'Subcliente reactivado correctamente',
                        'subcliente' => $existe->load('identificador'),
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Este subcliente ya está registrado.'
                ], 400);
            }

            $subcliente = PedidoClienteSubCliente::create([
                'IdOperador' => $operadorId,
                'IdIdentificador' => $request->IdIdentificador,
                'Alias' => $request->Alias,
                'ActivoInactivo' => 1,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => Carbon::now('America/La_Paz'),
            ]);

            // ✅ Invalidar caché
            PedidoClienteSubCliente::invalidarCacheOperador($operadorId);

            return response()->json([
                'success' => true,
                'message' => 'Subcliente registrado correctamente',
                'subcliente' => $subcliente->load('identificador'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear subcliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar subcliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Actualizar subcliente (alias o estado)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'Alias' => 'nullable|string|max:100',
            'ActivoInactivo' => 'nullable|integer|in:0,1',
        ]);

        $operadorId = session('operador_id');

        try {
            $subcliente = PedidoClienteSubCliente::where('IdOperador', $operadorId)
                ->where('IdSubClienteOperador', $id)
                ->first();

            if (!$subcliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subcliente no encontrado.'
                ], 404);
            }

            $subcliente->update([
                'Alias' => $request->Alias,
                'ActivoInactivo' => $request->ActivoInactivo ?? $subcliente->ActivoInactivo,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            // ✅ Invalidar caché
            PedidoClienteSubCliente::invalidarCacheOperador($operadorId);

            return response()->json([
                'success' => true,
                'message' => 'Subcliente actualizado correctamente',
                'subcliente' => $subcliente->load('identificador'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar subcliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar subcliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Desactivar subcliente (soft delete)
     */
    public function destroy($id)
    {
        $operadorId = session('operador_id');

        try {
            $subcliente = PedidoClienteSubCliente::where('IdOperador', $operadorId)
                ->where('IdSubClienteOperador', $id)
                ->first();

            if (!$subcliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subcliente no encontrado.'
                ], 404);
            }

            $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdIdentificador');

            if ($subcliente->IdIdentificador === $identificadorOperador) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes desactivar tu propio subcliente.'
                ], 400);
            }

            $subcliente->update([
                'ActivoInactivo' => 0,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            // ✅ Invalidar caché
            PedidoClienteSubCliente::invalidarCacheOperador($operadorId);

            return response()->json([
                'success' => true,
                'message' => 'Subcliente desactivado correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar subcliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar subcliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ API: Buscar identificadores disponibles (con FULLTEXT + fallback)
     */
    public function buscarIdentificadores(Request $request)
    {
        $busqueda = trim($request->get('q', ''));

        if (strlen($busqueda) < 2) {
            return response()->json([
                'success' => true,
                'identificadores' => []
            ]);
        }

        $operadorId = session('operador_id');

        // ✅ Cache corto de los IDs ya registrados
        $idsRegistrados = cache()->remember("ids_registrados_{$operadorId}", 60, function () use ($operadorId) {
            return PedidoClienteSubCliente::where('IdOperador', $operadorId)
                ->pluck('IdIdentificador')
                ->toArray();
        });

        // ✅ ¿La búsqueda es numérica?
        $esNumerico = ctype_digit($busqueda);

        try {
            $query = Identificador::query();

            if ($esNumerico) {
                // ✅ Búsqueda por CI/NIT (índice regular, LIKE 'prefijo%')
                $query->where('CI_NIT', 'LIKE', "{$busqueda}%");
            } else {
                // ✅ Búsqueda por Nombre con FULLTEXT (solo Nombre, no CI_NIT)
                $query->whereRaw(
                    "MATCH(Nombre) AGAINST(? IN BOOLEAN MODE)",
                    ["{$busqueda}*"]
                );
            }

            $identificadores = $query
                ->whereNotIn('IdIdentificador', $idsRegistrados)
                ->limit(20)
                ->get(['IdIdentificador', 'Nombre', 'CI_NIT']);

            // ⚠️ Si FULLTEXT no devuelve nada, usar LIKE tradicional
            if ($identificadores->isEmpty() && !$esNumerico) {
                $identificadores = Identificador::where('Nombre', 'LIKE', "%{$busqueda}%")
                    ->whereNotIn('IdIdentificador', $idsRegistrados)
                    ->limit(20)
                    ->get(['IdIdentificador', 'Nombre', 'CI_NIT']);
            }

            return response()->json([
                'success' => true,
                'identificadores' => $identificadores,
            ]);

        } catch (\Exception $e) {
            // ⚠️ Fallback total si algo falla
            Log::warning('Búsqueda fallback a LIKE: ' . $e->getMessage());

            $identificadores = Identificador::where(function ($q) use ($busqueda) {
                    $q->where('Nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('CI_NIT', 'LIKE', "%{$busqueda}%");
                })
                ->whereNotIn('IdIdentificador', $idsRegistrados)
                ->limit(20)
                ->get(['IdIdentificador', 'Nombre', 'CI_NIT']);

            return response()->json([
                'success' => true,
                'identificadores' => $identificadores,
            ]);
        }
    }

    /**
     * ✅ Crear un nuevo Identificador y agregarlo como subcliente
     */
    public function storeIdentificador(Request $request)
    {
        $request->validate([
            'Nombre' => 'required|string|max:150',
            'CI_NIT' => 'nullable|string|max:50',
        ]);

        $operadorId = session('operador_id');

        try {
            DB::beginTransaction();

            $identificador = null;

            // ✅ Verificar si ya existe
            if ($request->filled('CI_NIT')) {
                $existente = Identificador::where('CI_NIT', $request->CI_NIT)->first();

                if ($existente) {
                    $yaEsSubcliente = PedidoClienteSubCliente::where('IdOperador', $operadorId)
                        ->where('IdIdentificador', $existente->IdIdentificador)
                        ->exists();

                    if ($yaEsSubcliente) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Ya tienes este identificador como subcliente.'
                        ], 400);
                    }

                    $identificador = $existente;
                }
            }

            // ✅ Crear identificador con TODOS los campos requeridos
            if (!$identificador) {
                $ahora = Carbon::now('America/La_Paz');

                $identificador = Identificador::create([
                    'Nombre' => $request->Nombre,
                    'CI_NIT' => $request->CI_NIT,
                    'IdOperadorIngreso' => $operadorId,
                    'FechaIngreso' => $ahora,
                    'IdOperadorEdita' => $operadorId,
                    'FechaEdita' => $ahora,
                ]);
            }

            // ✅ Agregar como subcliente
            $subcliente = PedidoClienteSubCliente::create([
                'IdOperador' => $operadorId,
                'IdIdentificador' => $identificador->IdIdentificador,
                'Alias' => null,
                'ActivoInactivo' => 1,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => Carbon::now('America/La_Paz'),
            ]);

            // ✅ Invalidar cachés
            PedidoClienteSubCliente::invalidarCacheOperador($operadorId);
            cache()->forget("ids_registrados_{$operadorId}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Identificador creado y agregado como subcliente',
                'identificador' => $identificador,
                'subcliente' => $subcliente->load('identificador'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear identificador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el identificador: ' . $e->getMessage()
            ], 500);
        }
    }
}