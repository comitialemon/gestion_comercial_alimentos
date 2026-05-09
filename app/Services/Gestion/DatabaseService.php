<?php
// app/Services/Gestion/DatabaseService.php

namespace App\Services\Gestion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseService
{
    /**
     * Lista todas las bases de datos en el MySQL de Gestión
     * Excluye SOLO las bases del sistema
     */
    public function listarBasesMysql(): array
    {
        try {
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            $results = $connection->select('SHOW DATABASES');
            
            // ✅ Solo excluir bases del sistema
            $excluir = [
                'information_schema',
                'mysql',
                'performance_schema',
                'sys',
            ];
            
            $bases = [];
            foreach ($results as $row) {
                $dbName = $row->Database ?? array_values((array) $row)[0] ?? null;
                
                if ($dbName && !in_array($dbName, $excluir, true)) {
                    $bases[] = $dbName;
                }
            }
            
            Log::info('Bases de datos encontradas', ['bases' => $bases, 'total' => count($bases)]);
            
            return $bases;
            
        } catch (\Exception $e) {
            Log::error('Error listando bases de datos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Lista clientes de una base específica
     */
    public function listarClientesEnBase(string $dbName): array
    {
        try {
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            
            // Cambiar a la base seleccionada
            $connection->statement("USE `{$dbName}`");
            
            $clientes = $connection->table('todos_cliente')
                ->select('IdCliente as id', 'Nombre as nombre', 'NIT as nit')
                ->orderBy('Nombre')
                ->get();
            
            // Volver a la base original
            $connection->statement("USE `gestion_comercialalimentos`");
            
            return $clientes->toArray();
            
        } catch (\Exception $e) {
            Log::error('Error listando clientes en base', [
                'db' => $dbName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Obtener último IdFecha de una base específica
     */
    public function ultimoIdFechaEnBase(string $dbName): array
    {
        try {
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            
            $connection->statement("USE `{$dbName}`");
            
            $row = $connection->table('todos_fecha')
                ->selectRaw('IdFecha as id, Fecha as fecha')
                ->orderByDesc('IdFecha')
                ->first();
            
            $connection->statement("USE `gestion_comercialalimentos`");
            
            if (!$row) {
                return [0, null];
            }
            
            $fechaStr = $this->normalizarFecha($row->fecha);
            return [(int) $row->id, $fechaStr];
            
        } catch (\Exception $e) {
            Log::warning("No se pudo leer todos_fecha en {$dbName}: " . $e->getMessage());
            return [0, null];
        }
    }
    
    /**
     * Normaliza fecha a YYYY-MM-DD
     */
    private function normalizarFecha($fecha): ?string
    {
        if (!$fecha) return null;
        
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        
        $s = (string) $fecha;
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }
        
        try {
            return (new \DateTime($s))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ==================== NUEVOS MÉTODOS PARA SUCURSALES ====================

    /**
     * Lista plazas de una base específica
     */
    public function listarPlazasEnBase(string $dbName): array
    {
        try {
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            
            // Cambiar a la base seleccionada
            $connection->statement("USE `{$dbName}`");
            
            // Verificar si la tabla existe
            $tables = $connection->select("SHOW TABLES LIKE 'todos_cliente_plaza'");
            
            if (empty($tables)) {
                Log::warning("Tabla todos_cliente_plaza no existe en {$dbName}");
                $connection->statement("USE `gestion_comercialalimentos`");
                return [];
            }
            
            $plazas = $connection->table('todos_cliente_plaza')
                ->select('IdPlaza as id', 'Plaza as nombre', 'Abreviacion as abrev')
                ->orderBy('Plaza')
                ->get();
            
            // Volver a la base original
            $connection->statement("USE `gestion_comercialalimentos`");
            
            return $plazas->toArray();
            
        } catch (\Exception $e) {
            Log::error('Error listando plazas en base', [
                'db' => $dbName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener cliente por ID desde una base específica
     */
    public function obtenerClientePorId(string $dbName, int $idCliente)
    {
        try {
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            
            $connection->statement("USE `{$dbName}`");
            
            $cliente = $connection->table('todos_cliente')
                ->select('IdCliente', 'Nombre', 'NIT')
                ->where('IdCliente', $idCliente)
                ->first();
            
            $connection->statement("USE `gestion_comercialalimentos`");
            
            return $cliente;
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo cliente', [
                'db' => $dbName,
                'id' => $idCliente,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtener empresa (cliente) por ID para mapeo
     */
    public function obtenerEmpresaPorCliente(string $dbName, int $idCliente)
    {
        $cliente = $this->obtenerClientePorId($dbName, $idCliente);
        
        if (!$cliente) return null;
        
        return [
            'id' => $cliente->IdCliente,
            'nombre' => $cliente->Nombre,
            'nit' => $cliente->NIT,
        ];
    }

    /**
     * Listar sucursales de un cliente
     */
    public function listarSucursalesClienteEnBase(string $dbName, int $idCliente): array
    {
        try {
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            
            $connection->statement("USE `{$dbName}`");
            
            $sucursales = $connection->table('todos_cliente_sucursal')
                ->where('IdCliente', $idCliente)
                ->select(
                    'IdClienteSucursal as id',
                    'IdCliente as idCliente',
                    'Nombre as nombre',
                    'Direccion as direccion',
                    'NumeroSucursal as numero'
                )
                ->orderBy('NumeroSucursal')
                ->get();
            
            $connection->statement("USE `gestion_comercialalimentos`");
            
            return $sucursales->toArray();
            
        } catch (\Exception $e) {
            Log::error('Error listando sucursales', [
                'db' => $dbName,
                'idCliente' => $idCliente,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener sucursal por ID
     */
    public function obtenerSucursalPorId(string $dbName, int $idSucursal)
    {
        try {
            $connection = DB::connection('mysql_gestion_comercial_alimentos');
            
            $connection->statement("USE `{$dbName}`");
            
            $sucursal = $connection->table('todos_cliente_sucursal as s')
                ->join('todos_cliente as c', 'c.IdCliente', '=', 's.IdCliente')
                ->select(
                    's.IdClienteSucursal',
                    's.IdCliente',
                    's.Nombre',
                    's.Direccion',
                    's.NumeroSucursal',
                    's.ActivoInactivo',
                    's.Telefono',
                    's.Celular',
                    's.Categoria',
                    's.Orden',
                    'c.NIT as nit'
                )
                ->where('s.IdClienteSucursal', $idSucursal)
                ->first();
            
            $connection->statement("USE `gestion_comercialalimentos`");
            
            return $sucursal;
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo sucursal', [
                'db' => $dbName,
                'id' => $idSucursal,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Crear sucursal en gestión
     */
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
                'ActivaInactivaR' => 0,
                'Orden' => $data['orden'] ?? 0,
                'Categoria' => $data['categoria'] ?? '',
                'ActivoInactivo' => $data['activoInactivo'] ? 0 : 1,
                'ControlInternoEfectivo' => 0,
                'facturacion_habilitada' => 1,
            ];
            
            Log::info('Insertando en todos_cliente_sucursal', $payload);
            
            // Insertar y obtener el ID
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