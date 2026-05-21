<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Inventario\VentaGrupo;
use App\Models\Gestion\Inventario\ProductoVentaPrecioSucursal;
use App\Models\Gestion\Inventario\ProductoVentaPrecioMayorista;
use App\Models\Gestion\Inventario\RelacionVentaDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
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
        
        $query = ProductoVenta::where('IdCliente', $clienteId)->with('grupo');
        
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

        // 🔥 BUSCAR SI HAY UN PRODUCTO BORRADOR (ActivoInactivo = 1) PARA ESTE OPERADOR
        $productoBorrador = ProductoVenta::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdOperadorInserta', $operadorId)
            ->where('ActivoInactivo', ProductoVenta::ESTADO_INACTIVO) // 1 = Borrador
            ->first();

        if ($productoBorrador) {
            // Redirigir a edición del producto borrador
            return redirect()->route('gestion.productos-venta.edit', $productoBorrador->IdDetalleProducto)
                ->with('info', 'Tienes un producto en borrador. Continúa editándolo.');
        }

        // Si no hay borrador, mostrar formulario normal
        $grupos = VentaGrupo::where('IdCliente', $clienteId)
            ->orderBy('Detalle')
            ->get(['IdVentaGrupo as id', 'Detalle as nombre']);
        
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
            'sucursales' => $sucursales,
            'identificadores' => $identificadores,
            'productosInventario' => $productosInventario,
        ]);
    }
    
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
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
            'Codigo' => 'required|string|max:100',
            'Detalle' => 'required|string|max:100',
            'NombreCortoFactura' => 'required|string|max:20',
            'PrecioVenta' => 'required|numeric|min:0',
            'imagen_base64' => 'nullable|string',
        ]);

        // Validar unicidad
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

            // 🔥 CREAR PRODUCTO COMO BORRADOR (ESTADO_INACTIVO = 1)
            $producto = ProductoVenta::create([
                'IdVentaGrupo' => $request->IdVentaGrupo,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'NombreCortoFactura' => $request->NombreCortoFactura,
                'PrecioVenta' => $request->PrecioVenta,
                'ActivoInactivo' => ProductoVenta::ESTADO_INACTIVO, // 1 = Borrador
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
        
        $request->validate([
            'IdVentaGrupo' => 'required|exists:inventario_relacion_ventainventario_grupouno,IdVentaGrupo',
            'Codigo' => 'required|string|max:100',
            'Detalle' => 'required|string|max:100',
            'NombreCortoFactura' => 'required|string|max:20',
            'PrecioVenta' => 'required|numeric|min:0',
            'ActivoInactivo' => 'required|boolean',
            'imagen_base64' => 'nullable|string',
        ]);
        
        // VALIDACIONES MANUALES DE UNICIDAD (excluyendo actual)
        $codigoExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Codigo', $request->Codigo)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($codigoExiste) {
            return redirect()->back()
                ->withErrors(['Codigo' => 'El código ya existe. Por favor, use un código diferente.'])
                ->withInput();
        }
        
        $detalleExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('Detalle', $request->Detalle)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($detalleExiste) {
            return redirect()->back()
                ->withErrors(['Detalle' => 'El detalle ya existe. Por favor, use un nombre diferente.'])
                ->withInput();
        }
        
        $nombreCortoExiste = ProductoVenta::where('IdCliente', $clienteId)
            ->where('NombreCortoFactura', $request->NombreCortoFactura)
            ->where('IdDetalleProducto', '!=', $id)
            ->exists();
        
        if ($nombreCortoExiste) {
            return redirect()->back()
                ->withErrors(['NombreCortoFactura' => 'El nombre para factura ya existe. Por favor, use uno diferente.'])
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
            
            $producto->update([
                'IdVentaGrupo' => $request->IdVentaGrupo,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'NombreCortoFactura' => $request->NombreCortoFactura,
                'PrecioVenta' => $request->PrecioVenta,
                'ActivoInactivo' => $request->ActivoInactivo ? 0 : 1,
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
    
    public function activar($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        $tieneDetalle = RelacionVentaDetalle::where('IdDetalleProducto', $id)->exists();
        
        if (!$tieneDetalle) {
            return redirect()->back()->with('error', 'No se puede activar. El producto no tiene relación con inventario.');
        }
        
        $producto->update([
            'ActivoInactivo' => 0,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => now(),
        ]);
        
        return redirect()->back()->with('success', 'Producto activado correctamente');
    }
    
    public function desactivar($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        $producto->update([
            'ActivoInactivo' => 1,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => now(),
        ]);
        
        return redirect()->back()->with('success', 'Producto desactivado correctamente');
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
                'IdOperadorActualiza' => $operadorId,  // 🔥 AGREGAR ESTE
                'FechaActualiza' => now(),            // 🔥 AGREGAR ESTE
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
                'IdOperadorActualiza' => $operadorId,  // 🔥 AGREGAR ESTE
                'FechaActualiza' => now(),            // 🔥 AGREGAR ESTE
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

            // Cargar la relación producto manualmente
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
    // Enviar producto a aprobación
    public function enviarAprobacion($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        $producto = ProductoVenta::where('IdCliente', $clienteId)->findOrFail($id);
        
        // 🔥 PERMITIR ENVIAR DESDE INACTIVO (1) O RECHAZADO (3)
        if ($producto->ActivoInactivo != ProductoVenta::ESTADO_INACTIVO && 
            $producto->ActivoInactivo != ProductoVenta::ESTADO_RECHAZADO) {
            return redirect()->back()->with('error', 'El producto no puede ser enviado a aprobación en su estado actual');
        }
        
        // Obtener aprobadores configurados
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
            
            // Cambiar estado del producto a pendiente de aprobación
            $producto->update([
                'ActivoInactivo' => ProductoVenta::ESTADO_PENDIENTE_APROBACION,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);
            
            // Crear solicitud
            $solicitud = ProductoAprobacionSolicitud::create([
                'IdDetalleProducto' => $producto->IdDetalleProducto,
                'IdOperadorSolicita' => $operadorId,
                'Estado' => 'pendiente',
                'FechaSolicitud' => now(),
            ]);
            
            // Crear votos para cada aprobador
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

    // Listado de productos pendientes para el aprobador
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

    // Aprobar o rechazar producto
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
            
            // Verificar si todos los votos están completados
            $todosVotos = $solicitud->votos;
            $pendientes = $todosVotos->where('Estado', 'pendiente')->count();
            $rechazados = $todosVotos->where('Estado', 'rechazado')->count();
            
            if ($rechazados > 0) {
                // Alguien rechazó -> solicitud rechazada
                $solicitud->update([
                    'Estado' => 'rechazado',
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $solicitud->producto->update([
                    'ActivoInactivo' => ProductoVenta::ESTADO_RECHAZADO,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $mensaje = 'Producto rechazado';
                
            } elseif ($pendientes == 0) {
                // Todos aprobaron -> solicitud aprobada y producto activado
                $solicitud->update([
                    'Estado' => 'aprobado',
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
                
                $solicitud->producto->update([
                    'ActivoInactivo' => ProductoVenta::ESTADO_ACTIVO,
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

    // Ver detalle de aprobación de un producto
    public function verAprobacion($id)
    {
        // 🔥 Cargar la relación 'grupo'
        $producto = ProductoVenta::where('IdCliente', session('cliente_id'))
            ->with('grupo')  // 👈 AGREGAR ESTO
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