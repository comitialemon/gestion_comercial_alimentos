<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Inventario\VentaGrupo;
use App\Models\Gestion\Inventario\ProductoVentaPrecioSucursal;
use App\Models\Gestion\Inventario\ProductoVentaPrecioMayorista;
use App\Models\Gestion\Inventario\RelacionVentaDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\ProductoEstado;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductoVentaController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        $query = ProductoVenta::where('IdCliente', $clienteId)
            ->with(['grupo']);
        
        // Filtro por estado
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }
        
        // Filtro por grupos seleccionados
        if ($request->filled('grupos')) {
            $gruposArray = explode(',', $request->grupos);
            $query->whereIn('IdVentaGrupo', $gruposArray);
        }
        
        // Búsqueda rápida
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                  ->orWhere('Detalle', 'like', "%{$search}%");
            });
        }
        
        $productos = $query->orderBy('Detalle')
            ->paginate(20)
            ->withQueryString();
        
        // Obtener grupos para filtros
        $grupos = VentaGrupo::where('IdCliente', $clienteId)
            ->orderBy('Detalle')
            ->get(['IdVentaGrupo as id', 'Detalle as nombre']);
        
        // Contar productos por estado
        $totalActivos = ProductoVenta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->count();
        $totalInactivos = ProductoVenta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->count();
        
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
        $sucursalId = session('cliente_sucursal_id');
        
        $producto = ProductoVenta::where('IdCliente', $clienteId)
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
        $request->validate([
            'IdVentaGrupo' => 'required|exists:inventario_relacion_ventainventario_grupouno,IdVentaGrupo',
            'Codigo' => 'required|string|max:100|unique:inventario_relacion_ventainventario,Codigo,NULL,IdDetalleProducto,IdCliente,' . session('cliente_id'),
            'Detalle' => 'required|string|max:100',
            'NombreCortoFactura' => 'required|string|max:20',
            'PrecioVenta' => 'required|numeric|min:0',
            'ActivoInactivo' => 'required|boolean',
            'imagen_base64' => 'nullable|string',
        ]);
        
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $imagenUrl = null;
            if ($request->imagen_base64) {
                $imagenUrl = $this->guardarImagen($request->imagen_base64, $request->Codigo);
            }
            
            $producto = ProductoVenta::create([
                'IdVentaGrupo' => $request->IdVentaGrupo,
                'Codigo' => $request->Codigo,
                'Detalle' => $request->Detalle,
                'NombreCortoFactura' => $request->NombreCortoFactura,
                'PrecioVenta' => $request->PrecioVenta,
                'ActivoInactivo' => $request->ActivoInactivo ? 0 : 1,
                'ImagenProducto' => $imagenUrl,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            return redirect()->route('gestion.productos-venta.edit', $producto->IdDetalleProducto)
                ->with('success', 'Producto creado correctamente');
                
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
            'Codigo' => 'required|string|max:100|unique:inventario_relacion_ventainventario,Codigo,' . $id . ',IdDetalleProducto,IdCliente,' . $clienteId,
            'Detalle' => 'required|string|max:100',
            'NombreCortoFactura' => 'required|string|max:20',
            'PrecioVenta' => 'required|numeric|min:0',
            'ActivoInactivo' => 'required|boolean',
            'imagen_base64' => 'nullable|string',
        ]);
        
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $imagenUrl = $producto->ImagenProducto;
            if ($request->imagen_base64 && !str_contains($request->imagen_base64, '/storage/')) {
                // Eliminar imagen anterior si existe
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
        
        // Verificar que tenga al menos un detalle
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
                'IdOperadorInserta' => session('operador_id'),
                'FechaInserta' => now(),
            ]);
            
            $precio->load('sucursal');
            
            return response()->json([
                'success' => true,
                'message' => 'Precio agregado correctamente',
                'precio' => $precio
            ]);
            
        } catch (\Exception $e) {
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
                'IdOperadorInserta' => session('operador_id'),
                'FechaInserta' => now(),
            ]);
            
            $precio->load(['sucursal', 'identificador']);
            
            return response()->json([
                'success' => true,
                'message' => 'Precio mayorista agregado correctamente',
                'precio' => $precio
            ]);
            
        } catch (\Exception $e) {
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
                'IdOperadorInserta' => session('operador_id'),
                'FechaInserta' => now(),
            ]);
            
            $detalle->load('producto');
            
            return response()->json([
                'success' => true,
                'message' => 'Producto agregado correctamente',
                'detalle' => $detalle
            ]);
            
        } catch (\Exception $e) {
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
            
            return response()->json([
                'success' => true,
                'message' => 'Porción actualizada correctamente',
                'detalle' => $detalle->load('producto')
            ]);
            
        } catch (\Exception $e) {
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
}