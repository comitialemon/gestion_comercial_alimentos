<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LugarVenta;
use App\Models\Gestion\Impuestos\Comisionista;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class NuevaVentaTactilController extends Controller
{
    /**
     * Muestra el formulario de selección de lugar de venta y comisionista
     */
    public function create()
    {
        // Verificar si hay una venta activa para este operador
        $ventaActiva = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', session('cliente_id'))
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->where('IdOperadorIngresa', session('operador_id'))
            ->where('ActivoInactivo', 0)
            ->first();
        
        if ($ventaActiva) {
            session([
                'venta_tactil_id' => $ventaActiva->IdVentas,
                'venta_tactil_lugar_id' => $ventaActiva->LugarVenta,
                'venta_tactil_comisionista_id' => $ventaActiva->IdComisionista,
            ]);
            
            $comisionista = Comisionista::with('identificador')
                ->find($ventaActiva->IdComisionista);
                
            if ($comisionista) {
                session(['venta_tactil_comisionista_identificador' => $comisionista->IdIdentificador]);
            }
            
            return redirect()->route('venta-tactil.index')
                ->with('info', 'Reanudando venta en progreso');
        }
        
        $lugaresVenta = LugarVenta::porContexto()
            ->orderBy('Orden')
            ->get(['IdLugar as id', 'Lugar as nombre']);

        $comisionistas = Comisionista::porContexto()
            ->with('identificador')
            ->orderBy('IdComisionista')
            ->get()
            ->map(fn($c) => [
                'id' => $c->IdComisionista,
                'nombre' => $c->identificador->Nombre ?? 'Sin nombre',
                'idIdentificador' => $c->IdIdentificador,
            ]);

        return Inertia::render('PuntoVenta/NuevaVentaTactil', [
            'lugaresVenta' => $lugaresVenta,
            'comisionistas' => $comisionistas,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'lugar_venta_id' => 'required|integer|exists:impuestos_ventas_lugar_venta,IdLugar',
            'comisionista_id' => 'required|integer|exists:impuestos_ventas_comisionitas,IdComisionista',
        ]);

        $comisionista = Comisionista::with('identificador')
            ->findOrFail($request->comisionista_id);

        // Crear venta pendiente - Guardamos el ID del lugar, luego se reemplaza con el nombre al pagar
        $ventaId = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->insertGetId([
                'IdCliente' => session('cliente_id'),
                'IdClienteSucursal' => session('cliente_sucursal_id'),
                'IdOperadorIngresa' => session('operador_id'),
                'IdOperadorActualiza' => session('operador_id'),
                'FechaVenta' => now()->format('Y-m-d H:i:s'),
                'LugarVenta' => $request->lugar_venta_id,  // Guarda el ID temporalmente
                'IdComisionista' => $request->comisionista_id,
                'IdNIT' => $comisionista->IdIdentificador,
                'IdEstado' => 0,  // 0 = Pendiente
                'ActivoInactivo' => 0,
                'NumeroFactura' => 0,
                'NumeroAutorizacion' => '0',
                'CodigoControl' => '0',
                'ImporteVenta' => 0,
                'ImporteExcento' => 0,
                'ImporteExportaciones' => 0,
                'ImporteTasaCero' => 0,
                'ImporteDescuentos' => 0,
                'TicketDia' => 0,
                'Entrega' => 0,
                'LiquidadoVendedor' => 0,
                'Observacion' => '',
            ]);

        session([
            'venta_tactil_id' => $ventaId,
            'venta_tactil_lugar_id' => $request->lugar_venta_id,
            'venta_tactil_comisionista_id' => $request->comisionista_id,
            'venta_tactil_comisionista_identificador' => $comisionista->IdIdentificador,
        ]);

        return redirect()->route('venta-tactil.index')
            ->with('success', 'Venta iniciada. Selecciona productos.');
    }
}