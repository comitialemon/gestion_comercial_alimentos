<?php

namespace App\Models\Gestion\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
     * Obtiene las columnas de permisos (booleanas) de la tabla
     */
    public static function getPermisoColumns(): array
    {
        $excluir = [
            'Id', 'Description', 'Link', 'Parent', 'Node_Order'
        ];

        $columns = Schema::connection('mysql_gestion_comercial_alimentos')
            ->getColumnListing('menu_administrador');

        return array_values(array_filter($columns, fn($col) => !in_array($col, $excluir)));
    }

    /**
     * Relación con el padre
     */
    public function padre()
    {
        return $this->belongsTo(self::class, 'Parent', 'Id');
    }

    /**
     * Relación con los hijos
     */
    public function hijos()
    {
        return $this->hasMany(self::class, 'Parent', 'Id')->orderBy('Node_Order');
    }
}