<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\Diario;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdministradorDiarioController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // 🔥 AGREGAR 'fecha' AL WITH
        $query = Diario::porContexto()
            ->with(['tipoDiario', 'sucursal', 'operadorIngreso.identificador', 'fecha'])
            ->where('Contabilizado', 1);

        // Filtro por tipo de diario
        if ($request->filled('tipo_diario')) {
            $query->where('IdTipoDiario', $request->tipo_diario);
        }

        // Filtro por sucursal
        if ($request->filled('sucursal')) {
            $query->where('IdSucursal', $request->sucursal);
        }

        // Filtro por número de diario
        if ($request->filled('numero_diario')) {
            $query->where('NumeroDiario', $request->numero_diario);
        }

        // Filtro por fecha
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereHas('fecha', function($q) use ($request) {
                $q->where('Fecha', '>=', $request->fecha_desde)
                  ->where('Fecha', '<=', $request->fecha_hasta);
            });
        } elseif ($request->filled('fecha')) {
            $query->whereHas('fecha', function($q) use ($request) {
                $q->where('Fecha', $request->fecha);
            });
        }

        $diarios = $query->orderBy('NumeroDiario', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Datos para filtros
        $tiposDiario = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_tipodiario')
            ->orderBy('TipoDiario')
            ->get(['IdTipoDiario as id', 'TipoDiario as nombre']);

        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        return Inertia::render('Gestion/Contabilidad/AdministradorDiario/Index', [
            'diarios' => $diarios,
            'tiposDiario' => $tiposDiario,
            'sucursales' => $sucursales,
            'filtros' => [
                'tipo_diario' => $request->tipo_diario,
                'sucursal' => $request->sucursal,
                'numero_diario' => $request->numero_diario,
                'fecha' => $request->fecha,
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
            ],
            'tieneFiltros' => $request->filled('tipo_diario') || $request->filled('sucursal') || $request->filled('numero_diario') || $request->filled('fecha') || $request->filled('fecha_desde'),
        ]);
    }

    public function reabrir(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $diario = Diario::porContexto()->findOrFail($id);

        if ($diario->Contabilizado == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Este diario ya está abierto'
            ], 422);
        }

        try {
            $diario->update([
                'Contabilizado' => 0,
                'NumeroDiario' => 0,
                'IdOperadorEdita' => $operadorId,
                'FechaEdita' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Diario reabierto correctamente. Ya puede editarlo en el módulo de Diario de Ingresos.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al reabrir diario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al reabrir: ' . $e->getMessage()
            ], 500);
        }
    }
}