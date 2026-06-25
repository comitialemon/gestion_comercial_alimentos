<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\CategoriaProducto;
use App\Models\Gestion\Inventario\CategoriaImagen;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CategoriaProductoController extends Controller
{
    private $imageManager;
    private $useWebP;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->useWebP = function_exists('imagewebp');
        
        Log::info('📸 Sistema de imágenes de categorías iniciado', [
            'webp_soportado' => $this->useWebP,
            'formato_principal' => $this->useWebP ? 'WebP' : 'JPEG'
        ]);
    }

    public function index()
    {
        $categorias = CategoriaProducto::porContexto()
            ->with(['padre', 'imagenes'])
            ->orderBy('orden')
            ->get();

        // 🔥 Asignar la URL de la imagen principal (para el frontend)
        foreach ($categorias as $categoria) {
            if ($categoria->imagenes->isNotEmpty()) {
                $principal = $categoria->imagenes->firstWhere('EsPrincipal', true) ?? $categoria->imagenes->first();
                // Esto se usa en el frontend como imagen_url
                $categoria->imagen_url = $principal->url_thumbnail;
            }
        }

        $categoriasPadre = CategoriaProducto::porContexto()
            ->orderBy('orden')
            ->get(['id_categoria as id', 'nombre']);

        return Inertia::render('Gestion/Inventario/CategoriaProducto/Index', [
            'categorias' => $categorias,
            'categoriasPadre' => $categoriasPadre,
        ]);
    }

    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'nombre' => 'required|string|max:100',
            'id_padre' => 'nullable|exists:inventario_menu_categoria,id_categoria',
            'activo' => 'boolean',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        $padre = null;
        if ($request->id_padre) {
            $padre = CategoriaProducto::find($request->id_padre);
        }

        $orden = CategoriaProducto::getNextOrder($request->id_padre);

        // 🔥 Crear categoría SIN imagen_url
        $categoria = CategoriaProducto::create([
            'nombre' => strtoupper($request->nombre),
            'id_padre' => $request->id_padre ?: null,
            // 'imagen_url' => null, // ❌ ELIMINADO
            'orden' => $orden,
            'activo' => $request->activo ? 1 : 0,
            'id_cliente' => $clienteId,
        ]);

        // 🔥 GUARDAR IMAGEN OPTIMIZADA
        if ($request->hasFile('imagen')) {
            $this->guardarImagenOptimizada(
                $request->file('imagen'),
                $categoria->id_categoria,
                $request->nombre,
                $clienteId,
                $sucursalId,
                $operadorId,
                true
            );
        }

        return redirect()->back()->with('success', "Categoría '{$categoria->nombre}' creada correctamente");
    }

    public function update(Request $request, $id)
    {
        $categoria = CategoriaProducto::porContexto()->with('imagenes')->findOrFail($id);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'nombre' => 'required|string|max:100',
            'id_padre' => 'nullable|exists:inventario_menu_categoria,id_categoria',
            'activo' => 'boolean',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'eliminar_imagen' => 'nullable|boolean'
        ]);

        if ($request->id_padre == $id) {
            return redirect()->back()->withErrors(['id_padre' => 'No puedes poner una categoría como hija de sí misma']);
        }

        $padreAnterior = $categoria->id_padre;
        $nuevoPadre = $request->id_padre;

        $padre = null;
        if ($request->id_padre) {
            $padre = CategoriaProducto::find($request->id_padre);
        }

        // 🔥 ELIMINAR IMAGEN
        if ($request->boolean('eliminar_imagen')) {
            $imagenPrincipal = CategoriaImagen::where('IdCategoria', $id)
                ->where('EsPrincipal', 1)
                ->first();
            
            if ($imagenPrincipal) {
                $this->eliminarArchivosImagen($imagenPrincipal);
                $imagenPrincipal->delete();
                Log::info('🗑️ Imagen principal de categoría eliminada');
            }
        }

        // 🔥 GUARDAR NUEVA IMAGEN
        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior
            $imagenAnterior = CategoriaImagen::where('IdCategoria', $id)
                ->where('EsPrincipal', 1)
                ->first();
            
            if ($imagenAnterior) {
                $this->eliminarArchivosImagen($imagenAnterior);
                $imagenAnterior->delete();
                Log::info('🗑️ Imagen anterior de categoría eliminada');
            }
            
            $this->guardarImagenOptimizada(
                $request->file('imagen'),
                $categoria->id_categoria,
                $request->nombre,
                $clienteId,
                $sucursalId,
                $operadorId,
                true
            );
            Log::info('✅ Nueva imagen de categoría guardada');
        }

        if ($padreAnterior != $nuevoPadre) {
            $nuevoOrden = CategoriaProducto::getNextOrder($nuevoPadre);
            $categoria->orden = $nuevoOrden;
            CategoriaProducto::reordenar($padreAnterior);
        }

        // 🔥 Actualizar categoría SIN imagen_url
        $categoria->update([
            'nombre' => strtoupper($request->nombre),
            'id_padre' => $request->id_padre ?: null,
            // 'imagen_url' => null, // ❌ ELIMINADO
            'activo' => $request->activo ? 1 : 0,
        ]);

        if ($padreAnterior != $nuevoPadre) {
            CategoriaProducto::reordenar($nuevoPadre);
        }

        return redirect()->back()->with('success', "Categoría '{$categoria->nombre}' actualizada correctamente");
    }

    public function destroy($id)
    {
        $categoria = CategoriaProducto::porContexto()->findOrFail($id);

        if ($categoria->hijos()->count() > 0) {
            return redirect()->back()->with('error', 'No se puede eliminar porque tiene subcategorías');
        }

        if ($categoria->productos()->count() > 0) {
            return redirect()->back()->with('error', 'No se puede eliminar porque tiene productos asociados');
        }

        $padreId = $categoria->id_padre;

        // 🔥 ELIMINAR TODAS LAS IMÁGENES
        $imagenes = CategoriaImagen::where('IdCategoria', $id)->get();
        foreach ($imagenes as $imagen) {
            $this->eliminarArchivosImagen($imagen);
            $imagen->delete();
        }

        $categoria->delete();
        CategoriaProducto::reordenar($padreId);

        return redirect()->back()->with('success', 'Categoría eliminada correctamente');
    }

    public function reordenarTodo()
    {
        CategoriaProducto::reordenarTodo();
        return redirect()->back()->with('success', 'Categorías reordenadas correctamente');
    }

    // ==================== MÉTODOS PRIVADOS PARA IMÁGENES ====================

    /**
     * 🔥 GUARDAR IMAGEN OPTIMIZADA DE CATEGORÍA (3 VERSIONES)
     * 
     * ESTRUCTURA: /storage/cliente_{id}/categorias/{nombre_categoria}/imagenes/
     *   ├── original/
     *   ├── medium/
     *   └── thumbnails/
     */
    private function guardarImagenOptimizada($file, $categoriaId, $nombre, $clienteId, $sucursalId, $operadorId, $esPrincipal = true)
    {
        try {
            Log::info('=== PROCESANDO IMAGEN DE CATEGORÍA ===');
            Log::info('Categoría ID: ' . $categoriaId);
            Log::info('Nombre: ' . $nombre);
            Log::info('Cliente ID: ' . $clienteId);
            Log::info('Tamaño original: ' . $this->formatBytes($file->getSize()));

            // 1. Cargar imagen
            $imagen = $this->imageManager->read($file->getPathname());
            
            $anchoOriginal = $imagen->width();
            $altoOriginal = $imagen->height();
            Log::info("Dimensiones originales: {$anchoOriginal}x{$altoOriginal}");

            // 2. Generar nombre
            $nombreLimpio = Str::slug($nombre, '_');
            $timestamp = date('Ymd_His');
            $extension = $this->useWebP ? 'webp' : 'jpg';

            // 3. 🔥 ESTRUCTURA: cliente_{id}/categorias/{nombre_categoria}/imagenes/
            $rutaBase = sprintf(
                '/storage/cliente_%d/categorias/%s/imagenes',
                $clienteId,
                $nombreLimpio
            );

            Log::info('📁 Ruta base: ' . $rutaBase);

            // 4. CREAR 3 VERSIONES
            $rutas = [];

            // THUMBNAIL (150x150) - Calidad 70
            $thumbnail = clone $imagen;
            $thumbnail->cover(150, 150);
            $rutaThumb = $rutaBase . '/thumbnails/' . $categoriaId . '_' . $timestamp . '_thumb.' . $extension;
            $this->guardarImagen($thumbnail, $rutaThumb, 70, $extension);
            $rutas['thumbnail'] = $rutaThumb;
            Log::info('✅ Thumbnail generado: 150x150');

            // MEDIUM (600x400) - Calidad 80
            $medium = clone $imagen;
            $medium->resize(600, 400, function($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $rutaMedium = $rutaBase . '/medium/' . $categoriaId . '_' . $timestamp . '_medium.' . $extension;
            $this->guardarImagen($medium, $rutaMedium, 80, $extension);
            $rutas['medium'] = $rutaMedium;
            Log::info('✅ Medium generado: 600x400');

            // ORIGINAL OPTIMIZADO (1200x900) - Calidad 85
            $original = clone $imagen;
            $original->resize(1200, 900, function($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $rutaOriginal = $rutaBase . '/original/' . $categoriaId . '_' . $timestamp . '_original.' . $extension;
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

            Log::info('📊 RESUMEN OPTIMIZACIÓN CATEGORÍA:');
            Log::info('   Original: ' . $this->formatBytes($pesoOriginal));
            Log::info('   Total 3 versiones: ' . $this->formatBytes($pesoTotal));
            Log::info('   Ahorro: ' . $porcentajeAhorro . '%');

            // 6. GUARDAR EN BASE DE DATOS
            $imagenRegistro = CategoriaImagen::create([
                'IdCategoria' => $categoriaId,
                'NombreArchivo' => $categoriaId . '_' . $timestamp . '.' . $extension,
                'RutaOriginal' => $rutaOriginal,
                'RutaMedium' => $rutaMedium,
                'RutaThumbnail' => $rutaThumb,
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

            Log::info('✅ Registro de imagen de categoría creado. ID: ' . $imagenRegistro->IdImagenCategoria);

            return [
                'id' => $imagenRegistro->IdImagenCategoria,
                'thumbnail' => asset($rutaThumb),
                'medium' => asset($rutaMedium),
                'original' => asset($rutaOriginal),
                'peso_original' => $this->formatBytes($pesoOriginal),
                'peso_total' => $this->formatBytes($pesoTotal),
                'ahorro' => $porcentajeAhorro . '%'
            ];

        } catch (\Exception $e) {
            Log::error('❌ Error guardando imagen de categoría: ' . $e->getMessage());
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
}