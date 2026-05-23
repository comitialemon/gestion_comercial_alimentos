<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\CategoriaProducto;
use App\Models\Gestion\Inventario\ProductoVenta;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;  // ← IMPORTANTE: agregar esta línea

class MenuTactilController extends Controller
{
    public function index()
    {
        // Verificar si hay una venta activa en sesión
        $ventaId = session('venta_tactil_id');
        
        if (!$ventaId) {
            // No hay venta en sesión, redirigir a nueva venta
            return redirect()->route('venta-tactil.nueva')
                ->with('warning', 'Debes iniciar una venta primero');
        }
        
        // Verificar que la venta existe y está pendiente
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $ventaId)
            ->where('ActivoInactivo', 0)
            ->first();
            
        if (!$venta) {
            // La venta no existe o ya fue pagada, limpiar sesión
            session()->forget('venta_tactil_id');
            return redirect()->route('venta-tactil.nueva')
                ->with('warning', 'La venta anterior fue finalizada. Inicia una nueva.');
        }
        
        // Obtener comisionista de sesión
        $comisionistaNombre = null;
        $comisionistaId = session('venta_tactil_comisionista_id');
        if ($comisionistaId) {
            $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_comisionitas as c')
                ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('c.IdComisionista', $comisionistaId)
                ->first();
            $comisionistaNombre = $comisionista ? $comisionista->Nombre : null;
        }
        
        // Mostrar categorías
        $categorias = CategoriaProducto::porContexto()
            ->whereNull('id_padre')
            ->where('activo', 1)
            ->orderBy('orden')
            ->get();

        return Inertia::render('PuntoVenta/MenuTactil/Index', [
            'categorias' => $categorias,
            'ruta' => [],
            'titulo' => 'Menú Principal',
            'comisionista' => $comisionistaNombre
        ]);
    }

    public function verCategoria($id)
    {
        $categoria = CategoriaProducto::porContexto()
            ->with('padre')
            ->findOrFail($id);

        $subcategorias = CategoriaProducto::porContexto()
            ->where('id_padre', $id)
            ->where('activo', 1)
            ->orderBy('orden')
            ->get();

        $comisionistaNombre = null;
        $comisionistaId = session('venta_tactil_comisionista_id');
        if ($comisionistaId) {
            $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_comisionitas as c')
                ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('c.IdComisionista', $comisionistaId)
                ->first();
            $comisionistaNombre = $comisionista ? $comisionista->Nombre : null;
        }

        if ($subcategorias->isNotEmpty()) {
            return Inertia::render('PuntoVenta/MenuTactil/Index', [
                'categorias' => $subcategorias,
                'ruta' => $this->obtenerRuta($categoria),
                'titulo' => $categoria->nombre,
                'comisionista' => $comisionistaNombre
            ]);
        }

        $sucursalId = session('cliente_sucursal_id');
        
        // 🔥 CORREGIDO: usar categoriasHabilitadas()
        $productos = ProductoVenta::porContexto()
            ->where('ActivoInactivo', ProductoVenta::COMERCIAL_ACTIVO)  // ✅ CORREGIDO
            ->whereHas('categoriasHabilitadas', function($q) use ($id, $sucursalId) {
                $q->where('inventario_producto_categoria.id_categoria', $id)
                ->where('inventario_producto_categoria.id_sucursal', $sucursalId);
            })
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto as id', 'Detalle as nombre', 'PrecioVenta']);

        return Inertia::render('PuntoVenta/MenuTactil/Productos', [
            'categoria' => $categoria,
            'productos' => $productos,
            'ruta' => $this->obtenerRuta($categoria),
            'comisionista' => $comisionistaNombre
        ]);
    }

    private function obtenerRuta($categoria)
    {
        $ruta = [];
        $actual = $categoria;
        
        while ($actual) {
            array_unshift($ruta, [
                'id' => $actual->id_categoria,
                'nombre' => $actual->nombre
            ]);
            $actual = $actual->padre;
        }
        
        return $ruta;
    }
    /**
     * Obtener precio de un producto según comisionista y sucursal
     */
    public function getPrecioProducto($idProducto)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $identificadorComisionista = session('venta_tactil_comisionista_identificador');
            
            // 1. Buscar precio mayorista (por comisionista)
            $precioMayorista = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_preciomayorista')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdIdentificador', $identificadorComisionista)
                ->where('IdProducto', $idProducto)
                ->value('Precio');
            
            if ($precioMayorista) {
                return response()->json([
                    'precio' => (float) $precioMayorista,
                    'tipo' => 'mayorista'
                ]);
            }
            
            // 2. Buscar precio por sucursal
            $precioSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_preciosucursal')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdProducto', $idProducto)
                ->where('PrecioDiferenciadoA', 'Sucursal')
                ->value('Precio');
            
            if ($precioSucursal) {
                return response()->json([
                    'precio' => (float) $precioSucursal,
                    'tipo' => 'sucursal'
                ]);
            }
            
            // 3. Precio por defecto
            $producto = ProductoVenta::find($idProducto);
            $precioDefault = $producto ? (float) $producto->PrecioVenta : 0;
            
            return response()->json([
                'precio' => $precioDefault,
                'tipo' => 'default'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo precio: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Agregar producto al carrito (guardar en impuestos_ventas_detalle)
     */
    public function agregarAlCarrito(Request $request)
    {
        try {
            $request->validate([
                'id_producto' => 'required|integer',
                'unidades' => 'required|integer|min:1',
                'precio' => 'required|numeric|min:0'
            ]);

            $ventaId = session('venta_tactil_id');
            
            if (!$ventaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una venta activa. Inicia una nueva venta primero.'
                ], 400);
            }

            // Verificar que la venta existe y está pendiente
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->where('ActivoInactivo', 0)
                ->first();

            if (!$venta) {
                return response()->json([
                    'success' => false,
                    'message' => 'La venta no existe o ya fue finalizada'
                ], 400);
            }

            // Calcular total
            $total = $request->precio * $request->unidades;

            // Insertar en impuestos_ventas_detalle
            $detalleId = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->insertGetId([
                    'idventas' => $ventaId,
                    'IdVentaGrupo' => 0, // Temporal, se puede actualizar después
                    'idrelacionventainventario' => $request->id_producto,
                    'unidades' => $request->unidades,
                    'preciounidades' => $request->precio,
                    'totalbolivianos' => $total,
                    'PorcentajeDescuento' => 0,
                    'Descuento' => 0,
                    'TotalBolivianosFacturado' => 0,
                    'entregado' => 0,
                ]);

            // Actualizar el ImporteVenta en la cabecera (sumar el nuevo total)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->increment('ImporteVenta', $total);

            // Obtener el producto para respuesta
            $producto = ProductoVenta::find($request->id_producto);

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado correctamente',
                'data' => [
                    'id_detalle' => $detalleId,
                    'producto' => $producto ? $producto->Detalle : 'Producto',
                    'unidades' => $request->unidades,
                    'precio' => $request->precio,
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error agregando producto al carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar: ' . $e->getMessage()
            ], 500);
        }
    }
}