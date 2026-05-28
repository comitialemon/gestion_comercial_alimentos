<?php

namespace App\Http\Controllers\Gestion\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteTema;
use App\Models\Gestion\Todos\Cliente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemaClienteController extends Controller
{
    /**
     * 🔥 Asegura que la carpeta de logos existe
     */
    private function asegurarCarpetaLogos()
    {
        $paths = [
            storage_path('app/public/temas/logos'),
            public_path('storage/temas/logos'),
        ];
        
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
                \Log::info('Carpeta creada automáticamente: ' . $path);
            }
        }
    }

    public function index()
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $cliente = Cliente::find($clienteId);
        $tema = ClienteTema::where('id_cliente', $clienteId)->first();

        return Inertia::render('Gestion/Configuracion/TemaCliente', [
            'tema' => $tema,
            'clienteId' => $clienteId,
            'clienteNombre' => $cliente->Nombre ?? 'Mi Empresa',
        ]);
    }

    public function store(Request $request, $clienteId)
    {
        // 🔥 ASEGURAR QUE LA CARPETA EXISTE ANTES DE GUARDAR
        $this->asegurarCarpetaLogos();
        
        \Log::info('=== GUARDANDO TEMA ===');
        \Log::info('Cliente ID: ' . $clienteId);
        \Log::info('Has file logo? ' . ($request->hasFile('logo') ? 'SI' : 'NO'));
        
        if ($request->hasFile('logo')) {
            \Log::info('Logo nombre: ' . $request->file('logo')->getClientOriginalName());
            \Log::info('Logo tamaño: ' . $request->file('logo')->getSize() . ' bytes');
            \Log::info('Logo tipo: ' . $request->file('logo')->getMimeType());
        }
        
        $request->validate([
            'color_principal' => 'nullable|string|max:7',
            'color_secundario' => 'nullable|string|max:7',
            'color_fondo' => 'nullable|string|max:7',
            'color_texto_oscuro' => 'nullable|string|max:7',
            'color_texto_claro' => 'nullable|string|max:7',
            'color_acento' => 'nullable|string|max:7',
            'nombre_sistema' => 'nullable|string|max:100',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:512',
        ]);

        $tema = ClienteTema::where('id_cliente', $clienteId)->first();
        
        $cliente = Cliente::find($clienteId);
        $nombreCliente = $cliente ? Str::slug($cliente->Nombre, '_') : 'cliente_' . $clienteId;

        $hexPattern = '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
        
        $data = [
            'color_principal' => $request->color_principal && preg_match($hexPattern, $request->color_principal) ? $request->color_principal : '#1f2937',
            'color_secundario' => $request->color_secundario && preg_match($hexPattern, $request->color_secundario) ? $request->color_secundario : '#4b5563',
            'color_fondo' => $request->color_fondo && preg_match($hexPattern, $request->color_fondo) ? $request->color_fondo : '#ffffff',
            'color_texto_oscuro' => $request->color_texto_oscuro && preg_match($hexPattern, $request->color_texto_oscuro) ? $request->color_texto_oscuro : '#111827',
            'color_texto_claro' => $request->color_texto_claro && preg_match($hexPattern, $request->color_texto_claro) ? $request->color_texto_claro : '#ffffff',
            'color_acento' => $request->color_acento && preg_match($hexPattern, $request->color_acento) ? $request->color_acento : '#6b7280',
            'nombre_sistema' => $request->nombre_sistema,
            'activo' => 1,
        ];

        // 🔥 GUARDAR LOGO
        if ($request->hasFile('logo')) {
            try {
                // Eliminar logo anterior si existe
                if ($tema && $tema->logo_url) {
                    $oldPath = str_replace('/storage/', '', $tema->logo_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                        \Log::info('Logo anterior eliminado: ' . $oldPath);
                    }
                }
                
                // Obtener la extensión del archivo
                $extension = $request->file('logo')->getClientOriginalExtension();
                
                // Nombre del archivo
                $nombreArchivo = $nombreCliente . '_logo.' . $extension;
                
                // Guardar - AHORA LA CARPETA YA EXISTE
                $logoPath = $request->file('logo')->storeAs('temas/logos', $nombreArchivo, 'public');
                
                if ($logoPath) {
                    $data['logo_url'] = '/storage/' . $logoPath;
                    \Log::info('✅ Logo guardado exitosamente: ' . $data['logo_url']);
                } else {
                    \Log::error('❌ Error: storeAs devolvió false');
                }
                
            } catch (\Exception $e) {
                \Log::error('❌ Excepción guardando logo: ' . $e->getMessage());
            }
        }

        if ($tema) {
            $tema->update($data);
            $mensaje = 'Tema actualizado correctamente';
            \Log::info('Tema actualizado para cliente: ' . $clienteId);
        } else {
            $data['id_cliente'] = $clienteId;
            ClienteTema::create($data);
            $mensaje = 'Tema creado correctamente';
            \Log::info('Tema creado para cliente: ' . $clienteId);
        }

        // Limpiar caché de tema en sesión
        session()->forget([
            'tema_color_principal', 
            'tema_color_secundario', 
            'tema_color_fondo',
            'tema_color_texto_oscuro',
            'tema_color_texto_claro',
            'tema_color_acento',
            'tiene_tema_personalizado'
        ]);

        return redirect()->back()->with('success', $mensaje);
    }

    /**
     * Mostrar tema de una empresa específica (para supervisores)
     */
    public function show($clienteId)
    {
        $tipoOperador = session('operador_tipo_id');
        $esSupervisor = in_array($tipoOperador, [1, 2, 11]);

        if (!$esSupervisor) {
            return redirect()->route('oficial.index')
                ->with('error', 'No tienes permisos');
        }

        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            return redirect()->route('gestion.configuracion.tema.empresas')
                ->with('error', 'Empresa no encontrada');
        }

        $tema = ClienteTema::where('id_cliente', $clienteId)->first();

        return Inertia::render('Gestion/Configuracion/TemaCliente', [
            'tema' => $tema,
            'clienteId' => $clienteId,
            'clienteNombre' => $cliente->Nombre,
            'esSupervisor' => true,  // Para saber que puede volver al listado
        ]);
    }

    public function reset($clienteId)
    {
        $tema = ClienteTema::where('id_cliente', $clienteId)->first();
        
        if ($tema) {
            // Eliminar logo físico
            if ($tema->logo_url) {
                $oldPath = str_replace('/storage/', '', $tema->logo_url);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                    \Log::info('Logo eliminado al resetear: ' . $oldPath);
                }
            }
            $tema->delete();
        }

        // Limpiar caché
        session()->forget([
            'tema_color_principal', 
            'tema_color_secundario', 
            'tema_color_fondo',
            'tema_color_texto_oscuro',
            'tema_color_texto_claro',
            'tema_color_acento',
            'tiene_tema_personalizado'
        ]);

        return redirect()->back()->with('success', 'Tema restaurado a valores por defecto');
    }

    public function listarEmpresas()
    {
        $tipoOperador = session('operador_tipo_id');
        $esSupervisor = in_array($tipoOperador, [1, 2, 11]);

        if (!$esSupervisor) {
            return redirect()->route('oficial.index')
                ->with('error', 'No tienes permisos');
        }

        $empresas = Cliente::orderBy('Nombre')
            ->leftJoin('todos_cliente_tema', 'todos_cliente.IdCliente', '=', 'todos_cliente_tema.id_cliente')
            ->select(
                'todos_cliente.IdCliente as id',
                'todos_cliente.Nombre as nombre',
                'todos_cliente.NIT as nit',
                'todos_cliente_tema.id_tema as tiene_tema',
                'todos_cliente_tema.nombre_sistema'
            )
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'nit' => $e->nit,
                'tiene_tema' => !is_null($e->tiene_tema),
                'nombre_sistema' => $e->nombre_sistema,
            ]);

        return Inertia::render('Gestion/Configuracion/ListarEmpresasTema', [
            'empresas' => $empresas,
        ]);
    }
}