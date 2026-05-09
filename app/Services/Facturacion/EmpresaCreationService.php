<?php
// app/Services/Facturacion/EmpresaCreationService.php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmpresaCreationService
{
    protected $facturacionApiUrl;

    public function __construct()
    {
        $this->facturacionApiUrl = env('FACTURACION_API_URL', 'http://siat-app:80');
    }

    /**
     * Crea una empresa en Gestión (todos_cliente) y en Facturación (empresa + mapeo)
     */
    public function crearEmpresa(array $data): array
    {
        $required = ['nombre', 'nit', 'modalidad', 'ambiente', 'token', 'codigo_sistema', 'id_fecha', 'db'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Falta el campo: {$field}"];
            }
        }

        $dbName = $data['db'];
        
        Log::info('=== CREANDO EMPRESA EN GESTIÓN Y FACTURACIÓN ===', [
            'db' => $dbName,
            'nombre' => $data['nombre'],
            'nit' => $data['nit']
        ]);
        
        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // =============================================
            // 1. CREAR EN GESTIÓN (todos_cliente)
            // =============================================
            
            // Obtener el máximo IdCliente
            $maxId = DB::connection('mysql_gestion_comercial_alimentos')
                ->table(DB::raw("`{$dbName}`.`todos_cliente`"))
                ->max('IdCliente');
            
            $newId = ($maxId ?? 0) + 1;
            
            Log::info('ID calculado para gestión', ['max_id' => $maxId, 'new_id' => $newId]);
            
            // Preparar datos para INSERT
            $insertData = [
                'IdCliente' => $newId,
                'Nombre' => $data['nombre'],
                'NIT' => (int) $data['nit'],
                'Direccion' => $data['direccion'] ?? null,
                'Fono' => isset($data['fono']) ? (int) $data['fono'] : null,
                'Celular' => isset($data['celular']) ? (int) $data['celular'] : null,
                'CIRepresentanteLegal' => isset($data['ci_rep']) ? (int) $data['ci_rep'] : 0,
                'NombreRepresentanteLegal' => $data['rep'] ?? 'SIN REGISTRO',
                'IdFechaInicioOperaciones' => (int) $data['id_fecha'],
                'facturacion_habilitada' => 1,
            ];
            
            Log::info('Insertando en todos_cliente', $insertData);
            
            $inserted = DB::connection('mysql_gestion_comercial_alimentos')
                ->table(DB::raw("`{$dbName}`.`todos_cliente`"))
                ->insert($insertData);
            
            if (!$inserted) {
                throw new \Exception('No se pudo insertar el cliente en gestión');
            }
            
            $clienteId = $newId;
            
            Log::info('✅ Cliente creado en gestión', ['cliente_id' => $clienteId]);

            // =============================================
            // 2. CREAR EN FACTURACIÓN (empresa + mapeo)
            // =============================================
            
            Log::info('📤 Enviando a facturación', [
                'url' => $this->facturacionApiUrl . '/api/v1/facturacion/empresas',
                'idClienteGestion' => $clienteId
            ]);
            
            $response = Http::timeout(30)->post($this->facturacionApiUrl . '/api/v1/facturacion/empresas', [
                'nombre' => $data['nombre'],
                'nit' => $data['nit'],
                'modalidad' => (int) $data['modalidad'],
                'ambiente' => (int) $data['ambiente'],
                'token' => $data['token'],
                'codigo_sistema' => $data['codigo_sistema'],
                'idClienteGestion' => $clienteId,
            ]);

            Log::info('📥 Respuesta de facturación', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Error en facturación: ' . $response->body());
            }

            $resultado = $response->json();
            
            if (!($resultado['success'] ?? false)) {
                throw new \Exception($resultado['message'] ?? 'Error desconocido en facturación');
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return [
                'success' => true,
                'message' => 'Empresa creada correctamente en Gestión y Facturación',
                'data' => [
                    'idClienteGestion' => $clienteId,
                    'idEmpresaFacturacion' => $resultado['data']['idEmpresa'] ?? null,
                ]
            ];

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            
            Log::error('❌ Error creando empresa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}