<?php

namespace App\Models\Gestion\Menu;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\Cliente;

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
     * Relación con el menú
     */
    public function menu()
    {
        return $this->belongsTo(MenuAdministrador::class, 'IdMenu', 'Id');
    }

    /**
     * Relación con el operador
     */
    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    /**
     * Relación con la empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }
}