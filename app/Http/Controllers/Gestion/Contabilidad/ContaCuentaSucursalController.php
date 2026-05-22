<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Contabilidad\ContaCuentaSucursal;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContaCuentaSucursalController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');

        $asignaciones = ContaCuentaSucursal::porContexto()
            ->with(['cuenta', 'sucursal'])
            ->orderBy('Cuenta')
            ->get();

        $cuentas = ContaCuenta::porContexto()
            ->where('AbiertoCerrado', 0)
            ->orderBy('Cuenta')
            ->get(['IdCuenta as id', 'Cuenta', 'Descripcion']);

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);

        return Inertia::render('Gestion/Contabilidad/ContaCuentaSucursal/Index', [
            'asignaciones' => $asignaciones,
            'cuentas' => $cuentas,
            'sucursales' => $sucursales,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'IdCuenta' => 'required|exists:conta_cuenta,IdCuenta',
            'DinamicaCuenta' => 'required|string|max:1',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);

        $clienteId = session('cliente_id');

        $existe = ContaCuentaSucursal::porContexto()
            ->where('IdCuenta', $request->IdCuenta)
            ->where('IdSucursal', $request->IdSucursal)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta ya está asignada a esta sucursal'
            ], 422);
        }

        $cuenta = ContaCuenta::find($request->IdCuenta);

        try {
            $asignacion = ContaCuentaSucursal::create([
                'IdCuenta' => $request->IdCuenta,
                'Cuenta' => $cuenta ? $cuenta->Cuenta : '',
                'DinamicaCuenta' => strtoupper($request->DinamicaCuenta),
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
            ]);

            $asignacion->load(['cuenta', 'sucursal']);

            return response()->json([
                'success' => true,
                'message' => 'Asignación creada correctamente',
                'asignacion' => $asignacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear asignación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'DinamicaCuenta' => 'required|string|max:1',
        ]);

        try {
            $asignacion = ContaCuentaSucursal::porContexto()->findOrFail($id);
            $asignacion->update([
                'DinamicaCuenta' => strtoupper($request->DinamicaCuenta),
            ]);

            $asignacion->load(['cuenta', 'sucursal']);

            return response()->json([
                'success' => true,
                'message' => 'Asignación actualizada correctamente',
                'asignacion' => $asignacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar asignación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $asignacion = ContaCuentaSucursal::porContexto()->findOrFail($id);
            $asignacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Asignación eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}