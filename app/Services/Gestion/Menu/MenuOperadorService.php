<?php

namespace App\Services\Gestion\Menu;

use App\Models\Gestion\Menu\MenuAdministrador;
use App\Models\Gestion\Menu\MenuOperador;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuOperadorService
{
    private $g;

    public function __construct()
    {
        $this->g = DB::connection('mysql_gestion_comercial_alimentos');
    }

    /**
     * Asigna menús a un operador, insertando automáticamente los padres
     */
    public function asignarMenusConPadres(int $operadorId, int $clienteId, array $menuIds): void
    {
        // Verificar que el operador existe
        $operador = Operador::find($operadorId);
        if (!$operador) {
            throw new \Exception("El operador con ID {$operadorId} no existe");
        }

        // Verificar que la empresa existe
        $empresa = Cliente::find($clienteId);
        if (!$empresa) {
            throw new \Exception("La empresa con ID {$clienteId} no existe");
        }

        // 1. Eliminar asignaciones actuales
        MenuOperador::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->delete();

        if (empty($menuIds)) {
            Log::info('Menús eliminados para operador', [
                'operador_id' => $operadorId,
                'cliente_id' => $clienteId
            ]);
            return;
        }

        $insertados = [];

        foreach ($menuIds as $menuId) {
            $this->insertarConPadres($menuId, $operadorId, $clienteId, $insertados);
        }

        // Log para auditoría
        Log::info('Menús asignados a operador', [
            'operador_id' => $operadorId,
            'cliente_id' => $clienteId,
            'menus' => $insertados
        ]);
    }

    /**
     * Inserta un menú y recursivamente sus padres si no existen
     */
    private function insertarConPadres(int $menuId, int $operadorId, int $clienteId, array &$insertados): void
    {
        if (in_array($menuId, $insertados)) {
            return;
        }

        // Verificar que el menú existe
        $menu = MenuAdministrador::find($menuId);
        if (!$menu) {
            Log::warning("Menú ID {$menuId} no existe, se omite");
            return;
        }

        // Insertar menú actual
        MenuOperador::create([
            'IdMenu' => $menuId,
            'IdOperador' => $operadorId,
            'IdCliente' => $clienteId
        ]);
        $insertados[] = $menuId;

        // Buscar padre
        $parent = $menu->Parent;
        
        if ($parent && $parent > 0) {
            $yaExiste = MenuOperador::where('IdOperador', $operadorId)
                ->where('IdCliente', $clienteId)
                ->where('IdMenu', $parent)
                ->exists();

            if (!$yaExiste) {
                $this->insertarConPadres($parent, $operadorId, $clienteId, $insertados);
            }
        }
    }

    /**
     * Obtiene la lista de menús asignados a un operador
     */
    public function obtenerMenusAsignados(int $operadorId, int $clienteId): array
    {
        return MenuOperador::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->pluck('IdMenu')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    /**
     * Verifica si un operador tiene acceso a un menú específico
     */
    public function tieneAcceso(int $operadorId, int $clienteId, int $menuId): bool
    {
        return MenuOperador::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->where('IdMenu', $menuId)
            ->exists();
    }

    /**
     * Obtiene todos los operadores que tienen acceso a un menú específico
     */
    public function getOperadoresConAcceso(int $clienteId, int $menuId): array
    {
        return MenuOperador::where('IdCliente', $clienteId)
            ->where('IdMenu', $menuId)
            ->with('operador')
            ->get()
            ->map(fn($item) => [
                'id' => $item->IdOperador,
                'nombre' => $item->operador?->identificador?->Nombre ?? 'Sin nombre'
            ])
            ->toArray();
    }
}