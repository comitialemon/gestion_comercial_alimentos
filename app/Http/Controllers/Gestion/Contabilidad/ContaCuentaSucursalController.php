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

    /**
     * 🔥 GUARDAR ASIGNACIÓN - Usa el nombre que escribe el usuario
     */
    public function store(Request $request)
    {
        $request->validate([
            'IdCuenta' => 'required|exists:conta_cuenta,IdCuenta',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Cuenta' => 'required|string|max:255',  // ← EL NOMBRE QUE ESCRIBE EL USUARIO (ej: "Caja", "Banco")
            'DinamicaCuenta' => 'required|string|max:1|in:D,H',
        ]);

        $clienteId = session('cliente_id');

        // 🔥 VERIFICAR SI YA EXISTE (Cuenta + Sucursal + Dinámica)
        $existe = ContaCuentaSucursal::porContexto()
            ->where('IdCuenta', $request->IdCuenta)
            ->where('IdSucursal', $request->IdSucursal)
            ->where('DinamicaCuenta', strtoupper($request->DinamicaCuenta))
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => "Esta cuenta ya está asignada a esta sucursal con dinámica {$request->DinamicaCuenta}"
            ], 422);
        }

        try {
            // 🔥 GUARDAR EL NOMBRE QUE ESCRIBIÓ EL USUARIO (NO el número de cuenta)
            $asignacion = ContaCuentaSucursal::create([
                'IdCuenta' => $request->IdCuenta,
                'Cuenta' => $request->Cuenta,  // ← LO QUE ESCRIBIÓ EL USUARIO (ej: "Caja", "Banco")
                'Descripcion' => $request->Cuenta,  // ← También guardamos el mismo nombre como descripción
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

    /**
     * 🔥 ACTUALIZAR ASIGNACIÓN
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'DinamicaCuenta' => 'required|string|max:1|in:D,H',
        ]);

        try {
            $asignacion = ContaCuentaSucursal::porContexto()->findOrFail($id);
            
            // 🔥 VERIFICAR QUE NO HAYA DUPLICADO (misma cuenta + sucursal + dinámica)
            $duplicado = ContaCuentaSucursal::porContexto()
                ->where('IdCuenta', $asignacion->IdCuenta)
                ->where('IdSucursal', $asignacion->IdSucursal)
                ->where('DinamicaCuenta', strtoupper($request->DinamicaCuenta))
                ->where('IdCuentaSucursales', '!=', $id)
                ->exists();

            if ($duplicado) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya existe otra asignación para esta cuenta, sucursal y dinámica {$request->DinamicaCuenta}"
                ], 422);
            }

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

    /**
     * 🔥 ELIMINAR ASIGNACIÓN
     */
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

    /**
     * 🔥 SINCRONIZAR - ACTUALIZA EL NOMBRE DE LA CUENTA DESDE LA TABLA ORIGINAL
     */
    public function sincronizar()
    {
        try {
            $clienteId = session('cliente_id');
            
            $asignaciones = ContaCuentaSucursal::porContexto()->get();
            
            $contador = 0;
            
            foreach ($asignaciones as $asignacion) {
                // Obtener el número de cuenta de la tabla original
                $cuentaOriginal = ContaCuenta::porContexto()
                    ->where('IdCuenta', $asignacion->IdCuenta)
                    ->first(['Cuenta', 'Descripcion']);
                
                if ($cuentaOriginal) {
                    // 🔥 ACTUALIZAR EL CAMPO 'Cuenta' CON EL NÚMERO DE LA CUENTA ORIGINAL
                    $asignacion->update([
                        'Cuenta' => $cuentaOriginal->Cuenta,  // ← NÚMERO DE CUENTA (ej: "1-01-01")
                        'Descripcion' => $cuentaOriginal->Descripcion,
                    ]);
                    $contador++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Se sincronizaron {$contador} asignaciones correctamente",
                'total' => $contador
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al sincronizar asignaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar: ' . $e->getMessage()
            ], 500);
        }
    }
}