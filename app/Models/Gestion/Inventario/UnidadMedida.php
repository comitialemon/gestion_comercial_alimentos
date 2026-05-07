<?php
namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_unidadmedida';
    protected $primaryKey = 'IdUnidadMedida';
    public $timestamps = false;

    protected $fillable = ['UnidadMedida'];

    // No tiene IdCliente, se comparte entre todas las empresas
    public function scopePorContexto($query)
    {
        return $query;
    }
}