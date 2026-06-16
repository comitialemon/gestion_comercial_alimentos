<?php
// app/Http/Controllers/PuntoVenta/PdvBorrarLiquidacionController.php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PdvBorrarLiquidacionController extends Controller
{
    /**
     * Muestra el formulario del reporte
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        
        // Obtener sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        return Inertia::render('PuntoVenta/BorrarLiquidacion', [
            'sucursales' => $sucursales,
        ]);
    }
    
    /**
     * Obtener liquidaciones por sucursal (AJAX)
     */
    public function getLiquidaciones(Request $request)
    {
        \Log::info('=== getLiquidaciones llamado ===');
        \Log::info('sucursal_id: ' . $request->sucursal_id);
        
        $request->validate([
            'sucursal_id' => 'required|integer|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);
        
        $clienteId = session('cliente_id');
        $sucursalId = $request->sucursal_id;
        
        try {
            $liquidaciones = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion_vendedor as ivlv')
                ->join('todos_fecha as tf', 'ivlv.IdFecha', '=', 'tf.IdFecha')
                ->join('todos_cliente_sucursal as tcs', 'ivlv.iDsucursal', '=', 'tcs.IdClienteSucursal')
                ->join('todos_operador as top', 'ivlv.iDoperadorVendedor', '=', 'top.IdOperador')
                ->join('todos_identificador as ti', 'top.IdIdentificador', '=', 'ti.IdIdentificador')
                ->join('conta_diario as cd', 'ivlv.IdDiario', '=', 'cd.IdDiario')
                ->where('ivlv.IdCliente', $clienteId)
                ->where('ivlv.IdSucursal', $sucursalId)
                ->orderBy('tf.Fecha', 'desc')
                ->orderBy('cd.NumeroDiario', 'desc')
                ->select(
                    'ivlv.IdDiario as id',
                    DB::raw("CONCAT(tcs.Nombre, ' - ', ti.Nombre, ' - NoLiquidacion : ', cd.NumeroDiario, ' - Fecha: ', DATE_FORMAT(tf.Fecha, '%d/%m/%Y')) as display_text"),
                    'tf.Fecha as fecha',
                    'cd.NumeroDiario as numero_liquidacion',
                    'tcs.Nombre as sucursal_nombre',
                    'ti.Nombre as operador_nombre'
                )
                ->get();
            
            \Log::info('Liquidaciones encontradas: ' . $liquidaciones->count());
            
            return response()->json([
                'success' => true,
                'liquidaciones' => $liquidaciones
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error en getLiquidaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'liquidaciones' => []
            ], 500);
        }
    }
    
    /**
     * Eliminar liquidación (ejecuta las 3 operaciones)
     */
    public function eliminar(Request $request)
    {
        \Log::info('=== eliminar llamado ===');
        \Log::info('id_liquidacion: ' . $request->id_liquidacion);
        
        $request->validate([
            'id_liquidacion' => 'required|integer',
        ]);
        
        $idLiquidacion = $request->id_liquidacion;
        $resultados = [];
        
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            // 1. OBTENER LOS IdVentas que tienen esta liquidación
            $ventasIds = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('LiquidadoVendedor', $idLiquidacion)
                ->pluck('IdVentas');
            
            \Log::info('Ventas encontradas: ' . $ventasIds->count());
            $resultados['ventas_encontradas'] = $ventasIds->count();
            
            // 2. DELETE conta_diario_propiamente
            $deleted1 = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->where('IdDiario', $idLiquidacion)
                ->delete();
            $resultados['conta_diario_propiamente'] = $deleted1;
            \Log::info('conta_diario_propiamente: ' . $deleted1);
            
            // 3. DELETE impuestos_ventas_liquidacion_vendedor
            $deleted2 = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion_vendedor')
                ->where('IdDiario', $idLiquidacion)
                ->delete();
            $resultados['impuestos_ventas_liquidacion_vendedor'] = $deleted2;
            \Log::info('impuestos_ventas_liquidacion_vendedor: ' . $deleted2);
            
            // 4. UPDATE impuestos_ventas (poner LiquidadoVendedor = 0 en lugar de NULL)
            $updated = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('LiquidadoVendedor', $idLiquidacion)
                ->update(['LiquidadoVendedor' => 0]);
            $resultados['impuestos_ventas'] = $updated;
            \Log::info('impuestos_ventas actualizados: ' . $updated);
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            \Log::info('Proceso completado exitosamente');
            
            return response()->json([
                'success' => true,
                'message' => '✅ Proceso completado exitosamente',
                'resultados' => $resultados,
                'id_liquidacion' => $idLiquidacion
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            \Log::error('Error en eliminar: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => '❌ Error: ' . $e->getMessage(),
                'resultados' => $resultados,
                'id_liquidacion' => $idLiquidacion
            ], 500);
        }
    }
}