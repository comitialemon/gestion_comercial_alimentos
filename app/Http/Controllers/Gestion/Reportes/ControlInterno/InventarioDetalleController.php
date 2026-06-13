<?php

namespace App\Http\Controllers\Gestion\Reportes\ControlInterno;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoDetalle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class InventarioDetalleController extends Controller
{
    /**
     * Muestra el formulario del reporte (selector de producto)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Obtener productos del cliente para el selector
        $productos = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);
        
        // Si se envió un producto específico, mostrar la grid
        $movimientos = null;
        $productoSeleccionado = null;
        
        if ($request->has('producto_id') && $request->producto_id) {
            $productoId = $request->producto_id;
            
            $productoSeleccionado = ProductoDetalle::porContexto()
                ->where('IdProducto', $productoId)
                ->first(['IdProducto', 'Codigo', 'Descripcion', 'IdUnidadMedida']);
            
            // Obtener movimientos con saldo acumulado usando subconsulta
            $movimientos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdProducto', $productoId)
                ->orderBy('IdFecha')
                ->orderBy('IdInventarioPropiamente')
                ->get();
            
            // Calcular saldo acumulado manualmente (más confiable)
            $saldoAcumulado = 0;
            foreach ($movimientos as $movimiento) {
                if ($movimiento->D_H == 'D') {
                    $saldoAcumulado += (float) $movimiento->Unidades;
                    $movimiento->UnidadesSigno = (float) $movimiento->Unidades;
                } else {
                    $saldoAcumulado -= (float) $movimiento->Unidades;
                    $movimiento->UnidadesSigno = - (float) $movimiento->Unidades;
                }
                $movimiento->SaldoAcumulado = $saldoAcumulado;
                
                // Obtener nombre del tipo de operación
                $tipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_tipooperacion')
                    ->where('IdTipoOperacion', $movimiento->IdTipoDeOperacion)
                    ->first(['Detalle', 'Concepto']);
                $movimiento->tipo_operacion_detalle = $tipoOperacion->Detalle ?? '-';
                
                // Obtener nombre del almacén
                $almacen = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_almacen')
                    ->where('IdAlmacen', $movimiento->IdAlmacen)
                    ->first(['Almacen']);
                $movimiento->almacen_nombre = $almacen->Almacen ?? '-';
                
                // Obtener fecha formateada
                $fecha = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->where('IdFecha', $movimiento->IdFecha)
                    ->value('Fecha');
                $movimiento->fecha_formateada = $fecha ? date('d/m/Y', strtotime($fecha)) : '-';
            }
            
            // Paginar manualmente
            $perPage = 20;
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $total = count($movimientos);
            
            $movimientos = new \Illuminate\Pagination\LengthAwarePaginator(
                array_slice($movimientos->toArray(), $offset, $perPage),
                $total,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        
        return Inertia::render('Gestion/Reportes/ControlInterno/InventarioDetalle', [
            'productos' => $productos,
            'movimientos' => $movimientos,
            'productoSeleccionado' => $productoSeleccionado,
            'filtros' => [
                'producto_id' => $request->producto_id,
            ],
        ]);
    }
    
    /**
     * API: Obtener movimientos con saldo acumulado en formato JSON
     */
    public function getMovimientos(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|integer|exists:inventario_productodetalle,IdProducto',
        ]);
        
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $productoId = $request->producto_id;
        
        $movimientos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_propiamente')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdProducto', $productoId)
            ->orderBy('IdFecha')
            ->orderBy('IdInventarioPropiamente')
            ->get();
        
        // Calcular saldo acumulado
        $saldoAcumulado = 0;
        foreach ($movimientos as $movimiento) {
            if ($movimiento->D_H == 'D') {
                $saldoAcumulado += (float) $movimiento->Unidades;
                $movimiento->UnidadesSigno = (float) $movimiento->Unidades;
            } else {
                $saldoAcumulado -= (float) $movimiento->Unidades;
                $movimiento->UnidadesSigno = - (float) $movimiento->Unidades;
            }
            $movimiento->SaldoAcumulado = $saldoAcumulado;
            
            // Tipo de operación
            $tipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdTipoOperacion', $movimiento->IdTipoDeOperacion)
                ->first(['Detalle']);
            $movimiento->tipo_operacion = $tipoOperacion->Detalle ?? '-';
            
            // Fecha formateada
            $fecha = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('IdFecha', $movimiento->IdFecha)
                ->value('Fecha');
            $movimiento->fecha = $fecha ? date('d/m/Y', strtotime($fecha)) : '-';
        }
        
        return response()->json([
            'success' => true,
            'movimientos' => $movimientos,
        ]);
    }
}