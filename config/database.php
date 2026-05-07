<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        // ✅ Base de datos propia de Gestión Comercial
        'mysql_gestion_comercial_alimentos' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'mysql-gestion'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'gestion_comercialalimentos'),
            'username' => env('DB_USERNAME', 'cacho'),
            'password' => env('DB_PASSWORD', 'alemon1'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        // ✅ Conexión a la base de datos de FACTURACIÓN (solo lectura de catálogos)
        'facturacion' => [
            'driver' => 'mysql',
            'host' => env('FACTURACION_DB_HOST', 'siat-mysql'),
            'port' => env('FACTURACION_DB_PORT', '3306'),
            'database' => env('FACTURACION_DB_DATABASE', 'facturacion'),
            'username' => env('FACTURACION_DB_USERNAME', 'cacho'),
            'password' => env('FACTURACION_DB_PASSWORD', 'alemon1'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('PG_URL'),
            'host' => env('PG_HOST', '127.0.0.1'),
            'port' => env('PG_PORT', '5432'),
            'database' => env('PG_DATABASE', 'postgres'),
            'username' => env('PG_USERNAME', 'postgres'),
            'password' => env('PG_PASSWORD', ''),
            'charset' => env('PG_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('MSSQL_URL'),
            'host' => env('MSSQL_HOST', 'localhost'),
            'port' => env('MSSQL_PORT', '1433'),
            'database' => env('MSSQL_DATABASE', 'laravel'),
            'username' => env('MSSQL_USERNAME', 'sa'),
            'password' => env('MSSQL_PASSWORD', ''),
            'charset' => env('MSSQL_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];