<?php

namespace App\Services\Pedidos;

use Illuminate\Support\Facades\DB;

class NumeroPedidoService
{
    /**
     * Obtiene el siguiente número de pedido de forma atómica.
     * Usa ON DUPLICATE KEY UPDATE + LAST_INSERT_ID de MySQL para ser
     * ultrarrápido incluso con miles de operadores concurrentes.
     *
     * Nota: Los "gaps" (números que faltan si una transacción falla)
     * son normales y esperados en sistemas de alta concurrencia.
     */
    public function obtenerSiguiente(int $idCliente, int $idSucursal): string
    {
        $conexion = DB::connection('mysql_gestion_comercial_alimentos');

        // 1. Asegurar que la fila exista (idempotente, no incrementa si ya existe)
        $conexion->table('pedidos_clientes_secuencias')->insertOrIgnore([
            'IdCliente' => $idCliente,
            'IdSucursal' => $idSucursal,
            'UltimoNumero' => 0,
        ]);

        // 2. Incrementar atómicamente
        $conexion->statement("
            INSERT INTO pedidos_clientes_secuencias (IdCliente, IdSucursal, UltimoNumero)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE
                UltimoNumero = LAST_INSERT_ID(UltimoNumero + 1),
                FechaActualizacion = NOW()
        ", [$idCliente, $idSucursal]);

        // 3. Obtener el número asignado a esta conexión
        $resultado = $conexion->selectOne('SELECT LAST_INSERT_ID() as nuevo_numero');
        $nuevoNumero = (int) $resultado->nuevo_numero;

        // 4. Fallback (caso extremo: la fila no existía y LAST_INSERT_ID no se seteó)
        if ($nuevoNumero === 0) {
            $secuencia = $conexion->table('pedidos_clientes_secuencias')
                ->where('IdCliente', $idCliente)
                ->where('IdSucursal', $idSucursal)
                ->first();
            $nuevoNumero = $secuencia->UltimoNumero;
        }

        return str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);
    }
}