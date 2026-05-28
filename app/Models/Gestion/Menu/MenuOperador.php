<?php

namespace App\Models\Gestion\Menu;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\Cliente;
use App\Services\Gestion\Menu\MenuService;

class MenuOperador extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'menu_operador';
    protected $primaryKey = 'IdMenuOperador';
    public $timestamps = false;

    protected $fillable = [
        'IdMenu',
        'IdCliente',
        'IdOperador',
    ];

    /**
     * EVENTOS: Invalida la caché del menú cuando se asignan menús a operadores
     */
    protected static function booted()
    {
        static::saved(function () {
            MenuService::invalidarCache();
            \Illuminate\Support\Facades\Log::info('MENU.cache_invalidada_por_asignacion_operador');
        });
        
        static::deleted(function () {
            MenuService::invalidarCache();
            \Illuminate\Support\Facades\Log::info('MENU.cache_invalidada_por_eliminacion_asignacion');
        });
    }

    public function menu()
    {
        return $this->belongsTo(MenuAdministrador::class, 'IdMenu', 'Id');
    }

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    public function empresa()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }
}