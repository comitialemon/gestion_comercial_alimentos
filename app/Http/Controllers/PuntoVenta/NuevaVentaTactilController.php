<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LugarVenta;
use App\Models\Gestion\Impuestos\Comisionista;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Inventario\Almacen;
use App\Services\TimezoneService;
use App\Services\Gestion\PuntoVenta\VentaPendienteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;  // ✅ AGREGAR ESTA LÍNEA

class NuevaVentaTactilController extends Controller
{
    protected $timezoneService;
    protected $ventaPendienteService;

    public function __construct(
        TimezoneService $timezoneService,
        VentaPendienteService $ventaPendienteService
    ) {
        $this->timezoneService = $timezoneService;
        $this->ventaPendienteService = $ventaPendienteService;
    }

    /**
     * 🔥 OBTENER FECHA Y HORA CORRECTA DEL CLIENTE
     */
    private function getFechaHoraCliente()
    {
        return $this->timezoneService->getFechaHoraActual();
    }

    public function create()
    {
        // ✅ VERIFICAR SI LA SUCURSAL TIENE ALMACENES
        $sucursalId = session('cliente_sucursal_id');
        $tieneAlmacen = Almacen::where('IdSucursal', $sucursalId)->exists();

        // 🔥 LIMPIAR VENTAS PENDIENTES DE DÍAS ANTERIORES
        $resultado = $this->ventaPendienteService->limpiarVentasPendientes();
        
        if ($resultado['limpiadas'] > 0) {
            Log::info('🧹 Ventas pendientes limpiadas automáticamente', [
                'cantidad' => $resultado['limpiadas']
            ]);
        }

        // ✅ Verificar venta activa (SOLO si hay almacén)
        $ventaActiva = null;
        if ($tieneAlmacen) {
            // 🔥 BUSCAR SOLO VENTAS DEL DÍA ACTUAL
            $ventaActiva = $this->ventaPendienteService->verificarVentaPendienteHoy();
        }
        
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

        // 🔥 FECHA CORRECTA SEGÚN CLIENTE
        $fechaFormateada = $this->timezoneService->formatDate($this->timezoneService->now(), 'd/m/Y');

        return Inertia::render('PuntoVenta/NuevaVentaTactil', [
            'lugaresVenta' => $lugaresVenta,
            'comisionistas' => $comisionistas,
            'fechaFormateada' => $fechaFormateada,
            'tieneAlmacen' => $tieneAlmacen,
        ]);
    }

    public function store(Request $request)
    {
        // ✅ Validar almacén ANTES de cualquier otra cosa
        $sucursalId = session('cliente_sucursal_id');
        $tieneAlmacen = Almacen::where('IdSucursal', $sucursalId)->exists();

        if (!$tieneAlmacen) {
            return redirect()->back()
                ->with('error', '⚠️ Esta sucursal no tiene almacenes configurados. No se puede iniciar la venta.')
                ->withInput();
        }

        $request->validate([
            'lugar_venta_id' => 'required|integer|exists:impuestos_ventas_lugar_venta,IdLugar',
            'comisionista_id' => 'required|integer|exists:impuestos_ventas_comisionitas,IdComisionista',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $comisionista = Comisionista::with('identificador')
            ->findOrFail($request->comisionista_id);

        $clienteNit = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->value('NIT');

        $idNIT = $comisionista->IdIdentificador;
        if ($comisionista->identificador && $comisionista->identificador->CI_NIT == $clienteNit) {
            $identificadorCero = Identificador::where('CI_NIT', 0)->first();
            if (!$identificadorCero) {
                $identificadorCero = Identificador::create([
                    'CI_NIT' => 0,
                    'Nombre' => 'SIN NIT',
                    'IdOperadorIngreso' => $operadorId,
                    'IdOperadorEdita' => $operadorId,
                    'FechaEdita' => $this->getFechaHoraCliente(),
                ]);
            }
            $idNIT = $identificadorCero->IdIdentificador;
        }

        $lugarVentaTexto = 'Mostrador';
        if ($comisionista->identificador && $comisionista->identificador->CI_NIT != $clienteNit) {
            $lugarVentaTexto = 'Mayorista';
        }

        // 🔥 FECHA CORRECTA SEGÚN CLIENTE
        $fechaActual = $this->getFechaHoraCliente();

        $ventaId = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->insertGetId([
                'IdCliente' => $clienteId,
                'IdClienteSucursal' => $sucursalId,
                'IdOperadorIngresa' => $operadorId,
                'IdOperadorActualiza' => $operadorId,
                'FechaVenta' => $fechaActual,
                'FechaUltimaActualizcion' => $fechaActual,
                'LugarVenta' => $lugarVentaTexto,
                'IdComisionista' => $request->comisionista_id,
                'IdNIT' => $idNIT,
                'IdEstado' => 1,
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