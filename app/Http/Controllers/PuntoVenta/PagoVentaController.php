<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Contabilidad\MetodoPagoMapeo;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Services\Impuestos\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PagoVentaController extends Controller
{
    protected $ventaService;

    public function __construct(VentaService $ventaService)
    {
        $this->ventaService = $ventaService;
    }

    /**
     * Mostrar formulario de pago
     */
    public function create()
    {
        $ventaId = session('venta_actual_id');
        
        if (!$ventaId) {
            return redirect()->route('ventas.formulario')->with('error', 'No hay una venta activa');
        }
        
        $venta = Venta::with('detalles')->findOrFail($ventaId);
        $deuda = (float) $this->ventaService->getDeuda($ventaId);
        
        $productos = [];
        foreach ($venta->detalles as $detalle) {
            $productos[] = [
                'descripcionLibre' => 'Producto',
                'unidades' => (float) $detalle->unidades,
                'precioUnitario' => (float) $detalle->preciounidades,
                'total' => (float) $detalle->totalbolivianos,
            ];
        }
        
        return Inertia::render('PuntoVenta/PagoVenta', [
            'venta' => $venta,
            'deuda' => $deuda,
            'productos' => $productos,
        ]);
    }

    /**
     * API: Obtener métodos de pago (SOLO los que están en el mapeo)
     */
    public function getMetodosPago()
    {
        // Obtener SOLO los códigos que están en el mapeo con activo = 1
        $mapeos = MetodoPagoMapeo::where('idCliente', session('cliente_id'))
            ->where('idSucursal', session('cliente_sucursal_id'))
            ->where('activo', 1)
            ->get();
        
        $codigosUnicos = $mapeos->pluck('codigo_siat')->unique()->values();
        
        $metodosPago = [];
        
        if ($codigosUnicos->isNotEmpty()) {
            try {
                $response = Http::timeout(10)->get('http://siat-app:80/api/v1/metodos-pago');
                if ($response->successful()) {
                    $data = $response->json();
                    $todosMetodos = isset($data['data']) ? $data['data'] : $data;
                    
                    foreach ($todosMetodos as $metodo) {
                        if (in_array($metodo['codigo'], $codigosUnicos->toArray())) {
                            // 🔥 OBTENER LAS CUENTAS CON SU DESCRIPCIÓN COMPLETA
                            $cuentasRelacionadas = $mapeos->where('codigo_siat', $metodo['codigo'])->map(function($item) {
                                $cuenta = ContaCuenta::find($item->idContaCuenta);
                                return [
                                    'id' => $item->idContaCuenta,
                                    'nombre' => $cuenta->Cuenta ?? 'Cuenta ' . $item->idContaCuenta,
                                    'descripcion' => $cuenta->Descripcion ?? '',  // 🔥 Incluir descripción
                                ];
                            })->values();
                            
                            $metodosPago[] = [
                                'id' => $metodo['codigo'],
                                'codigo' => $metodo['codigo'],
                                'descripcion' => $metodo['descripcion'],
                                'cuentas' => $cuentasRelacionadas,
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error obteniendo métodos de pago: ' . $e->getMessage());
            }
        }
        
        return response()->json($metodosPago);
    }

    public function buscarCliente(Request $request)
    {
        $request->validate(['nit' => 'required|string']);
        
        try {
            // 🔥 Obtener los datos de la sesión de GESTIÓN
            $nitEmisor = session('cliente_nit');  // NIT de la empresa logueada
            $codigoSistema = env('SIAT_CODIGO_SISTEMA', ''); // o de la base de datos
            
            // Buscar el código de sistema de la empresa en facturación (opcional)
            // Podrías tenerlo en una tabla de mapeo
            
            $url = env('FACTURACION_API_URL', 'http://siat-app:80') . '/api/v1/verificar-nit';
            
            // 🔥 Enviar TODOS los datos necesarios
            $response = Http::timeout(15)->post($url, [
                'nit' => $request->nit,                    // NIT a verificar
                'nit_emisor' => $nitEmisor,                // NIT de la empresa
                'codigo_sistema' => $codigoSistema,        // Código de sistema
                'ambiente' => 2,                          // o de tu configuración
                'modalidad' => 1,                         // o de tu configuración
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'existe' => $data['existe'] ?? false,
                    'nombre' => $data['nombre'] ?? null,
                    'mensaje' => $data['mensaje'] ?? ($data['existe'] ? 'NIT VÁLIDO' : 'NIT NO ENCONTRADO')
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Error en la respuesta de Facturación'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error verificando NIT: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Procesar el pago y guardar
     */
    public function store(Request $request)
    {
        $request->validate([
            'venta_id' => 'required|exists:impuestos_ventas,IdVentas',
            'nit' => 'required|string',
            'nombre' => 'required|string',
            'codigo_metodo_pago' => 'required|integer',
            'montos' => 'required|array',
            'monto_total' => 'required|numeric',
        ]);
        
        try {
            // Aquí puedes guardar en impuestos_ventas_liquidacion
            
            session()->forget('venta_actual_id');
            return redirect()->route('oficial.index')->with('success', '✅ Venta procesada exitosamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}