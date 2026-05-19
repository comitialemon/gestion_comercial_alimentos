<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\CategoriaProducto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CategoriaProductoController extends Controller
{
    public function index()
    {
        $categorias = CategoriaProducto::porContexto()
            ->with('padre')
            ->orderBy('orden')
            ->get();

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
        $request->validate([
            'nombre' => 'required|string|max:100',
            'id_padre' => 'nullable|exists:inventario_menu_categoria,id_categoria',
            'activo' => 'boolean',
            'imagen_base64' => 'nullable|string'
        ]);

        $padre = null;
        if ($request->id_padre) {
            $padre = CategoriaProducto::find($request->id_padre);
        }

        $imagenUrl = null;
        if ($request->imagen_base64) {
            $imagenUrl = $this->guardarImagen($request->imagen_base64, $request->nombre, $padre);
        }

        // 🔥 Calcular orden automáticamente
        $orden = CategoriaProducto::getNextOrder($request->id_padre);

        $categoria = CategoriaProducto::create([
            'nombre' => strtoupper($request->nombre),
            'id_padre' => $request->id_padre ?: null,
            'imagen_url' => $imagenUrl,
            'orden' => $orden,
            'activo' => $request->activo ? 1 : 0,
            'id_cliente' => session('cliente_id'),
        ]);

        return redirect()->back()->with('success', "Categoría '{$categoria->nombre}' creada correctamente");
    }

    public function update(Request $request, $id)
    {
        $categoria = CategoriaProducto::porContexto()->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'id_padre' => 'nullable|exists:inventario_menu_categoria,id_categoria',
            'activo' => 'boolean',
            'imagen_base64' => 'nullable|string'
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

        $imagenUrl = $categoria->imagen_url;
        if ($request->imagen_base64) {
            if ($imagenUrl && file_exists(public_path($imagenUrl))) {
                unlink(public_path($imagenUrl));
                $carpeta = dirname(public_path($imagenUrl));
                if (is_dir($carpeta) && count(glob($carpeta . '/*')) === 0) {
                    rmdir($carpeta);
                }
            }
            $imagenUrl = $this->guardarImagen($request->imagen_base64, $request->nombre, $padre);
        }

        // 🔥 Si cambió de padre, reordenar en ambos grupos
        if ($padreAnterior != $nuevoPadre) {
            // Calcular nuevo orden para el nuevo padre
            $nuevoOrden = CategoriaProducto::getNextOrder($nuevoPadre);
            $categoria->orden = $nuevoOrden;
            
            // Reordenar el grupo anterior
            CategoriaProducto::reordenar($padreAnterior);
        }

        $categoria->update([
            'nombre' => strtoupper($request->nombre),
            'id_padre' => $request->id_padre ?: null,
            'imagen_url' => $imagenUrl,
            'activo' => $request->activo ? 1 : 0,
        ]);

        // Reordenar el nuevo grupo para mantener consistencia
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

        if ($categoria->imagen_url) {
            $rutaCompleta = public_path($categoria->imagen_url);
            if (file_exists($rutaCompleta)) {
                unlink($rutaCompleta);
            }
            $carpeta = dirname($rutaCompleta);
            if (is_dir($carpeta) && count(glob($carpeta . '/*')) === 0) {
                rmdir($carpeta);
            }
        }

        $categoria->delete();

        // 🔥 Reordenar el grupo del padre después de eliminar
        CategoriaProducto::reordenar($padreId);

        return redirect()->back()->with('success', 'Categoría eliminada correctamente');
    }

    /**
     * 🔥 Reordenar todas las categorías (útil para limpiar órdenes desordenados)
     */
    public function reordenarTodo()
    {
        CategoriaProducto::reordenarTodo();
        
        return redirect()->back()->with('success', 'Categorías reordenadas correctamente');
    }

    private function guardarImagen($base64, $nombre, $padre = null)
    {
        try {
            if (str_contains($base64, 'base64,')) {
                $base64 = explode('base64,', $base64)[1];
            }
            
            $image = base64_decode($base64);
            if ($image === false) {
                throw new \Exception('No se pudo decodificar la imagen');
            }
            
            $finfo = finfo_open();
            $mimeType = finfo_buffer($finfo, $image, FILEINFO_MIME_TYPE);
            finfo_close($finfo);
            
            $extension = match($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };
            
            $nombreLimpio = Str::slug($nombre, '_');
            $rutaBase = $this->getRutaBasePadres($padre);
            $rutaCarpeta = $rutaBase . '/' . $nombreLimpio;
            $rutaCompletaCarpeta = public_path($rutaCarpeta);
            
            if (!file_exists($rutaCompletaCarpeta)) {
                mkdir($rutaCompletaCarpeta, 0755, true);
            }
            
            $rutaRelativa = $rutaCarpeta . '/icono.' . $extension;
            $rutaCompleta = public_path($rutaRelativa);
            file_put_contents($rutaCompleta, $image);
            
            return $rutaRelativa;
        } catch (\Exception $e) {
            \Log::error('Error guardando imagen: ' . $e->getMessage());
            return null;
        }
    }

    private function getRutaBasePadres($padre)
    {
        if (!$padre) {
            return '/storage/categorias';
        }
        
        $partes = [];
        $actual = $padre;
        
        while ($actual) {
            $nombreLimpio = Str::slug($actual->nombre, '_');
            array_unshift($partes, $nombreLimpio);
            $actual = $actual->padre;
        }
        
        return '/storage/categorias/' . implode('/', $partes);
    }
}