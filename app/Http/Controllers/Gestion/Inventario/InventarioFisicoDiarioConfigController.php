<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\InventarioFisicoDiarioConfig;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventarioFisicoDiarioConfigController extends Controller
{
    /**
     * Listar configuraciones
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = $request->sucursal_id ?? null;

        $query = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
            ->with(['sucursal', 'operadorIngreso', 'operadorEdita']);  // 🔥 CARGAR RELACIONES

        if ($sucursalId) {
            $query->where('IdSucursal', $sucursalId);
        }

        $configuraciones = $query->orderBy('IdSucursal')
            ->orderBy('IdConfig', 'desc')
            ->paginate(20);

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);

        return Inertia::render('Gestion/Inventario/InventarioFisicoDiario/ConfigIndex', [
            'configuraciones' => $configuraciones,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId,
        ]);
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $clienteId = session('cliente_id');

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);

        return Inertia::render('Gestion/Inventario/InventarioFisicoDiario/ConfigForm', [
            'configuracion' => null,
            'sucursales' => $sucursales,
            'esEdicion' => false,
        ]);
    }

    /**
     * Guardar nueva configuración
     */
    public function store(Request $request)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'CantidadProductos' => 'required|integer|min:1|max:100',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // 🔥 VERIFICAR SI YA EXISTE UNA CONFIGURACIÓN PARA ESTA SUCURSAL (ACTIVA O INACTIVA)
        $existente = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
            ->where('IdSucursal', $request->IdSucursal)
            ->first();  // 👈 Busca aunque esté inactiva

        if ($existente) {
            $estadoTexto = $existente->ActivoInactivo == 1 ? 'activa' : 'inactiva';
            return redirect()->back()
                ->with('error', "⚠️ Ya existe una configuración {$estadoTexto} para esta sucursal. No se pueden crear múltiples configuraciones por sucursal.");
        }

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $config = InventarioFisicoDiarioConfig::create([
                'CantidadProductos' => $request->CantidadProductos,
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
                'ActivoInactivo' => 1,
                'IdOperadorIngreso' => $operadorId,
                'FechaIngreso' => now(),
                'IdOperadorEdita' => $operadorId,
                'FechaEdita' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('Configuración de inventario físico diario creada', [
                'id_config' => $config->IdConfig,
                'sucursal' => $request->IdSucursal,
                'cantidad' => $request->CantidadProductos,
                'operador' => $operadorId
            ]);

            return redirect()->route('gestion.inventario.inventario-fisico-diario.config.index')
                ->with('success', '✅ Configuración creada correctamente.');

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error creando configuración: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al crear la configuración: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');

        $configuracion = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
            ->where('IdConfig', $id)
            ->firstOrFail();

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);

        return Inertia::render('Gestion/Inventario/InventarioFisicoDiario/ConfigForm', [
            'configuracion' => $configuracion,
            'sucursales' => $sucursales,
            'esEdicion' => true,
        ]);
    }

    /**
     * Actualizar configuración
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'CantidadProductos' => 'required|integer|min:1|max:100',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $config = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
                ->where('IdConfig', $id)
                ->firstOrFail();

            // 🔥 SI ESTÁ CAMBIANDO DE SUCURSAL, VERIFICAR QUE NO EXISTA OTRA
            if ($config->IdSucursal != $request->IdSucursal) {
                $existente = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $request->IdSucursal)
                    ->where('IdConfig', '!=', $id)
                    ->first();

                if ($existente) {
                    $estadoTexto = $existente->ActivoInactivo == 1 ? 'activa' : 'inactiva';
                    return redirect()->back()
                        ->with('error', "⚠️ Ya existe una configuración {$estadoTexto} para esta sucursal.");
                }
            }

            $config->update([
                'CantidadProductos' => $request->CantidadProductos,
                'IdSucursal' => $request->IdSucursal,
                'IdOperadorEdita' => $operadorId,
                'FechaEdita' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('Configuración de inventario físico diario actualizada', [
                'id_config' => $config->IdConfig,
                'operador' => $operadorId
            ]);

            return redirect()->route('gestion.inventario.inventario-fisico-diario.config.index')
                ->with('success', '✅ Configuración actualizada correctamente.');

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error actualizando configuración: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al actualizar la configuración: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar configuración (solo si está inactiva)
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');

        try {
            $config = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
                ->where('IdConfig', $id)
                ->firstOrFail();

            if ($config->ActivoInactivo == 1) {
                return redirect()->back()
                    ->with('error', 'No se puede eliminar una configuración activa. Desactívela primero.');
            }

            $config->delete();

            Log::info('Configuración de inventario físico diario eliminada', [
                'id_config' => $id,
                'sucursal' => $config->IdSucursal
            ]);

            return redirect()->route('gestion.inventario.inventario-fisico-diario.config.index')
                ->with('success', '✅ Configuración eliminada correctamente.');

        } catch (\Exception $e) {
            Log::error('Error eliminando configuración: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al eliminar la configuración: ' . $e->getMessage());
        }
    }

    /**
     * Activar/Desactivar configuración
     */
    public function toggle($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        try {
            $config = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
                ->where('IdConfig', $id)
                ->firstOrFail();

            // Si se va a activar, verificar que no haya otra activa para esa sucursal
            if ($config->ActivoInactivo == 0) {
                $existenteActiva = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $config->IdSucursal)
                    ->where('ActivoInactivo', 1)
                    ->where('IdConfig', '!=', $id)
                    ->first();

                if ($existenteActiva) {
                    return redirect()->back()
                        ->with('error', '⚠️ Ya existe una configuración activa para esta sucursal.');
                }
            }

            $nuevoEstado = $config->ActivoInactivo == 1 ? 0 : 1;

            $config->update([
                'ActivoInactivo' => $nuevoEstado,
                'IdOperadorEdita' => $operadorId,
                'FechaEdita' => now(),
            ]);

            Log::info('Configuración de inventario físico diario ' . ($nuevoEstado ? 'activada' : 'desactivada'), [
                'id_config' => $id,
                'sucursal' => $config->IdSucursal,
                'operador' => $operadorId
            ]);

            return redirect()->route('gestion.inventario.inventario-fisico-diario.config.index')
                ->with('success', '✅ Configuración ' . ($nuevoEstado ? 'activada' : 'desactivada') . ' correctamente.');

        } catch (\Exception $e) {
            Log::error('Error cambiando estado de configuración: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al cambiar estado: ' . $e->getMessage());
        }
    }
}