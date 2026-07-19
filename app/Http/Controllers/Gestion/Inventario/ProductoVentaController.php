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
use App\Models\Gestion\Inventario\ProductoImagen;
use App\Models\Gestion\Inventario\ProductoAprobacionConfig;
use App\Models\Gestion\Inventario\ProductoAprobacionSolicitud;
use App\Models\Gestion\Inventario\ProductoAprobacionVoto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProductoVentaController extends Controller
{
    private $imageManager;
    private $useWebP;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->useWebP = function_exists('imagewebp');
        
        Log::info('📸 Sistema de imágenes iniciado', [
            'webp_soportado' => $this->useWebP,
            'formato_principal' => $this->useWebP ? 'WebP' : 'JPEG'
        ]);
    }

    // ==================== MÉTODOS PÚBLICOS ====================

    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $query = ProductoVenta::where('IdCliente', $clienteId)
            ->with(['categoria']);
        
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }
        
        if ($request->filled('categorias')) {
            $categoriasArray = explode(',', $request->categorias);
            $query->whereIn('id_categoria', $categoriasArray);
        }
        
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
            ->get()
            ->map(function($categoria) use ($clienteId, $request) {
                $query = ProductoVenta::where('IdCliente', $clienteId)
                    ->where('id_categoria', $categoria->id_categoria);
                
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function($q) use ($search) {
                        $q->where('Codigo', 'like', "%{$search}%")
                        ->orWhere('Detalle', 'like', "%{$search}%");
                    });
                }
                
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
        
        // 🔥 CORREGIDO: Traer TODOS los productos de inventario activos
        $productosInventario = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Codigo')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);
        
        return Inertia::render('Gestion/Inventario/ProductosVenta/Create', [
            'categorias' => $categorias,
            'sucursales' => $sucursales,
            'identificadores' => $identificadores,
            'productosInventario' => $productosInventario,
            'clienteId' => $clienteId, // 🔥 AGREGAR ESTA LÍNEA

        ]);
    }

    public function edit($id)
    {
        $clienteId = session('cliente_id');
        
        $producto = ProductoVenta::where('IdCliente', $clienteId)
            ->with(['categoria', 'imagenes'])
            ->findOrFail($id);
        
        if ($producto->imagenes->isNotEmpty()) {
            $principal = $producto->imagenes->firstWhere('EsPrincipal', true) ?? $producto->imagenes->first();
            $producto->ImagenProducto = $principal->RutaThumbnail;
        }
        
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
        
        $categorias = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get();
        
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);
        
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);
        
        // 🔥 CORREGIDO: Traer TODOS los productos de inventario activos
        $productosInventario = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
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
            'clienteId' => $clienteId, // 🔥 AGREGAR ESTA LÍNEA

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
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
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

            $producto = ProductoVenta::create([
                'id_categoria' => $request->id_categoria,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'PrecioVenta' => $request->PrecioVenta,
                'ActivoInactivo' => ProductoVenta::COMERCIAL_INACTIVO,
                'estado_aprobacion' => ProductoVenta::APROBACION_BORRADOR,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
                'CierrePermanente' => 0,
            ]);

            $categoria = CategoriaProducto::find($request->id_categoria);

            $imagenData = null;
            if ($request->hasFile('imagen')) {
                $imagenData = $this->guardarImagenOptimizada(
                    $request->file('imagen'),
                    $producto->IdDetalleProducto,
                    $request->Codigo,
                    $categoria,
                    $clienteId,
                    $sucursalId,
                    $operadorId,
                    true
                );
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            $productoConImagenes = ProductoVenta::with('imagenes')
                ->find($producto->IdDetalleProducto);

            return response()->json([
                'success' => true,
                'message' => 'Producto creado correctamente',
                'producto_id' => $producto->IdDetalleProducto,
                'producto' => $productoConImagenes,
                'imagen' => $imagenData
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al crear producto: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
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
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'eliminar_imagen' => 'nullable|boolean'
        ]);
        
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
            
            $categoriaNueva = CategoriaProducto::find($request->id_categoria);
            $operadorId = session('operador_id');
            $sucursalId = session('cliente_sucursal_id');

            if ($request->boolean('eliminar_imagen')) {
                $imagenPrincipal = ProductoImagen::where('IdProducto', $id)
                    ->where('EsPrincipal', 1)
                    ->first();
                
                if ($imagenPrincipal) {
                    $this->eliminarArchivosImagen($imagenPrincipal);
                    $imagenPrincipal->delete();
                    Log::info('🗑️ Imagen principal eliminada');
                }
            }

            if ($request->hasFile('imagen')) {
                $imagenAnterior = ProductoImagen::where('IdProducto', $id)
                    ->where('EsPrincipal', 1)
                    ->first();
                
                if ($imagenAnterior) {
                    $this->eliminarArchivosImagen($imagenAnterior);
                    $imagenAnterior->delete();
                    Log::info('🗑️ Imagen anterior eliminada');
                }
                
                $this->guardarImagenOptimizada(
                    $request->file('imagen'),
                    $producto->IdDetalleProducto,
                    $request->Codigo,
                    $categoriaNueva,
                    $clienteId,
                    $sucursalId,
                    $operadorId,
                    true
                );
                Log::info('✅ Nueva imagen guardada');
            }
            
            if ($producto->id_categoria != $request->id_categoria) {
                $imagenes = ProductoImagen::where('IdProducto', $id)
                    ->where('ActivoInactivo', 1)
                    ->get();
                
                foreach ($imagenes as $imagen) {
                    $this->moverImagenesCategoria($imagen, $categoriaNueva, $clienteId);
                }
                Log::info('📦 Imágenes movidas a nueva categoría');
            }
            
            $producto->update([
                'id_categoria' => $request->id_categoria,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'PrecioVenta' => $request->PrecioVenta,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            $productoActualizado = ProductoVenta::where('IdCliente', $clienteId)
                ->with(['categoria', 'imagenes'])
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado correctamente',
                'producto' => $productoActualizado
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al actualizar producto: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
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
            $imagenes = ProductoImagen::where('IdProducto', $id)->get();
            foreach ($imagenes as $imagen) {
                $this->eliminarArchivosImagen($imagen);
                $imagen->delete();
            }
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $id)
                ->delete();
            
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

    public function catalogo(Request $request)
    {
        $clienteId = session('cliente_id');
        
        $query = ProductoVenta::where('IdCliente', $clienteId)
            ->with(['categoria', 'imagenes']);
        
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
            $imagenPrincipal = $producto->imagenes->firstWhere('EsPrincipal', true) ?? $producto->imagenes->first();
            
            if ($imagenPrincipal) {
                $producto->imagen_url = $imagenPrincipal->url_thumbnail;
                $producto->ImagenProducto = $imagenPrincipal->RutaThumbnail;
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
            ->whereHas('imagenes')
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

    /**
     * Verificar si existe un aprobador configurado para el cliente
     */
    public function verificarAprobador()
    {
        try {
            $clienteId = session('cliente_id');
            
            if (!$clienteId) {
                return response()->json([
                    'existe' => false,
                    'mensaje' => 'No hay un cliente seleccionado'
                ]);
            }
            
            // 🔥 BUSCAR DIRECTO EN LA BASE DE DATOS (SIN SCOPES)
            $configuracion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('producto_aprobacion_config')
                ->where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 0)
                ->first();
            
            if (!$configuracion) {
                return response()->json([
                    'existe' => false,
                    'mensaje' => 'No hay un responsable configurado para aprobar productos. Contacte al administrador.'
                ]);
            }
            
            // Obtener el nombre del operador aprobador
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $configuracion->IdOperador)
                ->first();
            
            $nombreOperador = null;
            if ($operador) {
                $identificador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_identificador')
                    ->where('IdIdentificador', $operador->IdIdentificador)
                    ->first();
                $nombreOperador = $identificador ? $identificador->Nombre : 'No definido';
            }
            
            return response()->json([
                'existe' => true,
                'aprobador' => $nombreOperador ?? 'No definido',
                'mensaje' => 'Los productos serán enviados a aprobación por: ' . ($nombreOperador ?? 'No definido')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en verificarAprobador: ' . $e->getMessage());
            
            // 🔥 RETORNAR false PARA QUE NO BLOQUEE
            return response()->json([
                'existe' => false,
                'mensaje' => 'Error al verificar aprobador'
            ]);
        }
    }
    /**
     * Verificar si ya existe un producto con la misma composición
     */
    public function verificarComposicion(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            
            $request->validate([
                'productos_ids' => 'required|array',
                'productos_ids.*' => 'exists:inventario_productodetalle,IdProducto',
                'excluir_id' => 'nullable|exists:inventario_relacion_ventainventario,IdDetalleProducto'
            ]);
            
            $productosIds = $request->productos_ids;
            $excluirId = $request->excluir_id;
            sort($productosIds);
            
            $productosVenta = ProductoVenta::where('IdCliente', $clienteId)
                ->where('estado_aprobacion', '!=', ProductoVenta::APROBACION_RECHAZADO)
                ->where('IdDetalleProducto', '!=', $excluirId)
                ->get();
            
            foreach ($productosVenta as $producto) {
                $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_detalle')
                    ->where('IdDetalleProducto', $producto->IdDetalleProducto)
                    ->pluck('IdProducto')
                    ->toArray();
                
                sort($detalles);
                
                if ($detalles === $productosIds) {
                    return response()->json([
                        'existe' => true,
                        'producto' => [
                            'IdDetalleProducto' => $producto->IdDetalleProducto,
                            'Codigo' => $producto->Codigo,
                            'Detalle' => $producto->Detalle
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'existe' => false
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error verificando composición: ' . $e->getMessage());
            
            return response()->json([
                'existe' => false
            ]);
        }
    }

    // ==================== NUEVOS MÉTODOS AGREGADOS ====================
    /**
     * Store - Guardar detalle de inventario (producto que compone este producto)
     */
    public function storeDetalle(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        Log::info('📝 storeDetalle - Datos recibidos:', $request->all());

        $request->validate([
            'IdDetalleProducto' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Porcion' => 'required|numeric|min:0.000001',
        ]);

        try {
            // Verificar que el producto principal existe
            $productoPrincipal = ProductoVenta::where('IdCliente', $clienteId)
                ->where('IdDetalleProducto', $request->IdDetalleProducto)
                ->first();

            if (!$productoPrincipal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Verificar que el producto de inventario existe
            $productoInventario = ProductoDetalle::where('IdCliente', $clienteId)
                ->where('IdProducto', $request->IdProducto)
                ->first();

            if (!$productoInventario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto de inventario no encontrado'
                ], 404);
            }

            // Verificar duplicado
            $existe = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $request->IdDetalleProducto)
                ->where('IdProducto', $request->IdProducto)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este producto ya está en el detalle'
                ], 422);
            }

            // Insertar detalle (sin IdCliente e IdSucursal)
            $id = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->insertGetId([
                    'IdDetalleProducto' => $request->IdDetalleProducto,
                    'IdProducto' => $request->IdProducto,
                    'Porcion' => $request->Porcion,
                    'IdOperadorInserta' => $operadorId,
                    'FechaInserta' => now(),
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);

            // 🔥 Obtener el detalle con el formato que espera el frontend
            $detalle = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle as d')
                ->join('inventario_productodetalle as p', 'd.IdProducto', '=', 'p.IdProducto')
                ->where('d.IdDetalleProductoPorcion', $id)
                ->select(
                    'd.IdDetalleProductoPorcion',
                    'd.IdDetalleProducto',
                    'd.IdProducto',
                    'd.Porcion',
                    'p.Codigo',
                    'p.Descripcion'
                )
                ->first();

            // 🔥 Formatear para que coincida con lo que espera el frontend
            $detalleFormateado = [
                'IdDetalleProductoPorcion' => $detalle->IdDetalleProductoPorcion,
                'IdDetalleProducto' => $detalle->IdDetalleProducto,
                'IdProducto' => $detalle->IdProducto,
                'Porcion' => (float) $detalle->Porcion,
                'producto' => [
                    'Codigo' => $detalle->Codigo,
                    'Descripcion' => $detalle->Descripcion
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al detalle correctamente',
                'detalle' => $detalleFormateado
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error al guardar detalle: ' . $e->getMessage());
            Log::error('❌ Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update - Actualizar porción del detalle
     */
    public function updateDetalle(Request $request, $id)
    {
        $request->validate([
            'Porcion' => 'required|numeric|min:0.000001',
        ]);

        try {
            // 🔥 CORREGIDO: Eliminar where('IdCliente') porque no existe en la tabla
            $updated = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProductoPorcion', $id)
                ->update([
                    'Porcion' => $request->Porcion,
                    'IdOperadorActualiza' => session('operador_id'),
                    'FechaActualiza' => now(),
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Porción actualizada correctamente'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se encontró el detalle'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error al actualizar detalle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Destroy - Eliminar detalle
     */
    public function destroyDetalle($id)
    {
        try {
            // 🔥 CORREGIDO: Eliminar where('IdCliente') porque no existe en la tabla
            $deleted = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProductoPorcion', $id)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Producto eliminado del detalle correctamente'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se encontró el detalle'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error al eliminar detalle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PARA PRECIO SUCURSAL ====================

    /**
     * Store - Guardar precio por sucursal
     */
    public function storePrecioSucursal(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'IdProducto' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Precio' => 'required|numeric|min:0',
        ]);

        try {
            // Verificar que no esté duplicado
            $existe = ProductoVentaPrecioSucursal::where('IdCliente', $clienteId)
                ->where('IdProducto', $request->IdProducto)
                ->where('IdSucursal', $request->IdSucursal)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este precio ya existe para esta sucursal'
                ], 422);
            }

            $precio = ProductoVentaPrecioSucursal::create([
                'IdProducto' => $request->IdProducto,
                'IdSucursal' => $request->IdSucursal,
                'Precio' => $request->Precio,
                'PrecioDiferenciadoA' => 'Sucursal',
                'IdCliente' => $clienteId,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            // Cargar relación con sucursal
            $precio->load('sucursal');

            return response()->json([
                'success' => true,
                'message' => 'Precio por sucursal guardado correctamente',
                'precio' => $precio
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar precio sucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update - Actualizar precio por sucursal
     */
    public function updatePrecioSucursal(Request $request, $id)
    {
        $clienteId = session('cliente_id');

        $request->validate([
            'Precio' => 'required|numeric|min:0',
        ]);

        try {
            $precio = ProductoVentaPrecioSucursal::where('IdCliente', $clienteId)
                ->where('IdPrecioSucursal', $id)
                ->firstOrFail();

            $precio->update([
                'Precio' => $request->Precio,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Precio por sucursal actualizado correctamente',
                'precio' => $precio->load('sucursal')
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar precio sucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Destroy - Eliminar precio por sucursal
     */
    public function destroyPrecioSucursal($id)
    {
        $clienteId = session('cliente_id');

        try {
            $precio = ProductoVentaPrecioSucursal::where('IdCliente', $clienteId)
                ->where('IdPrecioSucursal', $id)
                ->firstOrFail();

            $precio->delete();

            return response()->json([
                'success' => true,
                'message' => 'Precio por sucursal eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar precio sucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PARA PRECIO MAYORISTA ====================

    /**
     * Store - Guardar precio mayorista
     */
    public function storePrecioMayorista(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'IdProducto' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'Precio' => 'required|numeric|min:0',
        ]);

        try {
            // Verificar que no esté duplicado
            $existe = ProductoVentaPrecioMayorista::where('IdCliente', $clienteId)
                ->where('IdProducto', $request->IdProducto)
                ->where('IdSucursal', $request->IdSucursal)
                ->where('IdIdentificador', $request->IdIdentificador)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este precio mayorista ya existe para este comisionista'
                ], 422);
            }

            $precio = ProductoVentaPrecioMayorista::create([
                'IdProducto' => $request->IdProducto,
                'IdSucursal' => $request->IdSucursal,
                'IdIdentificador' => $request->IdIdentificador,
                'Precio' => $request->Precio,
                'IdCliente' => $clienteId,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            // Cargar relaciones
            $precio->load(['sucursal', 'identificador']);

            return response()->json([
                'success' => true,
                'message' => 'Precio mayorista guardado correctamente',
                'precio' => $precio
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar precio mayorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update - Actualizar precio mayorista
     */
    public function updatePrecioMayorista(Request $request, $id)
    {
        $clienteId = session('cliente_id');

        $request->validate([
            'Precio' => 'required|numeric|min:0',
        ]);

        try {
            $precio = ProductoVentaPrecioMayorista::where('IdCliente', $clienteId)
                ->where('IdPrecioMayorista', $id)
                ->firstOrFail();

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
            Log::error('Error al actualizar precio mayorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Destroy - Eliminar precio mayorista
     */
    public function destroyPrecioMayorista($id)
    {
        $clienteId = session('cliente_id');

        try {
            $precio = ProductoVentaPrecioMayorista::where('IdCliente', $clienteId)
                ->where('IdPrecioMayorista', $id)
                ->firstOrFail();

            $precio->delete();

            return response()->json([
                'success' => true,
                'message' => 'Precio mayorista eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar precio mayorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar producto
     */
    public function activar($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        // Verificar que tenga detalle de inventario
        $tieneDetalle = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario_detalle')
            ->where('IdDetalleProducto', $id)
            ->exists();
        
        if (!$tieneDetalle) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede activar. El producto no tiene relación con inventario.'
            ], 422);
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
    }

    /**
     * Desactivar producto
     */
    public function desactivar($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        $producto->update([
            'ActivoInactivo' => ProductoVenta::COMERCIAL_INACTIVO,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Producto desactivado correctamente'
        ]);
    }

    // ==================== MÉTODOS PRIVADOS PARA IMÁGENES ====================

    /**
     * 🔥 GUARDAR IMAGEN OPTIMIZADA (3 VERSIONES)
     * 
     * ESTRUCTURA: /storage/cliente_{id}/productos/{nombre_producto}/imagenes/
     *   ├── original/
     *   ├── medium/
     *   └── thumbnails/
     */
    private function guardarImagenOptimizada($file, $productoId, $codigo, $categoria, $clienteId, $sucursalId, $operadorId, $esPrincipal = false)
    {
        try {
            Log::info('=== PROCESANDO IMAGEN OPTIMIZADA ===');
            Log::info('Producto ID: ' . $productoId);
            Log::info('Código: ' . $codigo);
            Log::info('Cliente ID: ' . $clienteId);
            Log::info('Tamaño original: ' . $this->formatBytes($file->getSize()));

            // 1. Cargar imagen
            $imagen = $this->imageManager->read($file->getPathname());
            
            $anchoOriginal = $imagen->width();
            $altoOriginal = $imagen->height();
            Log::info("Dimensiones originales: {$anchoOriginal}x{$altoOriginal}");

            // 2. Generar nombre del producto (limpio)
            $nombreProducto = Str::slug($codigo . '-' . $this->limpiarTexto($categoria?->nombre ?? 'sin-categoria'), '_');
            $timestamp = date('Ymd_His');
            $extension = $this->useWebP ? 'webp' : 'jpg';

            // 3. 🔥 ESTRUCTURA: cliente_{id}/productos/{nombre_producto}/imagenes/
            $rutaBase = sprintf(
                '/storage/cliente_%d/productos/%s/imagenes',
                $clienteId,
                $nombreProducto
            );

            Log::info('📁 Ruta base: ' . $rutaBase);

            // 4. CREAR 3 VERSIONES
            $rutas = [];

            // THUMBNAIL (150x150) - Calidad 70
            $thumbnail = clone $imagen;
            $thumbnail->cover(150, 150);
            $rutaThumb = $rutaBase . '/thumbnails/' . $productoId . '_' . $timestamp . '_thumb.' . $extension;
            $this->guardarImagen($thumbnail, $rutaThumb, 70, $extension);
            $rutas['thumbnail'] = $rutaThumb;
            Log::info('✅ Thumbnail generado: 150x150');

            // MEDIUM (600x400) - Calidad 80
            $medium = clone $imagen;
            $medium->resize(600, 400, function($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $rutaMedium = $rutaBase . '/medium/' . $productoId . '_' . $timestamp . '_medium.' . $extension;
            $this->guardarImagen($medium, $rutaMedium, 80, $extension);
            $rutas['medium'] = $rutaMedium;
            Log::info('✅ Medium generado: 600x400');

            // ORIGINAL OPTIMIZADO (1200x900) - Calidad 85
            $original = clone $imagen;
            $original->resize(1200, 900, function($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $rutaOriginal = $rutaBase . '/original/' . $productoId . '_' . $timestamp . '_original.' . $extension;
            $this->guardarImagen($original, $rutaOriginal, 85, $extension);
            $rutas['original'] = $rutaOriginal;
            Log::info('✅ Original optimizado: 1200x900');

            // 5. CALCULAR PESOS
            $pesoOriginal = $file->getSize();
            $pesoTotal = 0;
            foreach ($rutas as $tipo => $ruta) {
                $rutaCompleta = public_path($ruta);
                if (file_exists($rutaCompleta)) {
                    $pesoTotal += filesize($rutaCompleta);
                }
            }
            $porcentajeAhorro = round((1 - ($pesoTotal / $pesoOriginal)) * 100);

            Log::info('📊 RESUMEN OPTIMIZACIÓN:');
            Log::info('   Original: ' . $this->formatBytes($pesoOriginal));
            Log::info('   Total 3 versiones: ' . $this->formatBytes($pesoTotal));
            Log::info('   Ahorro: ' . $porcentajeAhorro . '%');

            // 6. GUARDAR EN BASE DE DATOS
            $imagenRegistro = ProductoImagen::create([
                'IdProducto' => $productoId,
                'NombreArchivo' => $productoId . '_' . $timestamp . '.' . $extension,
                'RutaOriginal' => $rutaOriginal,
                'RutaMedium' => $rutaMedium,
                'RutaThumbnail' => $rutaThumb,
                'Orden' => 0,
                'EsPrincipal' => $esPrincipal,
                'TamanioKB' => round($pesoOriginal / 1024),
                'Ancho' => $anchoOriginal,
                'Alto' => $altoOriginal,
                'ActivoInactivo' => 1,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperadorRegistro' => $operadorId,
                'FechaRegistro' => now(),
            ]);

            Log::info('✅ Registro creado en BD. ID: ' . $imagenRegistro->IdImagen);

            return [
                'id' => $imagenRegistro->IdImagen,
                'thumbnail' => asset($rutaThumb),
                'medium' => asset($rutaMedium),
                'original' => asset($rutaOriginal),
                'peso_original' => $this->formatBytes($pesoOriginal),
                'peso_total' => $this->formatBytes($pesoTotal),
                'ahorro' => $porcentajeAhorro . '%'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error guardando imagen: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * 🔥 Guardar imagen en formato WebP o JPEG
     */
    private function guardarImagen($imagen, $rutaRelativa, $calidad, $extension = 'webp')
    {
        try {
            $rutaCompleta = public_path($rutaRelativa);
            $carpeta = dirname($rutaCompleta);
            
            if (!file_exists($carpeta)) {
                mkdir($carpeta, 0755, true);
                Log::info('📁 Carpeta creada: ' . $carpeta);
            }

            if ($extension === 'webp' && $this->useWebP) {
                $imagen->toWebp($calidad)->save($rutaCompleta);
            } else {
                $imagen->toJpeg($calidad)->save($rutaCompleta);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('❌ Error guardando archivo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 🔥 Eliminar archivos físicos de una imagen (3 versiones)
     */
    private function eliminarArchivosImagen($imagen)
    {
        $rutas = [
            $imagen->RutaOriginal,
            $imagen->RutaMedium,
            $imagen->RutaThumbnail
        ];

        foreach ($rutas as $ruta) {
            if ($ruta) {
                $rutaCompleta = public_path($ruta);
                if (file_exists($rutaCompleta)) {
                    unlink($rutaCompleta);
                    Log::info('🗑️ Archivo eliminado: ' . $rutaCompleta);
                }
            }
        }

        $carpetasBase = [
            dirname(public_path($imagen->RutaOriginal)),
            dirname(public_path($imagen->RutaMedium)),
            dirname(public_path($imagen->RutaThumbnail))
        ];

        foreach ($carpetasBase as $carpeta) {
            if (is_dir($carpeta)) {
                $this->eliminarCarpetaSiVacia($carpeta, true);
            }
        }
    }

    /**
     * 🔥 Mover imágenes a nueva categoría
     */
    private function moverImagenesCategoria($imagen, $categoriaNueva, $clienteId)
    {
        try {
            $nombreProducto = Str::slug($imagen->producto->Codigo . '-' . $this->limpiarTexto($categoriaNueva?->nombre ?? 'sin-categoria'), '_');
            $nuevaRutaBase = sprintf(
                '/storage/cliente_%d/productos/%s/imagenes',
                $clienteId,
                $nombreProducto
            );

            $versiones = [
                'original' => $imagen->RutaOriginal,
                'medium' => $imagen->RutaMedium,
                'thumbnail' => $imagen->RutaThumbnail
            ];

            foreach ($versiones as $tipo => $rutaActual) {
                if (!$rutaActual) continue;

                $nombreArchivo = basename($rutaActual);
                $nuevaRuta = $nuevaRutaBase . '/' . $tipo . 's/' . $nombreArchivo;
                $rutaOrigen = public_path($rutaActual);
                $rutaDestino = public_path($nuevaRuta);

                $carpetaDestino = dirname($rutaDestino);
                if (!file_exists($carpetaDestino)) {
                    mkdir($carpetaDestino, 0755, true);
                }

                if (file_exists($rutaOrigen)) {
                    rename($rutaOrigen, $rutaDestino);
                    Log::info('📦 Archivo movido: ' . $rutaActual . ' → ' . $nuevaRuta);
                }

                $campo = 'Ruta' . ucfirst($tipo);
                $imagen->$campo = $nuevaRuta;
            }

            $imagen->save();
            Log::info('✅ Imágenes movidas a nueva categoría: ' . $nombreProducto);
            return true;

        } catch (\Exception $e) {
            Log::error('❌ Error moviendo imágenes: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔥 eliminarCarpetaSiVacia - Elimina carpetas vacías recursivamente
     */
    private function eliminarCarpetaSiVacia($carpeta, $recursivo = true)
    {
        try {
            if (!is_dir($carpeta)) {
                return false;
            }
            
            $archivos = glob($carpeta . '/*');
            $archivosOcultos = glob($carpeta . '/.*');
            $archivosOcultos = array_filter($archivosOcultos, function($item) {
                $nombre = basename($item);
                return $nombre !== '.' && $nombre !== '..';
            });
            
            $totalArchivos = count($archivos) + count($archivosOcultos);
            
            if ($totalArchivos === 0) {
                Log::info('📁 Eliminando carpeta vacía: ' . $carpeta);
                rmdir($carpeta);
                
                if ($recursivo) {
                    $carpetaPadre = dirname($carpeta);
                    if (strpos($carpetaPadre, '/storage/cliente') !== false) {
                        $this->eliminarCarpetaSiVacia($carpetaPadre, true);
                    }
                }
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::warning('⚠️ No se pudo eliminar carpeta: ' . $carpeta . ' - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔥 Limpiar texto para nombres de carpeta
     */
    private function limpiarTexto($texto)
    {
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $texto);
        $texto = preg_replace('/[^a-zA-Z0-9\-_]/', '', $texto);
        return $texto;
    }

    /**
     * 🔥 Formatear bytes a formato legible
     */
    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // ==================== MÉTODOS PARA DISPONIBILIDAD POR DÍAS ====================
    /**
     * Obtener días de disponibilidad de un producto
     */
    public function getDisponibilidadDias($idProducto)
    {
        try {
            $clienteId = session('cliente_id');
            
            $dias = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_disponibilidad_dias')
                ->where('IdProducto', $idProducto)
                ->where('Activo', 1)
                ->get(['IdSucursal', 'DiaSemana']);
            
            // Agrupar por sucursal
            $agrupado = [];
            foreach ($dias as $dia) {
                if (!isset($agrupado[$dia->IdSucursal])) {
                    $agrupado[$dia->IdSucursal] = [];
                }
                $agrupado[$dia->IdSucursal][] = (int) $dia->DiaSemana;
            }
            
            return response()->json([
                'success' => true,
                'dias' => $agrupado
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo disponibilidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener disponibilidad'
            ], 500);
        }
    }

    /**
     * Guardar días de disponibilidad de un producto
     */
    public function guardarDisponibilidadDias(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');
            
            $request->validate([
                'IdProducto' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
                'dias_por_sucursal' => 'required|array',
                'dias_por_sucursal.*.IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
                'dias_por_sucursal.*.dias' => 'array',
                'dias_por_sucursal.*.dias.*' => 'integer|min:1|max:7'
            ]);
            
            DB::beginTransaction();
            
            foreach ($request->dias_por_sucursal as $config) {
                // Eliminar días actuales para esta combinación
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_disponibilidad_dias')
                    ->where('IdProducto', $request->IdProducto)
                    ->where('IdSucursal', $config['IdSucursal'])
                    ->delete();
                
                // Insertar nuevos días
                foreach ($config['dias'] as $dia) {
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_relacion_ventainventario_disponibilidad_dias')
                        ->insert([
                            'IdProducto' => $request->IdProducto,
                            'IdSucursal' => $config['IdSucursal'],
                            'DiaSemana' => $dia,
                            'Activo' => 1,
                            'IdOperadorInserta' => $operadorId,
                            'FechaInserta' => now(),
                        ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Días de disponibilidad guardados correctamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error guardando disponibilidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener sucursales para disponibilidad
     */
    public function getSucursalesDisponibilidad()
    {
        try {
            $clienteId = session('cliente_id');
            
            $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 0)
                ->orderBy('Nombre')
                ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);
            
            return response()->json([
                'success' => true,
                'sucursales' => $sucursales
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursales'
            ], 500);
        }
    }


}