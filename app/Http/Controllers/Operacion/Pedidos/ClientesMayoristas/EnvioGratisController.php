<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteEnvioGratis;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoCliente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EnvioGratisController extends Controller
{
    // ============================================================
    // VISTA PRINCIPAL
    // ============================================================
    public function index(Request $request)
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        // Grupos disponibles para el filtro
        $grupos = GrupoCliente::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->orderBy('Nombre')
            ->get(['IdGrupoCliente', 'Nombre']);

        return Inertia::render('Operacion/ClientesMayoristas/EnviosGratis/Index', [
            'grupos'       => $grupos,
            'grupoFiltro'  => $request->query('grupo'), // deep link ?grupo=ID
            'diasSemana'   => PedidoClienteEnvioGratis::dias(),
        ]);
    }

    // ============================================================
    // LISTADO DE CLIENTES CON SUS DÍAS GRATIS (AJAX) — SOLO CIUDAD
    // ============================================================
    public function getClientes(Request $request)
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $buscar    = trim($request->get('q', ''));
        $grupoId   = $request->get('grupo');

        try {
            // 1. Clientes (operadores PedidoClientes) SOLO CIUDAD
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador as i')
                ->join('todos_operador as o', 'i.IdIdentificador', '=', 'o.IdIdentificador')
                ->join('todos_operador_tipo as ot', 'o.IdOperadorTipo', '=', 'ot.IdOperadorTipo')
                ->join('todos_operador_sucursaldb as osd', 'osd.IdOperador', '=', 'o.IdOperador')
                // 🔥 NUEVO: JOIN a la config de la operadora para filtrar zona Ciudad
                ->join('operacion_pedidos_operadores_clientes as opc', 'opc.IdOperador', '=', 'o.IdOperador')
                ->where('ot.Detalle', 'PedidoClientes')
                ->where('o.ActivoInactivo', 0)
                ->where('osd.IdCliente', $clienteId)
                ->where('osd.IdSucursal', $sucursalId)
                ->where('opc.IdCliente', $clienteId)
                ->where('opc.Ciudad', 1); // 🔥 SOLO CIUDAD

            // Filtro por búsqueda
            if (strlen($buscar) >= 2) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('i.Nombre', 'LIKE', "%{$buscar}%")
                    ->orWhere('i.CI_NIT', 'LIKE', "%{$buscar}%");
                });
            }

            // Filtro por grupo
            if ($grupoId) {
                $idsEnGrupo = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('operacion_pedidos_grupos_clientes_detalle')
                    ->where('IdGrupoCliente', $grupoId)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->pluck('IdIdentificador')
                    ->toArray();

                if (empty($idsEnGrupo)) {
                    return response()->json(['success' => true, 'data' => []]);
                }

                $query->whereIn('i.IdIdentificador', $idsEnGrupo);
            }

            $clientes = $query
                ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
                ->orderBy('i.Nombre')
                ->distinct()
                ->limit(200)
                ->get();

            if ($clientes->isEmpty()) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $idsIdentificadores = $clientes->pluck('IdIdentificador')->toArray();

            // 2. Días gratis guardados (agrupados por identificador)
            $diasGuardados = PedidoClienteEnvioGratis::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->whereIn('IdIdentificador', $idsIdentificadores)
                ->where('ActivoInactivo', 0)
                ->get()
                ->groupBy('IdIdentificador')
                ->map(fn ($items) => $items->pluck('DiaSemana')->map(fn ($d) => (int) $d)->values()->toArray());

            // 3. Armar resultado
            $resultado = $clientes->map(function ($c) use ($diasGuardados) {
                return [
                    'IdIdentificador' => $c->IdIdentificador,
                    'Nombre'          => $c->Nombre ?? 'Sin nombre',
                    'CI_NIT'          => $c->CI_NIT ?? '',
                    'DiasGratis'      => $diasGuardados[$c->IdIdentificador] ?? [],
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $resultado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener clientes envíos gratis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // GUARDAR DÍAS GRATIS (BULK)
    // ============================================================
    public function guardar(Request $request)
    {
        $request->validate([
            'clientes'                    => 'required|array',
            'clientes.*.IdIdentificador'  => 'required|integer|exists:todos_identificador,IdIdentificador',
            'clientes.*.DiasGratis'       => 'nullable|array',
            'clientes.*.DiasGratis.*'     => 'integer|in:1,2,3,4,5,6,7',
        ]);

        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $ahora      = Carbon::now('America/La_Paz');

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $totalInsertados = 0;
            $totalEliminados = 0;

            foreach ($request->clientes as $item) {
                $idIdent = (int) $item['IdIdentificador'];
                $dias = array_values(array_unique(array_map('intval', $item['DiasGratis'] ?? [])));
                // Solo días válidos
                $dias = array_values(array_filter($dias, fn ($d) => $d >= 1 && $d <= 7));

                // Borrar los días actuales de este cliente
                $deleted = PedidoClienteEnvioGratis::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdIdentificador', $idIdent)
                    ->delete();
                $totalEliminados += $deleted;

                // Insertar los nuevos
                if (!empty($dias)) {
                    $inserts = [];
                    foreach ($dias as $dia) {
                        $inserts[] = [
                            'IdCliente'           => $clienteId,
                            'IdSucursal'          => $sucursalId,
                            'IdIdentificador'     => $idIdent,
                            'DiaSemana'           => $dia,
                            'ActivoInactivo'      => 0,
                            'IdOperadorInserta'   => $operadorId,
                            'FechaInserta'        => $ahora,
                            'IdOperadorActualiza' => $operadorId,
                            'FechaActualiza'      => $ahora,
                        ];
                    }
                    PedidoClienteEnvioGratis::insert($inserts);
                    $totalInsertados += count($inserts);
                }
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => "Días gratis actualizados (insertados: {$totalInsertados}, eliminados: {$totalEliminados})",
                'resumen' => [
                    'insertados' => $totalInsertados,
                    'eliminados' => $totalEliminados,
                ],
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al guardar días gratis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}