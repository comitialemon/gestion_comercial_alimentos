<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Fecha;
use App\Models\Gestion\Contabilidad\FactorCambio;
use App\Models\Gestion\Contabilidad\Moneda;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FechaController extends Controller
{
    public function index(Request $request)
    {
        $query = Fecha::with(['factoresCambio.moneda']);

        // Búsqueda por fecha
        if ($request->filled('fecha')) {
            $busqueda = trim($request->fecha);
            
            // Convertir dd/mm/aaaa a YYYY-MM-DD
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $busqueda)) {
                $partes = explode('/', $busqueda);
                $dia = (int)$partes[0];
                $mes = (int)$partes[1];
                $anio = (int)$partes[2];
                $fechaConvertida = "{$anio}-{$mes}-{$dia}";
                $query->whereDate('Fecha', $fechaConvertida);
            }
            // Buscar por año (ej: 2025)
            elseif (preg_match('/^\d{4}$/', $busqueda)) {
                $query->whereYear('Fecha', (int)$busqueda);
            }
            // Buscar por mes/año (ej: 03/2025)
            elseif (preg_match('/^\d{2}\/\d{4}$/', $busqueda)) {
                $partes = explode('/', $busqueda);
                $mes = (int)$partes[0];
                $anio = (int)$partes[1];
                $query->whereMonth('Fecha', $mes)->whereYear('Fecha', $anio);
            }
            // Buscar por día/mes (ej: 26/03)
            elseif (preg_match('/^\d{2}\/\d{2}$/', $busqueda)) {
                $partes = explode('/', $busqueda);
                $dia = (int)$partes[0];
                $mes = (int)$partes[1];
                $query->whereDay('Fecha', $dia)->whereMonth('Fecha', $mes);
            }
            // Búsqueda por texto (fallback)
            else {
                $query->whereRaw("DATE_FORMAT(Fecha, '%d/%m/%Y') LIKE ?", ["%{$busqueda}%"]);
            }
        }

        // Filtro por tipo de cierre
        if ($request->filled('cierre')) {
            switch ($request->cierre) {
                case 'abierta':
                    $query->where('CierreSucursal', 0)->where('CierrePermanente', 0);
                    break;
                case 'cierre_sucursal':
                    $query->where('CierreSucursal', 1);
                    break;
                case 'cierre_permanente':
                    $query->where('CierrePermanente', 1);
                    break;
            }
        }

        $fechas = $query->orderBy('Fecha', 'desc')
            ->paginate(50)
            ->withQueryString();

        $monedas = Moneda::orderBy('Moneda')->get();

        return Inertia::render('Gestion/Todos/Fecha/Index', [
            'fechas' => $fechas,
            'monedas' => $monedas,
            'filtros' => [
                'fecha' => $request->fecha,
                'cierre' => $request->cierre,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'Fecha' => 'required|date|unique:mysql_gestion_comercial_alimentos.todos_fecha,Fecha',
            'ActivoInactivo' => 'boolean',
            'CierreSucursal' => 'boolean',
            'CierrePermanente' => 'boolean',
            'factores' => 'array',
            'factores.*.IdMoneda' => 'required|exists:conta_moneda,IdMoneda',
            'factores.*.FactorCambio' => 'required|numeric|min:0',
        ]);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $fecha = Fecha::create([
                'Fecha' => $request->Fecha,
                'ActivoInactivo' => $request->ActivoInactivo ? 0 : 1,
                'CierreSucursal' => $request->CierreSucursal ? 1 : 0,
                'CierrePermanente' => $request->CierrePermanente ? 1 : 0,
            ]);

            foreach ($request->factores as $factor) {
                FactorCambio::create([
                    'IdFecha' => $fecha->IdFecha,
                    'IdMoneda' => $factor['IdMoneda'],
                    'FactorCambio' => $factor['FactorCambio'],
                ]);
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return redirect()->route('gestion.fechas.index')
                ->with('success', 'Fecha y tipos de cambio creados correctamente');

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al crear fecha: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al crear: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $fecha = Fecha::findOrFail($id);

        $request->validate([
            'Fecha' => 'required|date|unique:mysql_gestion_comercial_alimentos.todos_fecha,Fecha,' . $id . ',IdFecha',
            'ActivoInactivo' => 'boolean',
            'CierreSucursal' => 'boolean',
            'CierrePermanente' => 'boolean',
            'factores' => 'array',
            'factores.*.IdMoneda' => 'required|exists:conta_moneda,IdMoneda',
            'factores.*.FactorCambio' => 'required|numeric|min:0',
        ]);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $fecha->update([
                'Fecha' => $request->Fecha,
                'ActivoInactivo' => $request->ActivoInactivo ? 0 : 1,
                'CierreSucursal' => $request->CierreSucursal ? 1 : 0,
                'CierrePermanente' => $request->CierrePermanente ? 1 : 0,
            ]);

            // Eliminar factores existentes y crear nuevos
            FactorCambio::where('IdFecha', $id)->delete();

            foreach ($request->factores as $factor) {
                FactorCambio::create([
                    'IdFecha' => $id,
                    'IdMoneda' => $factor['IdMoneda'],
                    'FactorCambio' => $factor['FactorCambio'],
                ]);
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return redirect()->route('gestion.fechas.index')
                ->with('success', 'Fecha y tipos de cambio actualizados correctamente');

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al actualizar fecha: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al actualizar: ' . $e->getMessage())
                ->withInput();
        }
    }

}