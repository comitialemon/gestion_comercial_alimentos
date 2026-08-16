<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class ContenedorTipo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_contenedor_tipo';
    protected $primaryKey = 'IdTipoContenedor';
    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'IdCliente',
        'IdSucursal',
        'IdOperadorInserta',
        'FechaInserta',
        'ActivoInactivo',
    ];

    protected $casts = [
        'ActivoInactivo' => 'integer',
    ];

    public function scopePorCliente($query, $clienteId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        return $query->where('IdCliente', $clienteId);
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function contenedores()
    {
        return $this->hasMany(Contenedor::class, 'IdTipoContenedor', 'IdTipoContenedor');
    }
}