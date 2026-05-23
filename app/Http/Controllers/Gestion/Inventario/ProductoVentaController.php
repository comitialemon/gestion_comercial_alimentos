<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Inventario\VentaGrupo;
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
        
        $query = ProductoVenta::where('IdCliente', $clienteId)->with('grupo', 'categoria'); // 🔥 AGREGAR 'categoria'
        
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }
        
        if ($request->filled('grupos')) {
            $gruposArray = explode(',', $request->grupos);
            $query->whereIn('IdVentaGrupo', $gruposArray);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                  ->orWhere('Detalle', 'like', "%{$search}%");
            });
        }
        
        $productos = $query->orderBy('Detalle')->paginate(20)->withQueryString();
        
        $grupos = VentaGrupo::where('IdCliente', $clienteId)
            ->withCount('productos')
            ->orderBy('Detalle')
            ->get(['IdVentaGrupo as id', 'Detalle as nombre']);
        
        $totalActivos = ProductoVenta::where('IdCliente', $clienteId)->where('ActivoInactivo', 0)->count();
        $totalInactivos = ProductoVenta::where('IdCliente', $clienteId)->where('ActivoInactivo', 1)->count();
        
        return Inertia::render('Gestion/Inventario/ProductosVenta/Index', [
            'productos' => $productos,
            'grupos' => $grupos,
            'totalActivos' => $totalActivos,
            'totalInactivos' => $totalInactivos,
            'filtros' => [
                'estado' => $request->estado,
                'grupos' => $request->grupos,
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

        $grupos = VentaGrupo::where('IdCliente', $clienteId)
            ->orderBy('Detalle')
            ->get(['IdVentaGrupo as id', 'Detalle as nombre']);
        
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
            'grupos' => $grupos,
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
        
        $grupos = VentaGrupo::where('IdCliente', $clienteId)
            ->orderBy('Detalle')
            ->get(['IdVentaGrupo as id', 'Detalle as nombre']);
        
        // 🔥 OBTENER CATEGORÍAS COMPLETAS
        $categorias = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get(); // 👈 SIN select
        
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
            'grupos' => $grupos,
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
            'IdVentaGrupo' => 'required|exists:inventario_relacion_ventainventario_grupouno,IdVentaGrupo',
            'id_categoria' => 'required|exists:inventario_menu_categoria,id_categoria',
            'Codigo' => 'required|string|max:100',
            'Detalle' => 'required|string|max:100',
            'NombreCortoFactura' => 'required|string|max:20',
            'PrecioVenta' => 'required|numeric|min:0',
            'imagen_base64' => 'nullable|string',
        ]);

        $codigoExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Codigo', $request->Codigo)
            ->exists();

        if ($codigoExiste) {
            return redirect()->back()
                ->withErrors(['Codigo' => 'El código ya existe.'])
                ->withInput();
        }

        $detalleExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Detalle', $request->Detalle)
            ->exists();

        if ($detalleExiste) {
            return redirect()->back()
                ->withErrors(['Detalle' => 'El detalle ya existe.'])
                ->withInput();
        }

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $imagenUrl = null;
            if ($request->imagen_base64) {
                $imagenUrl = $this->guardarImagen($request->imagen_base64, $request->Codigo);
            }

            // 🔥 CREAR PRODUCTO CON estado_aprobacion = APROBACION_BORRADOR (0)
            // 🔥 ActivoInactivo = COMERCIAL_INACTIVO (1)
            $producto = ProductoVenta::create([
                'IdVentaGrupo' => $request->IdVentaGrupo,
                'id_categoria' => $request->id_categoria,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'NombreCortoFactura' => $request->NombreCortoFactura,
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

            return redirect()->route('gestion.productos-venta.edit', $producto->IdDetalleProducto)
                ->with('success', 'Producto creado correctamente. Complete los datos adicionales y envíe a aprobación.');

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al crear producto: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al crear: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        // 🔥 VALIDAR QUE NO ESTÉ PENDIENTE DE APROBACIÓN
        if ($producto->estado_aprobacion == ProductoVenta::APROBACION_PENDIENTE) {
            return redirect()->back()->with('error', 'No se puede editar un producto pendiente de aprobación.');
        }
        
        $request->validate([
            'IdVentaGrupo' => 'required|exists:inventario_relacion_ventainventario_grupouno,IdVentaGrupo',
            'id_categoria' => 'required|exists:inventario_menu_categoria,id_categoria',
            'Codigo' => 'required|string|max:100',
            'Detalle' => 'required|string|max:100',
            'NombreCortoFactura' => 'required|string|max:20',
            'PrecioVenta' => 'required|numeric|min:0',
            'imagen_base64' => 'nullable|string',
        ]);
        
        // Validaciones de unicidad
        $codigoExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Codigo', $request->Codigo)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($codigoExiste) {
            return redirect()->back()
                ->withErrors(['Codigo' => 'El código ya existe.'])
                ->withInput();
        }
        
        $detalleExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Detalle', $request->Detalle)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($detalleExiste) {
            return redirect()->back()
                ->withErrors(['Detalle' => 'El detalle ya existe.'])
                ->withInput();
        }
        
        $nombreCortoExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('NombreCortoFactura', $request->NombreCortoFactura)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($nombreCortoExiste) {
            return redirect()->back()
                ->withErrors(['NombreCortoFactura' => 'El nombre para factura ya existe.'])
                ->withInput();
        }
        
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $imagenUrl = $producto->ImagenProducto;
            if ($request->imagen_base64 && !str_contains($request->imagen_base64, '/storage/')) {
                if ($imagenUrl && file_exists(public_path($imagenUrl))) {
                    unlink(public_path($imagenUrl));
                }
                $imagenUrl = $this->guardarImagen($request->imagen_base64, $request->Codigo);
            }
            
            // 🔥 NO MODIFICAR estado_aprobacion ni ActivoInactivo aquí
            $producto->update([
                'IdVentaGrupo' => $request->IdVentaGrupo,
                'id_categoria' => $request->id_categoria,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'NombreCortoFactura' => $request->NombreCortoFactura,
                'PrecioVenta' => $request->PrecioVenta,
                'ImagenProducto' => $imagenUrl,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            return redirect()->back()->with('success', 'Producto actualizado correctamente');
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al actualizar producto: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al actualizar: ' . $e->getMessage())
                ->withInput();
        }
    }
    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        // 🔥 CORREGIDO: Usar COMERCIAL_INACTIVO en lugar de ESTADO_INACTIVO
        if ($producto->ActivoInactivo != ProductoVenta::COMERCIAL_INACTIVO) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un producto que ya ha sido enviado a aprobación'
            ], 400);
        }
        
        try {
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
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        // 🔥 Solo se puede activar si está APROBADO
        if ($producto->estado_aprobacion != ProductoVenta::APROBACION_APROBADO) {
            return redirect()->back()->with('error', 'El producto debe estar aprobado primero');
        }
        
        $tieneDetalle = RelacionVentaDetalle::where('IdDetalleProducto', $id)->exists();
        
        if (!$tieneDetalle) {
            return redirect()->back()->with('error', 'No se puede activar. El producto no tiene relación con inventario.');
        }
        
        $producto->update([
            'ActivoInactivo' => ProductoVenta::COMERCIAL_ACTIVO,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => now(),
        ]);
        
        return redirect()->back()->with('success', 'Producto activado correctamente');
    }
    public function desactivar($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        // 🔥 Solo se puede desactivar si está APROBADO
        if ($producto->estado_aprobacion != ProductoVenta::APROBACION_APROBADO) {
            return redirect()->back()->with('error', 'El producto debe estar aprobado primero');
        }
        
        $producto->update([
            'ActivoInactivo' => ProductoVenta::COMERCIAL_INACTIVO,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => now(),
        ]);
        
        return redirect()->back()->with('success', 'Producto desactivado correctamente');
    }
    public function catalogo(Request $request)
    {
        $clienteId = session('cliente_id');
        
        $query = ProductoVenta::where('IdCliente', $clienteId)
            ->with(['grupo', 'categoria']);
        
        // Filtro por categoría
        if ($request->filled('categoria')) {
            $query->where('id_categoria', $request->categoria);
        }
        
        // Filtro por grupo
        if ($request->filled('grupo')) {
            $query->where('IdVentaGrupo', $request->grupo);
        }
        
        // 🔥 Filtro por estado de aprobación
        if ($request->filled('aprobacion') && $request->aprobacion !== '') {
            $query->where('estado_aprobacion', $request->aprobacion);
        }
        
        // 🔥 Filtro por estado comercial (Activo/Inactivo)
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
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
        
        $categorias = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get(['id_categoria as id', 'nombre']);
        
        $grupos = VentaGrupo::where('IdCliente', $clienteId)
            ->orderBy('Detalle')
            ->get(['IdVentaGrupo as id', 'Detalle as nombre']);
        
        $totalProductos = ProductoVenta::where('IdCliente', $clienteId)->count();
        $totalConCategoria = ProductoVenta::where('IdCliente', $clienteId)
            ->whereNotNull('id_categoria')
            ->count();
        $totalSinCategoria = $totalProductos - $totalConCategoria;
        
        return Inertia::render('Gestion/Inventario/ProductosVenta/Catalogo', [
            'productos' => $productos,
            'categorias' => $categorias,
            'grupos' => $grupos,
            'totalProductos' => $totalProductos,
            'totalConCategoria' => $totalConCategoria,
            'totalSinCategoria' => $totalSinCategoria,
            'filtros' => [
                'categoria' => $request->categoria,
                'grupo' => $request->grupo,
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
            \Log::error('Error en storePrecioSucursal: ' . $e->getMessage());
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
            \Log::error('Error en updatePrecioSucursal: ' . $e->getMessage());
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
            \Log::error('Error en storePrecioMayorista: ' . $e->getMessage());
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
            \Log::error('Error en updatePrecioMayorista: ' . $e->getMessage());
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
            \Log::error('Error en storeDetalle: ' . $e->getMessage());
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
            \Log::error('Error en updateDetalle: ' . $e->getMessage());
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
     * Verificar si ya existe un producto con la misma composición (mismos productos y mismas porciones)
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
        
        // Construir pares (IdProducto:Porcion) ordenados
        $pares = [];
        for ($i = 0; $i < count($productosIds); $i++) {
            $pares[] = $productosIds[$i] . ':' . number_format((float)$porciones[$i], 6, '.', '');
        }
        sort($pares);
        $hashComposicion = implode('|', $pares);
        
        // Buscar productos con la misma composición
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
    
    private function guardarImagen($base64, $codigo)
    {
        if (str_contains($base64, 'base64,')) {
            $base64 = explode('base64,', $base64)[1];
        }
        
        $image = base64_decode($base64);
        $finfo = finfo_open();
        $mimeType = finfo_buffer($finfo, $image, FILEINFO_MIME_TYPE);
        finfo_close($finfo);
        
        $extension = match($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg'
        };
        
        $nombreArchivo = Str::slug($codigo, '_') . '_' . date('Ymd_His') . '.' . $extension;
        $rutaRelativa = '/storage/productos/' . $nombreArchivo;
        $rutaCompleta = public_path($rutaRelativa);
        
        $directorio = dirname($rutaCompleta);
        if (!file_exists($directorio)) {
            mkdir($directorio, 0755, true);
        }
        
        file_put_contents($rutaCompleta, $image);
        
        return $rutaRelativa;
    }

    //========= APROBACIÓN DE PRODUCTOS =========
    public function enviarAprobacion($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        // 🔥 Solo se puede enviar desde BORRADOR o RECHAZADO
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
            
            // Eliminar solicitud anterior si existe
            ProductoAprobacionSolicitud::where('IdDetalleProducto', $producto->IdDetalleProducto)->delete();
            
            // 🔥 Cambiar estado a PENDIENTE
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
                // 🔥 Alguien rechazó -> solicitud rechazada
                $solicitud->update([
                    'Estado' => 'rechazado',
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                // 🔥 Producto RECHAZADO (estado_aprobacion = 3)
                $solicitud->producto->update([
                    'estado_aprobacion' => ProductoVenta::APROBACION_RECHAZADO,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $mensaje = 'Producto rechazado';
                
            } elseif ($pendientes == 0) {
                // 🔥 Todos aprobaron -> solicitud aprobada y producto activado
                $solicitud->update([
                    'Estado' => 'aprobado',
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                // 🔥 Producto APROBADO (estado_aprobacion = 2) y comercialmente ACTIVO (ActivoInactivo = 0)
                $solicitud->producto->update([
                    'estado_aprobacion' => ProductoVenta::APROBACION_APROBADO,
                    'ActivoInactivo' => ProductoVenta::COMERCIAL_ACTIVO,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $mensaje = 'Producto aprobado y activado para la venta';
                
            } else {
                // 🔥 Aún faltan votos
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
            ->with('grupo', 'categoria') // 🔥 AGREGAR 'categoria'
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