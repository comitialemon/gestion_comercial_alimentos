<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Inventario\ProductoVentaPrecioSucursal;
use App\Models\Gestion\Inventario\ProductoVentaPrecioMayorista;
use App\Models\Gestion\Inventario\RelacionVentaDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\CategoriaProducto;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Gestion\Inventario\ProductoAprobacionConfig;
use App\Models\Gestion\Inventario\ProductoAprobacionSolicitud;
use App\Models\Gestion\Inventario\ProductoAprobacionVoto;

class ProductoVentaController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $query = ProductoVenta::where('IdCliente', $clienteId)
            ->with(['categoria']);
        
        // Filtro por estado
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }
        
        // 🔥 FILTRO POR CATEGORÍAS (múltiples)
        if ($request->filled('categorias')) {
            $categoriasArray = explode(',', $request->categorias);
            $query->whereIn('id_categoria', $categoriasArray);
        }
        
        // Búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                ->orWhere('Detalle', 'like', "%{$search}%");
            });
        }
        
        $productos = $query->orderBy('Detalle')->paginate(20)->withQueryString();
        
        // 🔥 OBTENER CATEGORÍAS CON EL CONTEO CORRECTO
        $categorias = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get()
            ->map(function($categoria) use ($clienteId, $request) {
                // Contar productos que pertenecen a esta categoría
                $query = ProductoVenta::where('IdCliente', $clienteId)
                    ->where('id_categoria', $categoria->id_categoria);
                
                // Aplicar el mismo filtro de búsqueda si existe
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function($q) use ($search) {
                        $q->where('Codigo', 'like', "%{$search}%")
                        ->orWhere('Detalle', 'like', "%{$search}%");
                    });
                }
                
                // Aplicar filtro de estado si existe
                if ($request->filled('estado') && $request->estado !== '') {
                    $query->where('ActivoInactivo', $request->estado);
                }
                
                $categoria->productos_count = $query->count();
                return $categoria;
            });
        
        $totalActivos = ProductoVenta::where('IdCliente', $clienteId)->where('ActivoInactivo', 0)->count();
        $totalInactivos = ProductoVenta::where('IdCliente', $clienteId)->where('ActivoInactivo', 1)->count();
        
        return Inertia::render('Gestion/Inventario/ProductosVenta/Index', [
            'productos' => $productos,
            'categorias' => $categorias,
            'totalActivos' => $totalActivos,
            'totalInactivos' => $totalInactivos,
            'filtros' => [
                'estado' => $request->estado,
                'categorias' => $request->categorias,
                'search' => $request->search,
            ],
        ]);
    }
    
    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // 🔥 Buscar borrador por estado_aprobacion = APROBACION_BORRADOR (0)
        $productoBorrador = ProductoVenta::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdOperadorInserta', $operadorId)
            ->where('estado_aprobacion', ProductoVenta::APROBACION_BORRADOR)
            ->first();

        if ($productoBorrador) {
            return redirect()->route('gestion.productos-venta.edit', $productoBorrador->IdDetalleProducto)
                ->with('info', 'Tienes un producto en borrador. Continúa editándolo.');
        }
        
        $categorias = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get();
        
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);
        
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);
        
        $productosInventario = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->whereHas('estado', function($q) {
                $q->where('Estado', 'Terminado');
            })
            ->orderBy('Codigo')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);
        
        return Inertia::render('Gestion/Inventario/ProductosVenta/Create', [
            'categorias' => $categorias,
            'sucursales' => $sucursales,
            'identificadores' => $identificadores,
            'productosInventario' => $productosInventario,
        ]);
    }
    
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        
        $producto = ProductoVenta::where('IdCliente', $clienteId)
            ->with('categoria')
            ->findOrFail($id);
        
        $preciosSucursal = ProductoVentaPrecioSucursal::where('IdCliente', $clienteId)
            ->where('IdProducto', $id)
            ->with('sucursal')
            ->get();
        
        $preciosMayorista = ProductoVentaPrecioMayorista::where('IdCliente', $clienteId)
            ->where('IdProducto', $id)
            ->with(['sucursal', 'identificador'])
            ->get();
        
        $detalles = RelacionVentaDetalle::where('IdDetalleProducto', $id)
            ->with('producto')
            ->get();
        
        // 🔥 OBTENER CATEGORÍAS COMPLETAS
        $categorias = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get();
        
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);
        
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);
        
        $productosInventario = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->whereHas('estado', function($q) {
                $q->where('Estado', 'Terminado');
            })
            ->orderBy('Codigo')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);
        
        return Inertia::render('Gestion/Inventario/ProductosVenta/Create', [
            'producto' => $producto,
            'preciosSucursal' => $preciosSucursal,
            'preciosMayorista' => $preciosMayorista,
            'detalles' => $detalles,
            'categorias' => $categorias,
            'sucursales' => $sucursales,
            'identificadores' => $identificadores,
            'productosInventario' => $productosInventario,
            'editando' => true,
        ]);
    }
    
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'id_categoria' => 'required|exists:inventario_menu_categoria,id_categoria',
            'Codigo' => 'required|string|max:100',
            'Detalle' => 'required|string|max:100',
            'PrecioVenta' => 'required|numeric|min:0',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:512',
        ]);

        $codigoExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Codigo', $request->Codigo)
            ->exists();

        if ($codigoExiste) {
            return response()->json([
                'success' => false,
                'message' => 'El código ya existe.'
            ], 422);
        }

        $detalleExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Detalle', $request->Detalle)
            ->exists();

        if ($detalleExiste) {
            return response()->json([
                'success' => false,
                'message' => 'El detalle ya existe.'
            ], 422);
        }

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $imagenUrl = null;
            if ($request->hasFile('imagen')) {
                $imagenUrl = $this->guardarImagenArchivo($request->file('imagen'), $request->Codigo);
            }

            $producto = ProductoVenta::create([
                'id_categoria' => $request->id_categoria,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'PrecioVenta' => $request->PrecioVenta,
                'ActivoInactivo' => ProductoVenta::COMERCIAL_INACTIVO,
                'estado_aprobacion' => ProductoVenta::APROBACION_BORRADOR,
                'ImagenProducto' => $imagenUrl,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
                'CierrePermanente' => 0,
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            // 🔥 DEVOLVER JSON EN VEZ DE REDIRECCIÓN
            return response()->json([
                'success' => true,
                'message' => 'Producto creado correctamente',
                'producto_id' => $producto->IdDetalleProducto
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al crear producto: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        if ($producto->estado_aprobacion == ProductoVenta::APROBACION_PENDIENTE) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar un producto pendiente de aprobación.'
            ], 400);
        }
        
        $request->validate([
            'id_categoria' => 'nullable|exists:inventario_menu_categoria,id_categoria',
            'Codigo' => 'required|string|max:100',
            'Detalle' => 'required|string|max:100',
            'PrecioVenta' => 'required|numeric|min:0',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // 🔥 Validación de archivo
        ]);
        
        // Validar Código
        $codigoExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Codigo', $request->Codigo)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($codigoExiste) {
            return response()->json([
                'success' => false,
                'message' => 'El código ya existe.'
            ], 422);
        }
        
        // Validar Detalle
        $detalleExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Detalle', $request->Detalle)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($detalleExiste) {
            return response()->json([
                'success' => false,
                'message' => 'El detalle ya existe.'
            ], 422);
        }
        
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $imagenUrl = $producto->ImagenProducto;
            
            // 🔥 Si hay nueva imagen, eliminar la anterior y guardar la nueva
            if ($request->hasFile('imagen')) {
                if ($imagenUrl && file_exists(public_path($imagenUrl))) {
                    unlink(public_path($imagenUrl));
                }
                $imagenUrl = $this->guardarImagenArchivo($request->file('imagen'), $request->Codigo);
            }
            
            $producto->update([
                'id_categoria' => $request->id_categoria,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'PrecioVenta' => $request->PrecioVenta,
                'ImagenProducto' => $imagenUrl,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado correctamente'
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al actualizar producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        if ($producto->ActivoInactivo != ProductoVenta::COMERCIAL_INACTIVO) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un producto que ya ha sido enviado a aprobación'
            ], 400);
        }
        
        try {
            // Eliminar imagen si existe
            if ($producto->ImagenProducto && file_exists(public_path($producto->ImagenProducto))) {
                unlink(public_path($producto->ImagenProducto));
            }
            
            // Eliminar primero los detalles relacionados
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $id)
                ->delete();
            
            // Eliminar el producto
            $producto->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function activar($id)
    {
        try {
            $clienteId = session('cliente_id');
            $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
            
            if ($producto->estado_aprobacion != ProductoVenta::APROBACION_APROBADO) {
                return response()->json([
                    'success' => false,
                    'message' => 'El producto debe estar aprobado primero. Estado actual: ' . $producto->estado_aprobacion
                ], 400);
            }
            
            $tieneDetalle = RelacionVentaDetalle::where('IdDetalleProducto', $id)->exists();
            
            if (!$tieneDetalle) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede activar. El producto no tiene relación con inventario.'
                ], 400);
            }
            
            $producto->update([
                'ActivoInactivo' => ProductoVenta::COMERCIAL_ACTIVO,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Producto activado correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en activar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function desactivar($id)
    {
        try {
            $clienteId = session('cliente_id');
            $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
            
            if ($producto->estado_aprobacion != ProductoVenta::APROBACION_APROBADO) {
                return response()->json([
                    'success' => false,
                    'message' => 'El producto debe estar aprobado primero. Estado actual: ' . $producto->estado_aprobacion
                ], 400);
            }
            
            $producto->update([
                'ActivoInactivo' => ProductoVenta::COMERCIAL_INACTIVO,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Producto desactivado correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en desactivar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ==================== CATÁLOGO DE LOS PRODUCTOS ====================
    public function catalogo(Request $request)
    {
        $clienteId = session('cliente_id');
        
        $query = ProductoVenta::where('IdCliente', $clienteId)
            ->with(['categoria']);
        
        if ($request->filled('categoria')) {
            $query->where('id_categoria', $request->categoria);
        }
        
        if ($request->filled('aprobacion') && $request->aprobacion !== '') {
            $query->where('estado_aprobacion', $request->aprobacion);
        }
        
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                ->orWhere('Detalle', 'like', "%{$search}%");
            });
        }
        
        $productos = $query->orderBy('Detalle')->paginate(20)->withQueryString();
        
        foreach ($productos as $producto) {
            if ($producto->ImagenProducto) {
                if (!str_starts_with($producto->ImagenProducto, 'http') && !str_starts_with($producto->ImagenProducto, '/storage')) {
                    $producto->imagen_url = '/storage/' . ltrim($producto->ImagenProducto, '/');
                } else {
                    $producto->imagen_url = $producto->ImagenProducto;
                }
            } else {
                $producto->imagen_url = null;
            }
        }
        
        $categorias = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get(['id_categoria as id', 'nombre']);
        
        $totalProductos = ProductoVenta::where('IdCliente', $clienteId)->count();
        $totalConCategoria = ProductoVenta::where('IdCliente', $clienteId)
            ->whereNotNull('id_categoria')
            ->count();
        $totalSinCategoria = $totalProductos - $totalConCategoria;
        
        $totalConImagen = ProductoVenta::where('IdCliente', $clienteId)
            ->whereNotNull('ImagenProducto')
            ->where('ImagenProducto', '!=', '')
            ->count();
        $totalSinImagen = $totalProductos - $totalConImagen;
        
        return Inertia::render('Gestion/Inventario/ProductosVenta/Catalogo', [
            'productos' => $productos,
            'categorias' => $categorias,
            'totalProductos' => $totalProductos,
            'totalConCategoria' => $totalConCategoria,
            'totalSinCategoria' => $totalSinCategoria,
            'totalConImagen' => $totalConImagen,
            'totalSinImagen' => $totalSinImagen,
            'filtros' => [
                'categoria' => $request->categoria,
                'aprobacion' => $request->aprobacion,
                'estado' => $request->estado,
                'search' => $request->search,
            ],
        ]);
    }
    
    // ==================== PRECIO SUCURSAL ====================
    
    public function storePrecioSucursal(Request $request)
    {
        $request->validate([
            'IdProducto' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Precio' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $existe = ProductoVentaPrecioSucursal::where('IdCliente', $clienteId)
            ->where('IdProducto', $request->IdProducto)
            ->where('IdSucursal', $request->IdSucursal)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un precio para esta sucursal'
            ], 422);
        }

        try {
            $precio = ProductoVentaPrecioSucursal::create([
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
                'PrecioDiferenciadoA' => 'Sucursal',
                'IdProducto' => $request->IdProducto,
                'Precio' => $request->Precio,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            $precio->load('sucursal');

            return response()->json([
                'success' => true,
                'message' => 'Precio agregado correctamente',
                'precio' => $precio
            ]);

        } catch (\Exception $e) {
            Log::error('Error en storePrecioSucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function updatePrecioSucursal(Request $request, $id)
    {
        $request->validate([
            'Precio' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $precio = ProductoVentaPrecioSucursal::where('IdCliente', $clienteId)
            ->where('IdPrecio', $id)
            ->firstOrFail();

        try {
            $precio->update([
                'Precio' => $request->Precio,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Precio actualizado correctamente',
                'precio' => $precio->load('sucursal')
            ]);

        } catch (\Exception $e) {
            Log::error('Error en updatePrecioSucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function destroyPrecioSucursal($id)
    {
        $clienteId = session('cliente_id');
        $precio = ProductoVentaPrecioSucursal::where('IdCliente', $clienteId)
            ->where('IdPrecio', $id)
            ->firstOrFail();
        
        try {
            $precio->delete();
            return response()->json([
                'success' => true,
                'message' => 'Precio eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // ==================== PRECIO MAYORISTA ====================
    
    public function storePrecioMayorista(Request $request)
    {
        $request->validate([
            'IdProducto' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'Precio' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $existe = ProductoVentaPrecioMayorista::where('IdCliente', $clienteId)
            ->where('IdProducto', $request->IdProducto)
            ->where('IdSucursal', $request->IdSucursal)
            ->where('IdIdentificador', $request->IdIdentificador)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un precio mayorista para esta combinación'
            ], 422);
        }

        try {
            $precio = ProductoVentaPrecioMayorista::create([
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
                'IdIdentificador' => $request->IdIdentificador,
                'IdProducto' => $request->IdProducto,
                'Precio' => $request->Precio,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            $precio->load(['sucursal', 'identificador']);

            return response()->json([
                'success' => true,
                'message' => 'Precio mayorista agregado correctamente',
                'precio' => $precio
            ]);

        } catch (\Exception $e) {
            Log::error('Error en storePrecioMayorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function updatePrecioMayorista(Request $request, $id)
    {
        $request->validate([
            'Precio' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $precio = ProductoVentaPrecioMayorista::where('IdCliente', $clienteId)
            ->where('IdPrecioMayorista', $id)
            ->firstOrFail();

        try {
            $precio->update([
                'Precio' => $request->Precio,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Precio mayorista actualizado correctamente',
                'precio' => $precio->load(['sucursal', 'identificador'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error en updatePrecioMayorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function destroyPrecioMayorista($id)
    {
        $clienteId = session('cliente_id');
        $precio = ProductoVentaPrecioMayorista::where('IdCliente', $clienteId)
            ->where('IdPrecioMayorista', $id)
            ->firstOrFail();
        
        try {
            $precio->delete();
            return response()->json([
                'success' => true,
                'message' => 'Precio mayorista eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // ==================== INVENTARIO DETALLE ====================
    
    public function storeDetalle(Request $request)
    {
        $request->validate([
            'IdDetalleProducto' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Porcion' => 'required|numeric|min:0.000001',
        ]);

        $operadorId = session('operador_id');

        $existe = RelacionVentaDetalle::where('IdDetalleProducto', $request->IdDetalleProducto)
            ->where('IdProducto', $request->IdProducto)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Este producto ya está agregado en el detalle'
            ], 422);
        }

        try {
            $detalle = RelacionVentaDetalle::create([
                'IdDetalleProducto' => $request->IdDetalleProducto,
                'IdProducto' => $request->IdProducto,
                'Porcion' => $request->Porcion,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            $detalle->load('producto');

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado correctamente',
                'detalle' => $detalle
            ]);

        } catch (\Exception $e) {
            Log::error('Error en storeDetalle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function updateDetalle(Request $request, $id)
    {
        $request->validate([
            'Porcion' => 'required|numeric|min:0.000001',
        ]);

        try {
            $detalle = RelacionVentaDetalle::findOrFail($id);
            $detalle->update([
                'Porcion' => $request->Porcion,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);

            $detalle->load('producto');

            return response()->json([
                'success' => true,
                'message' => 'Porción actualizada correctamente',
                'detalle' => $detalle
            ]);

        } catch (\Exception $e) {
            Log::error('Error en updateDetalle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function destroyDetalle($id)
    {
        try {
            $detalle = RelacionVentaDetalle::findOrFail($id);
            $detalle->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verificar si ya existe un producto con la misma composición
     */
    public function verificarComposicion(Request $request)
    {
        $request->validate([
            'productos_ids' => 'required|array|min:1',
            'productos_ids.*' => 'exists:inventario_productodetalle,IdProducto',
            'porciones' => 'required|array|min:1',
            'porciones.*' => 'numeric|min:0.000001',
            'excluir_id' => 'nullable|exists:inventario_relacion_ventainventario,IdDetalleProducto'
        ]);
        
        $clienteId = session('cliente_id');
        $productosIds = $request->productos_ids;
        $porciones = $request->porciones;
        
        $pares = [];
        for ($i = 0; $i < count($productosIds); $i++) {
            $pares[] = $productosIds[$i] . ':' . number_format((float)$porciones[$i], 6, '.', '');
        }
        sort($pares);
        $hashComposicion = implode('|', $pares);
        
        $existe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario as p')
            ->where('p.IdCliente', $clienteId)
            ->where('p.CierrePermanente', 0)
            ->whereExists(function($query) use ($hashComposicion, $clienteId) {
                $query->select(DB::raw('1'))
                    ->from('inventario_relacion_ventainventario_detalle as d')
                    ->whereRaw('d.IdDetalleProducto = p.IdDetalleProducto')
                    ->whereRaw('(
                        SELECT GROUP_CONCAT(CONCAT(IdProducto, ":", ROUND(Porcion, 6)) ORDER BY IdProducto SEPARATOR "|")
                        FROM inventario_relacion_ventainventario_detalle
                        WHERE IdDetalleProducto = p.IdDetalleProducto
                    ) = ?', [$hashComposicion]);
            })
            ->select('p.IdDetalleProducto as id', 'p.Detalle as nombre')
            ->first();
        
        if ($existe && (!$request->excluir_id || $existe->id != $request->excluir_id)) {
            return response()->json([
                'existe' => true,
                'producto' => [
                    'id' => $existe->id,
                    'nombre' => $existe->nombre
                ]
            ]);
        }
        
        return response()->json(['existe' => false]);
    }

    // ==================== UTILIDADES ====================
    
    /**
     * 🔥 NUEVA FUNCIÓN: Guardar imagen desde archivo subido
     */
    private function guardarImagenArchivo($file, $codigo)
    {
        try {
            \Log::info('=== GUARDANDO IMAGEN ===');
            \Log::info('Código: ' . $codigo);
            \Log::info('Archivo original: ' . $file->getClientOriginalName());
            \Log::info('Mime type: ' . $file->getMimeType());
            \Log::info('Tamaño: ' . $file->getSize() . ' bytes');
            
            $extension = $file->getClientOriginalExtension();
            $nombreArchivo = Str::slug($codigo, '_') . '_' . date('Ymd_His') . '.' . $extension;
            $rutaRelativa = '/storage/productos/' . $nombreArchivo;
            $rutaCompleta = public_path($rutaRelativa);
            
            $directorio = dirname($rutaCompleta);
            \Log::info('Directorio destino: ' . $directorio);
            
            if (!file_exists($directorio)) {
                \Log::info('Creando directorio...');
                if (!mkdir($directorio, 0755, true)) {
                    throw new \Exception('No se pudo crear el directorio: ' . $directorio);
                }
                \Log::info('Directorio creado exitosamente');
            }
            
            // Verificar permisos de escritura
            if (!is_writable($directorio)) {
                throw new \Exception('El directorio no tiene permisos de escritura: ' . $directorio);
            }
            
            \Log::info('Moviendo archivo a: ' . $rutaCompleta);
            $file->move($directorio, $nombreArchivo);
            
            if (!file_exists($rutaCompleta)) {
                throw new \Exception('El archivo no se guardó correctamente');
            }
            
            \Log::info('✅ Imagen guardada exitosamente en: ' . $rutaRelativa);
            
            return $rutaRelativa;
            
        } catch (\Exception $e) {
            \Log::error('❌ Error guardando imagen: ' . $e->getMessage());
            return null;
        }
    }

    // ==================== APROBACIÓN DE PRODUCTOS ====================
    
    public function enviarAprobacion($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        if ($producto->estado_aprobacion != ProductoVenta::APROBACION_BORRADOR && 
            $producto->estado_aprobacion != ProductoVenta::APROBACION_RECHAZADO) {
            return redirect()->back()->with('error', 'El producto no puede ser enviado a aprobación en su estado actual');
        }
        
        $aprobadores = ProductoAprobacionConfig::porContexto()
            ->activos()
            ->get();
        
        if ($aprobadores->isEmpty()) {
            return redirect()->back()->with('error', 'No hay aprobadores configurados. Contacte al administrador.');
        }
        
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            ProductoAprobacionSolicitud::where('IdDetalleProducto', $producto->IdDetalleProducto)->delete();
            
            $producto->update([
                'estado_aprobacion' => ProductoVenta::APROBACION_PENDIENTE,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);
            
            $solicitud = ProductoAprobacionSolicitud::create([
                'IdDetalleProducto' => $producto->IdDetalleProducto,
                'IdOperadorSolicita' => $operadorId,
                'Estado' => 'pendiente',
                'FechaSolicitud' => now(),
            ]);
            
            foreach ($aprobadores as $aprobador) {
                ProductoAprobacionVoto::create([
                    'IdProductoAprobacionSolicitud' => $solicitud->IdProductoAprobacionSolicitud,
                    'IdOperadorAprobador' => $aprobador->IdOperador,
                    'Estado' => 'pendiente',
                ]);
            }
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            return redirect()->route('gestion.productos-venta.index')
                ->with('success', 'Producto enviado a aprobación correctamente');
                
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al enviar a aprobación: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al enviar: ' . $e->getMessage());
        }
    }

    public function pendientesAprobacion()
    {
        $operadorId = session('operador_id');
        
        $solicitudes = ProductoAprobacionSolicitud::whereHas('votos', function($q) use ($operadorId) {
                $q->where('IdOperadorAprobador', $operadorId)
                ->where('Estado', 'pendiente');
            })
            ->with(['producto', 'solicitante.identificador', 'votos' => function($q) use ($operadorId) {
                $q->where('IdOperadorAprobador', $operadorId);
            }])
            ->orderBy('FechaSolicitud', 'desc')
            ->get();
        
        return Inertia::render('Gestion/Inventario/ProductoAprobacion/Pendientes', [
            'solicitudes' => $solicitudes,
        ]);
    }

    public function votarAprobacion(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:aprobado,rechazado',
            'comentario' => 'nullable|string|max:500',
        ]);
        
        $operadorId = session('operador_id');
        
        $voto = ProductoAprobacionVoto::where('IdProductoAprobacionVoto', $id)
            ->where('IdOperadorAprobador', $operadorId)
            ->firstOrFail();
        
        if ($voto->Estado != 'pendiente') {
            return redirect()->back()->with('error', 'Este producto ya fue procesado');
        }
        
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $voto->update([
                'Estado' => $request->estado,
                'Comentario' => $request->comentario,
                'FechaVoto' => now(),
            ]);
            
            $solicitud = $voto->solicitud;
            $todosVotos = $solicitud->votos;
            $pendientes = $todosVotos->where('Estado', 'pendiente')->count();
            $rechazados = $todosVotos->where('Estado', 'rechazado')->count();
            
            if ($rechazados > 0) {
                $solicitud->update([
                    'Estado' => 'rechazado',
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $solicitud->producto->update([
                    'estado_aprobacion' => ProductoVenta::APROBACION_RECHAZADO,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $mensaje = 'Producto rechazado';
                
            } elseif ($pendientes == 0) {
                $solicitud->update([
                    'Estado' => 'aprobado',
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $solicitud->producto->update([
                    'estado_aprobacion' => ProductoVenta::APROBACION_APROBADO,
                    'ActivoInactivo' => ProductoVenta::COMERCIAL_ACTIVO,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $mensaje = 'Producto aprobado y activado para la venta';
                
            } else {
                $mensaje = 'Voto registrado correctamente. Pendiente ' . $pendientes . ' aprobador(es) más.';
            }
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            return redirect()->back()->with('success', $mensaje);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al votar: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al procesar: ' . $e->getMessage());
        }
    }

    public function verAprobacion($id)
    {
        $producto = ProductoVenta::where('IdCliente', session('cliente_id'))
            ->with('categoria')
            ->findOrFail($id);
        
        $solicitud = ProductoAprobacionSolicitud::where('IdDetalleProducto', $producto->IdDetalleProducto)
            ->with(['solicitante.identificador', 'votos.aprobador.identificador'])
            ->first();
        
        $preciosSucursal = ProductoVentaPrecioSucursal::where('IdCliente', session('cliente_id'))
            ->where('IdProducto', $id)
            ->with('sucursal')
            ->get();
        
        $preciosMayorista = ProductoVentaPrecioMayorista::where('IdCliente', session('cliente_id'))
            ->where('IdProducto', $id)
            ->with(['sucursal', 'identificador'])
            ->get();
        
        $detallesInventario = RelacionVentaDetalle::where('IdDetalleProducto', $id)
            ->with('producto')
            ->get();
        
        return Inertia::render('Gestion/Inventario/ProductoAprobacion/Detalle', [
            'producto' => $producto,
            'solicitud' => $solicitud,
            'preciosSucursal' => $preciosSucursal,
            'preciosMayorista' => $preciosMayorista,
            'detallesInventario' => $detallesInventario,
        ]);
    }
}