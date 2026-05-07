<?php
namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class TipoOperacion extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_tipooperacion';
    protected $primaryKey = 'IdTipoOperacion';
    public $timestamps = false;

    protected $fillable = ['Detalle', 'Concepto', 'ActivoInactivo', 'IdCliente'];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }
}