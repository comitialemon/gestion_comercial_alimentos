<?php
// app/Services/Facturacion/FacturacionApiService.php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FacturacionApiService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('FACTURACION_API_URL', 'http://siat-app:80');
    }

    /**
     * Obtener parámetros comunes para todas las peticiones SIAT
     */
    private function getSiatParams()
    {
        $empresaId = session('empresa_id_facturacion');
        $empresa = null;
        
        if ($empresaId) {
            try {
                $empresa = DB::connection('facturacion')
                    ->table('empresa')
                    ->where('idEmpresa', $empresaId)
                    ->first();
            } catch (\Exception $e) {
                Log::error('Error obteniendo empresa de facturación', ['error' => $e->getMessage()]);
            }
        }
        
        return array_filter([
            'nit_emisor' => session('cliente_nit'),
            'empresa_id' => $empresaId,
            'sucursal_id' => session('sucursal_id_facturacion'),
            'punto_venta_id' => session('punto_venta_id'),
            'token' => $empresa->token ?? null,
            'codigo_sistema' => $empresa->codigo_sistema ?? null,
            'ambiente' => $empresa->ambiente ?? null,
            'modalidad' => $empresa->modalidad ?? null,
            'nombre' => $empresa->nombre ?? null,
            'razon_social' => $empresa->razon_social ?? null,
        ]);
    }
    //==== METODOS PAGO ======
    public function getMetodosPago()
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/metodos-pago');
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data'])) {
                    return $data['data'];
                }
                return $data;
            }
            
            Log::error('Error API metodos-pago: ' . $response->status());
            return [];
        } catch (\Exception $e) {
            Log::error('Error API metodos-pago: ' . $e->getMessage());
            return [];
        }
    }

    // ==================== SIAT - CUIS ====================
    public function getCuisVigente()
    {
        try {
            $nitEmisor = session('cliente_nit');
            
            if (!$nitEmisor) {
                return [
                    'success' => false,
                    'message' => 'No hay empresa seleccionada',
                    'data' => null
                ];
            }
            
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/siat/cuis/vigente', [
                'nit_emisor' => $nitEmisor
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'message' => 'Error al obtener CUIS: ' . $response->status(),
                'data' => null
            ];
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo CUIS vigente', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function solicitarCuis()
    {
        try {
            $nitEmisor = session('cliente_nit');
            
            if (!$nitEmisor) {
                return [
                    'success' => false,
                    'message' => 'No hay empresa seleccionada'
                ];
            }
            
            $response = Http::timeout(60)->post($this->baseUrl . '/api/v1/siat/cuis', [
                'nit_emisor' => $nitEmisor
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'message' => 'Error al solicitar CUIS: ' . $response->status()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error solicitando CUIS', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getCuisHistorial()
    {
        try {
            $nitEmisor = session('cliente_nit');
            
            if (!$nitEmisor) {
                return [
                    'success' => false,
                    'data' => []
                ];
            }
            
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/siat/cuis/historial', [
                'nit_emisor' => $nitEmisor
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'data' => []
            ];
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo historial CUIS', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'data' => []
            ];
        }
    }

    // ==================== SIAT - CUFD ====================

    public function getCufdVigente()
    {
        try {
            $nitEmisor = session('cliente_nit');
            $puntoVentaId = session('punto_venta_id');
            
            if (!$nitEmisor) {
                return [
                    'success' => false,
                    'message' => 'No hay empresa seleccionada',
                    'data' => null
                ];
            }
            
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/siat/cufd/vigente', [
                'nit_emisor' => $nitEmisor,
                'punto_venta_id' => $puntoVentaId
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'message' => 'Error al obtener CUFD: ' . $response->status(),
                'data' => null
            ];
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo CUFD vigente', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function solicitarCufd()
    {
        try {
            $nitEmisor = session('cliente_nit');
            $puntoVentaId = session('punto_venta_id');
            
            if (!$nitEmisor) {
                return [
                    'success' => false,
                    'message' => 'No hay empresa seleccionada'
                ];
            }
            
            $response = Http::timeout(60)->post($this->baseUrl . '/api/v1/siat/cufd', [
                'nit_emisor' => $nitEmisor,
                'punto_venta_id' => $puntoVentaId
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'message' => 'Error al solicitar CUFD: ' . $response->status()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error solicitando CUFD', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getCufdHistorial()
    {
        try {
            $nitEmisor = session('cliente_nit');
            $puntoVentaId = session('punto_venta_id');
            
            if (!$nitEmisor) {
                return [
                    'success' => false,
                    'data' => []
                ];
            }
            
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/siat/cufd/historial', [
                'nit_emisor' => $nitEmisor,
                'punto_venta_id' => $puntoVentaId
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'data' => []
            ];
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo historial CUFD', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'data' => []
            ];
        }
    }
    // ==================== SIAT - CATÁLOGOS ====================
    public function getCatalogosStatus()
    {
        try {
            $nitEmisor = session('cliente_nit');
            
            if (!$nitEmisor) {
                return ['error' => true, 'message' => 'No hay empresa seleccionada'];
            }
            
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/siat/catalogos/status', [
                'nit_emisor' => $nitEmisor
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['error' => true, 'message' => 'Error al obtener status: ' . $response->status()];
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo status catálogos', ['error' => $e->getMessage()]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function sincronizarCatalogos()
    {
        try {
            $params = $this->getSiatParams();
            
            Log::info('📡 Sincronizando todos los catálogos', $params);
            
            $response = Http::timeout(120)->post($this->baseUrl . '/api/v1/siat/catalogos/sync', $params);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('Error sincronizando catálogos', ['status' => $response->status(), 'body' => $response->body()]);
            return ['error' => true, 'message' => $response->body()];
            
        } catch (\Exception $e) {
            Log::error('Error sincronizando catálogos', ['error' => $e->getMessage()]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function sincronizarCatalogo($key)
    {
        try {
            $params = $this->getSiatParams();
            
            Log::info("📡 Sincronizando catálogo: {$key}", $params);
            
            $response = Http::timeout(60)->post($this->baseUrl . '/api/v1/siat/catalogos/sync/' . $key, $params);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('Error sincronizando catálogo', ['key' => $key, 'status' => $response->status()]);
            return ['error' => true, 'message' => $response->body()];
            
        } catch (\Exception $e) {
            Log::error('Error sincronizando catálogo', ['key' => $key, 'error' => $e->getMessage()]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function pingFechaHora()
    {
        try {
            $params = $this->getSiatParams();
            
            Log::info('📡 Probando fecha/hora con SIAT', $params);
            
            $response = Http::timeout(30)->post($this->baseUrl . '/api/v1/siat/ping', $params);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('Error en ping fecha/hora', ['status' => $response->status()]);
            return ['error' => true, 'message' => $response->body()];
            
        } catch (\Exception $e) {
            Log::error('Error en ping fecha/hora', ['error' => $e->getMessage()]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
    // ==================== EMPRESAS ====================
    // app/Services/Facturacion/FacturacionApiService.php

    public function crearEmpresa($data)
    {
        try {
            // 🔥 Facturación SOLO recibe datos, NO se conecta a gestión
            $payload = [
                'db' => $data['nombreBaseDatos'] ?? $data['db'] ?? '',  // Solo referencia
                'nombre' => $data['nombre'],
                'nit' => $data['nit'],
                'modalidad' => (int) $data['modalidad'],
                'ambiente' => (int) $data['ambiente'],
                'direccion' => $data['direccion'] ?? null,
                'fono' => $data['fono'] ?? null,
                'celular' => $data['celular'] ?? null,
                'ci_rep' => $data['ci_rep'] ?? null,
                'rep' => $data['rep'] ?? null,
                'token' => $data['token'],
                'codigo_sistema' => $data['codigo_sistema'],
                'idClienteGestion' => $data['idClienteGestion'] ?? 0,   // ← Para mapeo
                'nombreBaseDatos' => $data['nombreBaseDatos'] ?? '',     // ← Para referencia
                'id_fecha' => $data['id_fecha'] ?? 0,
            ];
            
            $url = $this->baseUrl . '/api/v1/facturacion/empresas';
            
            \Log::info('📤 Creando empresa en facturación', [
                'url' => $url,
                'payload' => $payload
            ]);
            
            $response = Http::timeout(60)->post($url, $payload);
            
            \Log::info('📥 Respuesta crear empresa', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false, 
                'message' => 'Error HTTP: ' . $response->status() . ' - ' . $response->body()
            ];
            
        } catch (\Exception $e) {
            \Log::error('❌ Error creando empresa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false, 
                'message' => $e->getMessage()
            ];
        }
    }

    public function getEmpresas()
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/facturacion/empresas');
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['success' => false, 'message' => 'Error al obtener empresas', 'data' => []];
        } catch (\Exception $e) {
            Log::error('Error obteniendo empresas', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    public function importarEmpresa($data)
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . '/api/v1/facturacion/empresas/importar', $data);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'message' => 'Error HTTP: ' . $response->status() . ' - ' . $response->body()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ==================== SUCURSALES ====================

    public function getSucursales($idEmpresa)
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/facturacion/sucursales', [
                'idEmpresa' => $idEmpresa
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['success' => false, 'message' => 'Error al obtener sucursales', 'data' => []];
        } catch (\Exception $e) {
            Log::error('Error obteniendo sucursales', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    public function crearSucursal($data)
    {
        try {
            Log::info('📤 Creando sucursal en facturación', $data);
            
            $response = Http::timeout(30)->post($this->baseUrl . '/api/v1/facturacion/sucursales', $data);
            
            $responseData = $response->json();
            
            Log::info('📥 Respuesta de facturación', [
                'status' => $response->status(),
                'body' => $responseData
            ]);
            
            if ($response->successful() && ($responseData['success'] ?? false)) {
                return $responseData;
            }
            
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Error HTTP: ' . $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error en crearSucursal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function importarSucursal($data)
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . '/api/v1/facturacion/sucursales/importar', $data);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [
                'success' => false,
                'message' => 'Error HTTP: ' . $response->status() . ' - ' . $response->body()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getMunicipios()
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/facturacion/municipios');
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return ['success' => false, 'data' => []];
        } catch (\Exception $e) {
            Log::error('Error obteniendo municipios', ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => []];
        }
    }
    /**
     * Crear sucursal en gestión
     */
    public function crearSucursalEnGestion(array $data): array
    {
        try {
            Log::info('=== CREANDO SUCURSAL EN GESTIÓN ===', [
                'db' => $data['db'],
                'idCliente' => $data['idCliente'],
                'nombre' => $data['nombre'],
                'numero' => $data['numero']
            ]);
            
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            
            // Cambiar a la base seleccionada
            $connection->statement("USE `{$data['db']}`");
            
            // 🔥 PAYLOAD COMPLETO con TODOS los campos obligatorios
            $payload = [
                'IdCliente' => $data['idCliente'],
                'IdPlaza' => $data['idPlaza'],
                'Nombre' => $data['nombre'],
                'Direccion' => $data['direccion'],
                'Telefono' => $data['telefono'] ?? '',
                'Celular' => $data['celular'] ?? '',
                'NumeroSucursal' => $data['numero'],
                'ActivaInactivaR' => 0,  // ← Campo faltante
                'Orden' => $data['orden'] ?? 0,
                'Categoria' => $data['categoria'] ?? '',
                'ActivoInactivo' => $data['activoInactivo'] ? 0 : 1,
                'ControlInternoEfectivo' => 0,  // ← Campo faltante
                'facturacion_habilitada' => 1,
            ];
            
            Log::info('Insertando en todos_cliente_sucursal', $payload);
            
            // Insertar
            $id = $connection->table('todos_cliente_sucursal')->insertGetId($payload, 'IdClienteSucursal');
            
            // Volver a la base original
            $connection->statement("USE `gestion_comercialalimentos`");
            
            Log::info('✅ Sucursal creada en gestión', ['id' => $id]);
            
            return ['success' => true, 'id' => $id];
            
        } catch (\Exception $e) {
            Log::error('❌ Error creando sucursal en gestión', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Asegurar volver a la base original
            try {
                DB::connection('mysql_gestion_comercial_alimentos')->statement("USE `gestion_comercialalimentos`");
            } catch (\Exception $ignore) {}
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}