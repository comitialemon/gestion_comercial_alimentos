<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\MetodoPagoMapeo;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagoController extends Controller
{
    /**
     * Obtener métodos de pago (CON facturación)
     */
    public function getMetodosPagoConFacturacion()
    {
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
                            $cuentasRelacionadas = $mapeos->where('codigo_siat', $metodo['codigo'])->map(function($item) {
                                $cuenta = ContaCuenta::find($item->idContaCuenta);
                                return [
                                    'id' => $item->idContaCuenta,
                                    'nombre' => $cuenta->Cuenta ?? 'Cuenta ' . $item->idContaCuenta,
                                    'descripcion' => $cuenta->Descripcion ?? '',
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
                Log::error('Error obteniendo métodos de pago: ' . $e->getMessage());
            }
        }
        
        return response()->json($metodosPago);
    }

    /**
     * Obtener conceptos de liquidación (SIN facturación)
     */
    public function getConceptosSinFacturacion()
    {
        $conceptos = VentaLiquidacionConcepto::porContexto()
            ->where('activo', 1)
            ->orderBy('IdConceptoLiquidacion')
            ->get(['IdConceptoLiquidacion as id', 'Concepto as nombre', 'IdCuenta']);

        return response()->json([
            'success' => true,
            'conceptos' => $conceptos
        ]);
    }

    /**
     * Verificar NIT (para facturación)
     */
    public function verificarNit(Request $request)
    {
        $request->validate(['nit' => 'required|string']);
        
        try {
            $nitEmisor = session('cliente_nit');
            $codigoSistema = env('SIAT_CODIGO_SISTEMA', '');
            
            $url = env('FACTURACION_API_URL', 'http://siat-app:80') . '/api/v1/verificar-nit';
            
            $response = Http::timeout(15)->post($url, [
                'nit' => $request->nit,
                'nit_emisor' => $nitEmisor,
                'codigo_sistema' => $codigoSistema,
                'ambiente' => 2,
                'modalidad' => 1,
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
            Log::error('Error verificando NIT: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Procesar pago CON facturación
     */
    public function procesarPagoConFacturacion(Request $request)
    {
        $request->validate([
            'venta_id' => 'required|exists:impuestos_ventas,IdVentas',
            'nit' => 'required|string',
            'nombre' => 'required|string',
            'codigo_metodo_pago' => 'required|integer',
            'montos' => 'required|array',
            'monto_total' => 'required|numeric',
            'tipo_venta' => 'required|string|in:normal,tactil'
        ]);
        
        try {
            // Aquí va la lógica de guardar pago CON facturación
            $this->limpiarSesionVenta($request->tipo_venta);
            return response()->json(['success' => true, 'message' => 'Venta procesada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Buscar identificadores (para venta sin facturación)
     */
    public function buscarIdentificador(Request $request)
    {
        $term = $request->get('q', '');
        
        if (strlen($term) < 2) {
            return response()->json(['success' => true, 'clientes' => []]);
        }
        
        $clientes = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->where(function($query) use ($term) {
                $query->where('CI_NIT', 'like', "%{$term}%")
                    ->orWhere('Nombre', 'like', "%{$term}%");
            })
            ->orderBy('Nombre')
            ->limit(20)
            ->get(['IdIdentificador', 'CI_NIT', 'Nombre']);
        
        return response()->json([
            'success' => true,
            'clientes' => $clientes
        ]);
    }

    /**
     * Limpiar sesión según tipo de venta
     */
    private function limpiarSesionVenta($tipoVenta)
    {
        if ($tipoVenta === 'tactil') {
            session()->forget('venta_tactil_id');
        } else {
            session()->forget('venta_actual_id');
        }
    }
}