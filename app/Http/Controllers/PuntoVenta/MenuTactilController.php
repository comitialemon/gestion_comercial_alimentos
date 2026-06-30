<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\CategoriaProducto;
use App\Models\Gestion\Inventario\ProductoVenta;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MenuTactilController extends Controller
{
    public function index()
    {
        $ventaId = session('venta_tactil_id');
        
        if (!$ventaId) {
            return redirect()->route('venta-tactil.nueva')
                ->with('warning', 'Debes iniciar una venta primero');
        }
        
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $ventaId)
            ->where('ActivoInactivo', 0)
            ->first();
            
        if (!$venta) {
            session()->forget('venta_tactil_id');
            return redirect()->route('venta-tactil.nueva')
                ->with('warning', 'La venta anterior fue finalizada. Inicia una nueva.');
        }
        
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

        if ($subcategorias->isNotEmpty()) {
            return Inertia::render('PuntoVenta/MenuTactil/Index', [
                'categorias' => $subcategorias,
                'ruta' => $this->obtenerRuta($categoria),
                'titulo' => $categoria->nombre,
                'comisionista' => $this->getComisionistaNombre()
            ]);
        }

        // Obtener datos del contexto
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $identificadorComisionista = session('venta_tactil_comisionista_identificador');

        // 🔥 CORREGIDO: Obtener productos con su imagen principal
        $productosQuery = ProductoVenta::porContexto()
            ->with('imagenPrincipal') // 🔥 Cargar la imagen principal
            ->where('ActivoInactivo', 0)
            ->whereHas('categorias', function($q) use ($id) {
                $q->where('inventario_producto_categoria.id_categoria', $id);
            })
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto as id', 'Detalle as nombre', 'PrecioVenta']);

        // Calcular precio real para cada producto
        $productos = [];
        foreach ($productosQuery as $producto) {
            // 🔥 Obtener URL de la imagen desde la relación
            $imagenUrl = null;
            if ($producto->imagenPrincipal) {
                $imagenUrl = $producto->imagenPrincipal->url_thumbnail;
            }

            $precioReal = $producto->PrecioVenta;
            $tipoPrecio = 'default';

            // 1. Buscar precio mayorista (por comisionista)
            if ($identificadorComisionista) {
                $precioMayorista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_preciomayorista')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdIdentificador', $identificadorComisionista)
                    ->where('IdProducto', $producto->id)
                    ->value('Precio');
                
                if ($precioMayorista) {
                    $precioReal = (float) $precioMayorista;
                    $tipoPrecio = 'mayorista';
                }
            }

            // 2. Si no hay mayorista, buscar precio por sucursal
            if ($tipoPrecio === 'default') {
                $precioSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_preciosucursal')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdProducto', $producto->id)
                    ->where('PrecioDiferenciadoA', 'Sucursal')
                    ->value('Precio');
                
                if ($precioSucursal) {
                    $precioReal = (float) $precioSucursal;
                    $tipoPrecio = 'sucursal';
                }
            }

            $productos[] = [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio_real' => $precioReal,
                'precio_normal' => (float) $producto->PrecioVenta,
                'tipo_precio' => $tipoPrecio,
                'imagen' => $imagenUrl, // 🔥 Usar la URL de la imagen
            ];
        }

        return Inertia::render('PuntoVenta/MenuTactil/Productos', [
            'categoria' => $categoria,
            'productos' => $productos,
            'ruta' => $this->obtenerRuta($categoria),
            'comisionista' => $this->getComisionistaNombre()
        ]);
    }

    private function getComisionistaNombre()
    {
        $comisionistaId = session('venta_tactil_comisionista_id');
        if ($comisionistaId) {
            $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_comisionitas as c')
                ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('c.IdComisionista', $comisionistaId)
                ->first();
            return $comisionista ? $comisionista->Nombre : null;
        }
        return null;
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
     * Agregar producto al carrito
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

            $total = $request->precio * $request->unidades;

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->insert([
                    'idventas' => $ventaId,
                    'IdVentaGrupo' => 0,
                    'idrelacionventainventario' => $request->id_producto,
                    'unidades' => $request->unidades,
                    'preciounidades' => $request->precio,
                    'totalbolivianos' => $total,
                    'PorcentajeDescuento' => 0,
                    'Descuento' => 0,
                    'TotalBolivianosFacturado' => 0,
                    'entregado' => 0,
                ]);

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->increment('ImporteVenta', $total);

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado correctamente'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error agregando producto al carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Versión simplificada de getDetallesCombo
     */
    public function getDetallesCombo($idProducto)
    {
        try {
            $clienteId = session('cliente_id');
            
            // Obtener la composición del combo
            $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $idProducto)
                ->get();
            
            $composicion = [];
            foreach ($detalles as $detalle) {
                // Obtener el producto
                $producto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdProducto', $detalle->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->first();
                
                $composicion[] = [
                    'id_producto' => $detalle->IdProducto,
                    'nombre' => $producto->Descripcion ?? 'Producto',
                    'codigo' => $producto->Codigo ?? '',
                    'porcion' => (float) $detalle->Porcion,
                ];
            }
            
            // Obtener las opciones
            $opciones = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_combo_opcion')
                ->where('id_producto_combo', $idProducto)
                ->where('activo', 1)
                ->orderBy('orden')
                ->get();
            
            $opcionesFormateadas = [];
            foreach ($opciones as $opcion) {
                // Obtener producto original
                $original = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdProducto', $opcion->id_producto_original)
                    ->where('IdCliente', $clienteId)
                    ->first();
                
                // Obtener producto sustituto
                $sustituto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdProducto', $opcion->id_producto_sustituto)
                    ->where('IdCliente', $clienteId)
                    ->first();
                
                $opcionesFormateadas[] = [
                    'id_combo_opcion' => $opcion->id_combo_opcion,
                    'id_producto_original' => $opcion->id_producto_original,
                    'nombre_original' => $original->Descripcion ?? 'Producto',
                    'id_producto_sustituto' => $opcion->id_producto_sustituto,
                    'nombre_sustituto' => $sustituto->Descripcion ?? 'Producto',
                    'codigo_sustituto' => $sustituto->Codigo ?? '',
                    'orden' => $opcion->orden,
                    'cantidad_maxima' => (int) ($opcion->cantidad ?? 1),
                ];
            }
            
            return response()->json([
                'success' => true,
                'combo' => [
                    'id' => $idProducto,
                    'nombre' => 'Combo',
                    'precio_real' => 0,
                    'composicion' => $composicion,
                    'opciones' => $opcionesFormateadas,
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error en getDetallesCombo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}