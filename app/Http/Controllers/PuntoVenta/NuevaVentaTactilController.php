<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LugarVenta;
use App\Models\Gestion\Impuestos\Comisionista;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class NuevaVentaTactilController extends Controller
{
    public function create()
    {
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

        // Obtener NIT de la empresa (cliente logueado)
        $clienteNit = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->value('NIT');

        $comisionistas = Comisionista::porContexto()
            ->with('identificador')
            ->orderBy('IdComisionista')
            ->get()
            ->map(fn($c) => [
                'id' => $c->IdComisionista,
                'nombre' => $c->identificador->Nombre ?? 'Sin nombre',
                'idIdentificador' => $c->IdIdentificador,
                'nit' => $c->identificador->CI_NIT ?? null,
                'esCliente' => ($c->identificador->CI_NIT ?? null) == $clienteNit,
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

        // Obtener NIT de la empresa (cliente logueado)
        $clienteNit = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->value('NIT');

        // Determinar el IdNIT para la venta
        // Si el comisionista tiene el mismo NIT que el cliente, usar 0 (sin NIT)
        // Sino, usar el IdIdentificador del comisionista
        $idNIT = $comisionista->IdIdentificador;
        if ($comisionista->identificador && $comisionista->identificador->CI_NIT == $clienteNit) {
            // Buscar o crear identificador con NIT 0
            $identificadorCero = Identificador::where('CI_NIT', 0)->first();
            if (!$identificadorCero) {
                $identificadorCero = Identificador::create([
                    'CI_NIT' => 0,
                    'Nombre' => 'SIN NIT',
                    'IdOperadorIngreso' => session('operador_id'),
                    'FechaIngreso' => now(),
                    'IdOperadorEdita' => session('operador_id'),
                    'FechaEdita' => now(),
                ]);
            }
            $idNIT = $identificadorCero->IdIdentificador;
        }

        $ventaId = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->insertGetId([
                'IdCliente' => session('cliente_id'),
                'IdClienteSucursal' => session('cliente_sucursal_id'),
                'IdOperadorIngresa' => session('operador_id'),
                'IdOperadorActualiza' => session('operador_id'),
                'FechaVenta' => now()->format('Y-m-d H:i:s'),
                'LugarVenta' => $request->lugar_venta_id,
                'IdComisionista' => $request->comisionista_id,
                'IdNIT' => $idNIT,
                'IdEstado' => 0,
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
            'venta_tactil_cliente_es_comisionista' => ($comisionista->identificador->CI_NIT ?? null) == $clienteNit,
        ]);

        return redirect()->route('venta-tactil.index')
            ->with('success', 'Venta iniciada. Selecciona productos.');
    }
}