<?php

namespace App\Models\Gestion\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Services\Gestion\Menu\MenuService;  // 🔥 IMPORTAR

class MenuAdministrador extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'menu_administrador';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'Description',
        'Link',
        'Parent',
        'Node_Order',
        'Informes',
        'Digitador',
        'Supervisor',
        'Administrador',
        'EstadoCuenta',
        'VentaMostrador',
        'VentaRestaurante',
        'VentaSupervisor',
        'VentaAdministracion',
        'VentaMayorista',
        'MonitorCocina',
        'ComercialGerente',
        'ComercialSupervisor',
        'ComercialDistribucion',
        'Produccion',
        'ProduccionSupervisor',
        'ProduccionGerente',
        'Fiscal',
        'FiscalSupervisor',
        'FiscalGerente',
        'ControlInterno',
        'ControlInternoPrecios',
    ];

    /**
     * 🔥 EVENTOS: Invalida la caché del menú cuando se modifica la tabla
     */
    protected static function booted()
    {
        static::saved(function () {
            MenuService::invalidarCache();
            \Illuminate\Support\Facades\Log::info('MENU.cache_invalidada_por_guardado');
        });
        
        static::deleted(function () {
            MenuService::invalidarCache();
            \Illuminate\Support\Facades\Log::info('MENU.cache_invalidada_por_eliminacion');
        });
    }

    public static function getPermisoColumns(): array
    {
        $excluir = [
            'Id', 'Description', 'Link', 'Parent', 'Node_Order'
        ];

        $columns = Schema::connection('mysql_gestion_comercial_alimentos')
            ->getColumnListing('menu_administrador');

        return array_values(array_filter($columns, fn($col) => !in_array($col, $excluir)));
    }

    public function padre()
    {
        return $this->belongsTo(self::class, 'Parent', 'Id');
    }

    public function hijos()
    {
        return $this->hasMany(self::class, 'Parent', 'Id')->orderBy('Node_Order');
    }
}