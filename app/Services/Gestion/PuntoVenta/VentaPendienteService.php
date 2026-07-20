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
     */
    public function limpiarVentasPendientes()
    {
        $clienteId = Session::get('cliente_id');
        $sucursalId = Session::get('cliente_sucursal_id');
        $operadorId = Session::get('operador_id');

        if (!$clienteId || !$sucursalId || !$operadorId) {
            return ['limpiadas' => 0, 'errores' => []];
        }

        $fechaActual = $this->timezoneService->getFechaActual();

        $ventasPendientes = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdOperadorIngresa', $operadorId)
            ->where('ActivoInactivo', 0)
            ->where('NumeroFactura', 0)
            ->get();

        if ($ventasPendientes->isEmpty()) {
            return ['limpiadas' => 0, 'errores' => []];
        }

        $limpiadas = 0;
        $errores = [];

        foreach ($ventasPendientes as $venta) {
            $fechaVenta = date('Y-m-d', strtotime($venta->FechaVenta));

            if ($fechaVenta !== $fechaActual) {
                try {
                    $this->eliminarVentaConReversion($venta->IdVentas);
                    $limpiadas++;
                } catch (\Exception $e) {
                    $errores[] = "Error eliminando venta {$venta->IdVentas}: " . $e->getMessage();
                    Log::error('❌ Error eliminando venta pendiente: ' . $e->getMessage(), [
                        'venta_id' => $venta->IdVentas,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if ($limpiadas > 0) {
            Session::forget('venta_tactil_id');
            Session::forget('venta_actual_id');
            Session::forget('venta_tactil_lugar_id');
            Session::forget('venta_tactil_comisionista_id');
            Session::forget('venta_tactil_comisionista_identificador');
        }

        return [
            'limpiadas' => $limpiadas,
            'errores' => $errores
        ];
    }

    /**
     * 🔥 ELIMINAR VENTA CON REVERSIÓN DE INVENTARIO (ENTRADA)
     */
    public function eliminarVentaConReversion($ventaId)
    {
        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // 🔥 1. OBTENER LA VENTA
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->first();

            if (!$venta) {
                throw new \Exception('Venta no encontrada');
            }

            // 🔥 2. OBTENER LOS MOVIMIENTOS DE INVENTARIO DE ESTA VENTA
            $movimientos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdTipoDeOperacion', 2)  // ID de "Ventas"
                ->where('IdDocumento', $ventaId)
                ->get();

            Log::info('🔄 Revirtiendo inventario para venta eliminada', [
                'venta_id' => $ventaId,
                'movimientos' => count($movimientos)
            ]);

            // 🔥 3. OBTENER FECHA ACTUAL
            $fechaActual = $this->timezoneService->getFechaActual();
            
            $idFecha = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('Fecha', $fechaActual)
                ->value('IdFecha');
            
            if (!$idFecha) {
                $idFecha = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->insertGetId([
                        'Fecha' => $fechaActual,
                        'ActivoInactivo' => 1,
                        'CierreSucursal' => 0,
                        'CierrePermanente' => 0,
                    ]);
            }

            // 🔥 4. OBTENER ID DEL TIPO DE OPERACIÓN "Anulación Venta"
            $idTipoAnulacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdCliente', $venta->IdCliente)
                ->where('Detalle', 'Anulación Venta')
                ->where('ActivoInactivo', 0)
                ->value('IdTipoOperacion');

            if (!$idTipoAnulacion) {
                throw new \Exception('No se encontró el tipo de operación "Anulación Venta". Por favor, créalo primero.');
            }

            // 🔥 5. CREAR MOVIMIENTOS DE REVERSIÓN (ENTRADAS)
            foreach ($movimientos as $mov) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->insert([
                        'IdTipoDeOperacion' => $idTipoAnulacion,  // "Anulación Venta"
                        'IdDocumento' => $ventaId,
                        'IdFecha' => $idFecha,
                        'IdAlmacen' => $mov->IdAlmacen,
                        'IdProducto' => $mov->IdProducto,
                        'Glosa' => "ELIMINACIÓN Venta No {$venta->NumeroFactura}",
                        'D_H' => 'D',  // 🔥 ENTRADA (ingreso)
                        'Unidades' => $mov->Unidades,
                        'Bolivianos' => $mov->Bolivianos,
                        'IdCliente' => $venta->IdCliente,
                        'IdSucursal' => $venta->IdClienteSucursal,
                    ]);
            }

            // 🔥 6. ELIMINAR DETALLES DE LA VENTA
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->delete();

            // 🔥 7. ELIMINAR LIQUIDACIONES ASOCIADAS
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion')
                ->where('IdVentas', $ventaId)
                ->delete();

            // 🔥 8. ELIMINAR LA VENTA
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->delete();

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('🗑️ Venta eliminada con reversión de inventario', [
                'venta_id' => $ventaId,
                'movimientos_revertidos' => count($movimientos)
            ]);

            return true;

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('❌ Error eliminando venta con reversión: ' . $e->getMessage(), [
                'venta_id' => $ventaId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 🔥 VERIFICAR SI HAY VENTA PENDIENTE DEL DÍA ACTUAL
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
}