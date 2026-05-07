<?php

namespace App\Services\Impuestos;

use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Impuestos\VentaDetalle;
use App\Models\Gestion\Impuestos\VentaLiquidacion;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;
use App\Models\Gestion\Impuestos\VentaDosificacion;
use App\Models\Gestion\Inventario\Movimiento;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaService
{
    /**
     * Obtener la deuda total de una venta
     */
    public function getDeuda($ventaId)
    {
        return VentaDetalle::where('idventas', $ventaId)->sum('totalbolivianos');
    }

    /**
     * Obtener métodos de pago disponibles
     */
    public function getMetodosPago($clienteId)
    {
        return VentaLiquidacionConcepto::where('IdCliente', $clienteId)
            ->orderBy('IdConceptoLiquidacion')
            ->get();
    }

    /**
     * Buscar identificador por NIT
     */
    public function buscarIdentificadorPorNit($nit)
    {
        return Identificador::porNit($nit)->first();
    }

    /**
     * Obtener dosificación activa
     */
    public function getDosificacionActiva($clienteId, $sucursalId, $tipoDosificacion)
    {
        return VentaDosificacion::activa($clienteId, $sucursalId, $tipoDosificacion)->first();
    }

    /**
     * Generar número de factura
     */
    public function generarNumeroFactura($clienteId, $sucursalId, $autorizacion)
    {
        $maxFactura = Venta::where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('NumeroAutorizacion', $autorizacion)
            ->max('NumeroFactura');

        return ($maxFactura ?? 0) + 1;
    }

    /**
     * Generar ticket de venta del día
     */
    public function generarTicketDia($clienteId, $sucursalId)
    {
        $maxTicket = Venta::where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->whereDate('FechaVenta', now()->toDateString())
            ->max('TicketDia');

        return ($maxTicket ?? 0) + 1;
    }

    /**
     * Generar código de control
     */
    public function generarCodigoControl($autorizacion, $numeroFactura, $nit, $fechaTransaccion, $montoTotal, $llaveDosificacion)
    {
        // Implementar algoritmo de código de control según normativa SIN
        // Por ahora es temporal
        return substr(md5($autorizacion . $numeroFactura . $nit . $fechaTransaccion . $montoTotal . $llaveDosificacion), 0, 15);
    }

    /**
     * Procesar la venta (pago)
     */
    public function procesarPago($ventaId, $data)
    {
        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // 1. Obtener venta y deuda
            $venta = Venta::findOrFail($ventaId);
            $deuda = $this->getDeuda($ventaId);

            // 2. Calcular total pagado
            $totalPagado = ($data['efectivo'] ?? 0) + ($data['tarjeta'] ?? 0) + ($data['qr'] ?? 0) + ($data['clientes'] ?? 0);

            // 3. Validar que el pago cubra la deuda
            if ($totalPagado < $deuda) {
                throw new \Exception("El pago recibido debe ser igual o mayor a la deuda");
            }

            // 4. Obtener NIT del cliente (si aplica)
            $nitId = null;
            if (!empty($data['nit'])) {
                $identificador = $this->buscarIdentificadorPorNit($data['nit']);
                $nitId = $identificador?->IdIdentificador;
            }

            // 5. Obtener dosificación activa
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $tipoDosificacion = $this->determinarTipoDosificacion($clienteId, $sucursalId, $nitId);
            
            $dosificacion = $this->getDosificacionActiva($clienteId, $sucursalId, $tipoDosificacion);
            
            if (!$dosificacion) {
                throw new \Exception("No hay dosificación activa para esta sucursal");
            }

            // 6. Generar número de factura y código de control
            $numeroFactura = $this->generarNumeroFactura($clienteId, $sucursalId, $dosificacion->Autorizacion);
            $ticketDia = $this->generarTicketDia($clienteId, $sucursalId);
            
            $fechaTransaccion = now()->format('Ymd');
            $nitNumero = $identificador?->CI_NIT ?? 0;
            
            $codigoControl = $this->generarCodigoControl(
                $dosificacion->Autorizacion,
                $numeroFactura,
                $nitNumero,
                $fechaTransaccion,
                $deuda,
                $dosificacion->LlaveDosificacion
            );

            // 7. Actualizar venta
            $venta->update([
                'IdNIT' => $nitId,
                'NumeroFactura' => $numeroFactura,
                'NumeroAutorizacion' => $dosificacion->Autorizacion,
                'CodigoControl' => $codigoControl,
                'ImporteVenta' => $deuda,
                'ActivoInactivo' => 1,
                'TicketDia' => $ticketDia,
                'Observacion' => $data['observacion'] ?? null,
            ]);

            // 8. Guardar liquidación (métodos de pago)
            $metodosPago = $this->getMetodosPago($clienteId);
            $operadorId = session('operador_id');

            foreach ($metodosPago as $metodo) {
                $monto = $this->getMontoPorConcepto($metodo->Concepto, $data);
                
                if ($monto > 0) {
                    $identificadorId = $this->getIdentificadorPorConcepto($metodo->Concepto, $data, $operadorId);
                    
                    VentaLiquidacion::create([
                        'IdVentas' => $ventaId,
                        'IdDiario' => 0,
                        'IdIdentificador' => $identificadorId,
                        'IdCuenta' => $metodo->IdConceptoLiquidacion,
                        'Bolivianos' => $monto,
                        'EfectivoRecibido' => $totalPagado,
                    ]);
                }
            }

            // 9. Actualizar inventario (descontar productos)
            $this->actualizarInventario($ventaId, $clienteId, $sucursalId, $numeroFactura);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return [
                'success' => true,
                'ticket_dia' => $ticketDia,
                'numero_factura' => $numeroFactura,
                'codigo_control' => $codigoControl,
                'cambio' => $totalPagado - $deuda,
                'total_pagado' => $totalPagado,
                'deuda' => $deuda,
            ];

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error procesando pago: ' . $e->getMessage());
            throw $e;
        }
    }

    private function determinarTipoDosificacion($clienteId, $sucursalId, $nitId)
    {
        // Por defecto: 1 = Factura
        $tipo = 1;
        
        // Verificar si la sucursal tiene recibos habilitados
        $sucursal = \App\Models\Todos\ClienteSucursal::find($sucursalId);
        
        if ($sucursal && $sucursal->ActivaInactivaR == 0) {
            // NIT cero o 99
            if ($nitId) {
                $identificador = Identificador::find($nitId);
                if ($identificador && in_array($identificador->CI_NIT, [0, 99])) {
                    $tipo = 3; // Recibo
                }
            }
        }
        
        return $tipo;
    }

    private function getMontoPorConcepto($concepto, $data)
    {
        $mapa = [
            'Efectivo' => 'efectivo',
            'Tarjeta' => 'tarjeta',
            'QR' => 'qr',
            'Cliente' => 'clientes',
        ];
        
        $key = $mapa[$concepto] ?? null;
        return $key ? ($data[$key] ?? 0) : 0;
    }

    private function getIdentificadorPorConcepto($concepto, $data, $operadorId)
    {
        // Para efectivo, tarjeta, QR se usa el operador
        if (in_array($concepto, ['Efectivo', 'Tarjeta', 'QR'])) {
            $operador = \App\Models\Todos\Operador::find($operadorId);
            return $operador?->IdIdentificador;
        }
        
        // Para Clientes se usa el identificador del cliente
        if ($concepto == 'Cliente') {
            if (!empty($data['identificador_clientes'])) {
                return $data['identificador_clientes'];
            }
            throw new \Exception("Para pago a crédito debe seleccionar un cliente");
        }
        
        return null;
    }

    private function actualizarInventario($ventaId, $clienteId, $sucursalId, $numeroFactura)
    {
        // Obtener detalles de venta
        $detalles = VentaDetalle::where('idventas', $ventaId)->get();
        
        // Obtener almacén principal
        $almacen = Almacen::principal($clienteId, $sucursalId)->first();
        
        if (!$almacen) {
            throw new \Exception("No se encontró almacén principal");
        }
        
        // Obtener fecha para inventario
        $fecha = \App\Models\Todos\Fecha::firstOrCreate(
            ['Fecha' => now()->toDateString()],
            ['ActivoInactivo' => 1, 'CierreSucursal' => 0, 'CierrePermanente' => 0]
        );
        
        $tipoOperacion = 2; // Venta
        
        foreach ($detalles as $detalle) {
            // Obtener porciones del producto
            $productos = \App\Models\Inventario\RelacionVentaDetalle::where('IdDetalleProducto', $detalle->idrelacionventainventario)->get();
            
            foreach ($productos as $producto) {
                $cantidad = $producto->Porcion * $detalle->unidades;
                
                // Obtener precio costo
                $precioCosto = \App\Models\Inventario\ProductoPrecioCosto::where('IdProducto', $producto->IdProducto)
                    ->orderBy('IdPrecioCosto', 'DESC')
                    ->first();
                
                $costoTotal = $cantidad * ($precioCosto?->PrecioCosto ?? 0);
                
                // Registrar movimiento
                Movimiento::create([
                    'IdTipoDeOperacion' => $tipoOperacion,
                    'IdDocumento' => $ventaId,
                    'IdFecha' => $fecha->IdFecha,
                    'IdAlmacen' => $almacen->IdAlmacen,
                    'IdProducto' => $producto->IdProducto,
                    'Glosa' => "Factura Ventas No {$numeroFactura}",
                    'D_H' => 'H', // Haber (salida)
                    'Unidades' => $cantidad,
                    'Bolivianos' => $costoTotal,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                ]);
            }
        }
    }
}