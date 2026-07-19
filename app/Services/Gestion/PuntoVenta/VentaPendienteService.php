<?php

namespace App\Services\Gestion\PuntoVenta;

use App\Services\TimezoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class VentaPendienteService
{
    protected $timezoneService;

    public function __construct(TimezoneService $timezoneService)
    {
        $this->timezoneService = $timezoneService;
    }

    /**
     * 🔥 VERIFICAR Y LIMPIAR VENTAS PENDIENTES DE DÍAS ANTERIORES
     * 
     * @return array ['limpiadas' => int, 'errores' => array]
     */
    public function limpiarVentasPendientes()
    {
        $clienteId = Session::get('cliente_id');
        $sucursalId = Session::get('cliente_sucursal_id');
        $operadorId = Session::get('operador_id');

        if (!$clienteId || !$sucursalId || !$operadorId) {
            return ['limpiadas' => 0, 'errores' => []];
        }

        // 🔥 FECHA ACTUAL DEL CLIENTE
        $fechaActual = $this->timezoneService->getFechaActual();

        // 🔥 BUSCAR VENTAS ACTIVAS DEL OPERADOR
        $ventasPendientes = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdOperadorIngresa', $operadorId)
            ->where('ActivoInactivo', 0)  // Ventas NO finalizadas
            ->where('NumeroFactura', 0)    // Sin número de factura
            ->get();

        if ($ventasPendientes->isEmpty()) {
            Log::info('🧹 No hay ventas pendientes para limpiar', [
                'cliente' => $clienteId,
                'sucursal' => $sucursalId,
                'operador' => $operadorId
            ]);
            return ['limpiadas' => 0, 'errores' => []];
        }

        $limpiadas = 0;
        $errores = [];

        foreach ($ventasPendientes as $venta) {
            $fechaVenta = date('Y-m-d', strtotime($venta->FechaVenta));

            // 🔥 SI LA VENTA ES DE UN DÍA ANTERIOR
            if ($fechaVenta !== $fechaActual) {
                try {
                    // 🔥 1. ELIMINAR DETALLES DE LA VENTA
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_detalle')
                        ->where('idventas', $venta->IdVentas)
                        ->delete();

                    // 🔥 2. ELIMINAR LIQUIDACIONES ASOCIADAS
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->where('IdVentas', $venta->IdVentas)
                        ->delete();

                    // 🔥 3. ELIMINAR LA VENTA
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas')
                        ->where('IdVentas', $venta->IdVentas)
                        ->delete();

                    $limpiadas++;
                    
                    Log::info('🗑️ Venta pendiente eliminada por cambio de día', [
                        'venta_id' => $venta->IdVentas,
                        'fecha_venta' => $fechaVenta,
                        'fecha_actual' => $fechaActual,
                        'operador_id' => $operadorId,
                        'sucursal_id' => $sucursalId
                    ]);

                } catch (\Exception $e) {
                    $errores[] = "Error eliminando venta {$venta->IdVentas}: " . $e->getMessage();
                    Log::error('❌ Error eliminando venta pendiente: ' . $e->getMessage(), [
                        'venta_id' => $venta->IdVentas,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // 🔥 SI SE ELIMINARON VENTAS, LIMPIAR SESIÓN
        if ($limpiadas > 0) {
            Session::forget('venta_tactil_id');
            Session::forget('venta_actual_id');
            Session::forget('venta_tactil_lugar_id');
            Session::forget('venta_tactil_comisionista_id');
            Session::forget('venta_tactil_comisionista_identificador');
            
            Log::info('🧹 Sesión limpiada después de eliminar ventas pendientes', [
                'ventas_eliminadas' => $limpiadas
            ]);
        }

        return [
            'limpiadas' => $limpiadas,
            'errores' => $errores
        ];
    }

    /**
     * 🔥 VERIFICAR SI HAY VENTA PENDIENTE DEL DÍA ACTUAL
     * 
     * @return object|null
     */
    public function verificarVentaPendienteHoy()
    {
        $clienteId = Session::get('cliente_id');
        $sucursalId = Session::get('cliente_sucursal_id');
        $operadorId = Session::get('operador_id');

        if (!$clienteId || !$sucursalId || !$operadorId) {
            return null;
        }

        $fechaActual = $this->timezoneService->getFechaActual();

        return DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdOperadorIngresa', $operadorId)
            ->where('ActivoInactivo', 0)
            ->where('NumeroFactura', 0)
            ->whereDate('FechaVenta', $fechaActual)
            ->first();
    }

    /**
     * 🔥 ELIMINAR UNA VENTA ESPECÍFICA Y SUS DETALLES
     * 
     * @param int $ventaId
     * @return bool
     */
    public function eliminarVenta($ventaId)
    {
        try {
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->delete();

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion')
                ->where('IdVentas', $ventaId)
                ->delete();

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->delete();

            Log::info('🗑️ Venta eliminada manualmente', ['venta_id' => $ventaId]);
            return true;

        } catch (\Exception $e) {
            Log::error('❌ Error eliminando venta: ' . $e->getMessage(), [
                'venta_id' => $ventaId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}