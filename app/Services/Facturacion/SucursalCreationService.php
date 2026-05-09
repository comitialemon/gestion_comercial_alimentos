<?php
// app/Services/Facturacion/SucursalCreationService.php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SucursalCreationService
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    /**
     * CREAR sucursal desde cero (en gestión y facturación)
     */
    public function crearSucursal(array $data): array
    {
        $required = ['db', 'idCliente', 'numero', 'nombre', 'direccion', 'idPlaza', 'telefono', 'celular', 'categoria'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Falta el campo: {$field}"];
            }
        }

        $dbName = $data['db'];
        
        Log::info('=== CREANDO SUCURSAL DESDE CERO ===', [
            'db' => $dbName,
            'cliente_gestion' => $data['idCliente'],
            'numero' => $data['numero'],
            'nombre' => $data['nombre']
        ]);
        
        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // =============================================
            // 1. CREAR EN GESTIÓN (todos_cliente_sucursal)
            // =============================================
            
            // Obtener el máximo IdClienteSucursal
            $maxId = DB::connection('mysql_gestion_comercial_alimentos')
                ->table(DB::raw("`{$dbName}`.`todos_cliente_sucursal`"))
                ->max('IdClienteSucursal');
            
            $newId = ($maxId ?? 0) + 1;
            
            $insertData = [
                'IdClienteSucursal' => $newId,
                'IdCliente' => $data['idCliente'],
                'IdPlaza' => $data['idPlaza'],
                'Nombre' => $data['nombre'],
                'Direccion' => $data['direccion'],
                'Telefono' => $data['telefono'],
                'Celular' => $data['celular'],
                'NumeroSucursal' => $data['numero'],
                'ActivoInactivo' => $data['activoInactivo'] ? 1 : 0,
                'Categoria' => $data['categoria'],
                'Orden' => $data['orden'] ?? 0,
                'facturacion_habilitada' => 1,
            ];
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table(DB::raw("`{$dbName}`.`todos_cliente_sucursal`"))
                ->insert($insertData);
            
            $sucursalGestionId = $newId;
            Log::info('✅ Sucursal creada en gestión', ['id' => $sucursalGestionId]);

            // =============================================
            // 2. RESOLVER EMPRESA EN FACTURACIÓN
            // =============================================
            
            // Buscar empresa por NIT del cliente
            $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table(DB::raw("`{$dbName}`.`todos_cliente`"))
                ->where('IdCliente', $data['idCliente'])
                ->first();
            
            if (!$cliente) {
                throw new \Exception('Cliente no encontrado en gestión');
            }
            
            // Buscar empresa en facturación por NIT
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('empresa')
                ->where('nit', (string)$cliente->NIT)
                ->first();
            
            if (!$empresa) {
                throw new \Exception('No se encontró empresa en facturación para el NIT: ' . $cliente->NIT);
            }

            // =============================================
            // 3. CREAR EN FACTURACIÓN (vía API)
            // =============================================
            
            $resultado = $this->facturacionApi->crearSucursal([
                'idEmpresa' => $empresa->idEmpresa,
                'nombre' => $data['nombre'],
                'codigo' => $data['numero'],
                'direccion' => $data['direccion'],
                'idMunicipio' => $data['idMunicipio'] ?? null,
                'idClienteSucursalGestion' => $sucursalGestionId,
                'nombreBaseDatos' => $dbName,
            ]);

            if (!($resultado['success'] ?? false)) {
                throw new \Exception($resultado['message'] ?? 'Error en facturación');
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return [
                'success' => true,
                'message' => 'Sucursal creada correctamente en Gestión y Facturación',
                'data' => $resultado['data'] ?? []
            ];

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            
            Log::error('❌ Error creando sucursal', [
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