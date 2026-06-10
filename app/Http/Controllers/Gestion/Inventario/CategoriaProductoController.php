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
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:512' // 🔥 CAMBIADO: file, no base64
        ]);

        $padre = null;
        if ($request->id_padre) {
            $padre = CategoriaProducto::find($request->id_padre);
        }

        $imagenUrl = null;
        if ($request->hasFile('imagen')) { // 🔥 CAMBIADO: verificar file
            $imagenUrl = $this->guardarImagen($request->file('imagen'), $request->nombre, $padre);
        }

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
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:512' // 🔥 CAMBIADO: file, no base64
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
        
        // 🔥 CAMBIADO: manejar nueva imagen como file
        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior si existe
            if ($imagenUrl && file_exists(public_path($imagenUrl))) {
                unlink(public_path($imagenUrl));
                $carpeta = dirname(public_path($imagenUrl));
                if (is_dir($carpeta) && count(glob($carpeta . '/*')) === 0) {
                    rmdir($carpeta);
                }
            }
            $imagenUrl = $this->guardarImagen($request->file('imagen'), $request->nombre, $padre);
        }

        if ($padreAnterior != $nuevoPadre) {
            $nuevoOrden = CategoriaProducto::getNextOrder($nuevoPadre);
            $categoria->orden = $nuevoOrden;
            CategoriaProducto::reordenar($padreAnterior);
        }

        $categoria->update([
            'nombre' => strtoupper($request->nombre),
            'id_padre' => $request->id_padre ?: null,
            'imagen_url' => $imagenUrl,
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
        CategoriaProducto::reordenar($padreId);

        return redirect()->back()->with('success', 'Categoría eliminada correctamente');
    }

    public function reordenarTodo()
    {
        CategoriaProducto::reordenarTodo();
        return redirect()->back()->with('success', 'Categorías reordenadas correctamente');
    }

    /**
     * 🔥 NUEVO MÉTODO: Guardar imagen desde archivo subido (FormData)
     */
    private function guardarImagen($file, $nombre, $padre = null)
    {
        try {
            \Log::info('=== Guardando imagen desde archivo ===');
            \Log::info('Nombre original: ' . $file->getClientOriginalName());
            \Log::info('Mime type: ' . $file->getMimeType());
            \Log::info('Tamaño: ' . $file->getSize() . ' bytes');
            
            $extension = $file->getClientOriginalExtension();
            $nombreLimpio = Str::slug($nombre, '_');
            $rutaBase = $this->getRutaBasePadres($padre);
            $rutaCarpeta = $rutaBase . '/' . $nombreLimpio;
            $rutaCompletaCarpeta = public_path($rutaCarpeta);
            
            if (!file_exists($rutaCompletaCarpeta)) {
                if (!mkdir($rutaCompletaCarpeta, 0755, true)) {
                    throw new \Exception('No se pudo crear el directorio: ' . $rutaCompletaCarpeta);
                }
            }
            
            $nombreArchivo = 'icono.' . $extension;
            $rutaRelativa = $rutaCarpeta . '/' . $nombreArchivo;
            $rutaCompleta = public_path($rutaRelativa);
            
            // Mover el archivo
            $file->move($rutaCompletaCarpeta, $nombreArchivo);
            
            if (!file_exists($rutaCompleta)) {
                throw new \Exception('El archivo no se guardó correctamente');
            }
            
            \Log::info('✅ Imagen guardada en: ' . $rutaRelativa);
            
            return $rutaRelativa;
            
        } catch (\Exception $e) {
            \Log::error('❌ Error guardando imagen: ' . $e->getMessage());
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