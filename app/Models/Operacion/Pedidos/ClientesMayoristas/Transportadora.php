<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class Transportadora extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_transportadoras';
    protected $primaryKey = 'IdTransportadora';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'Nombre',
        'Telefono',
        'Destino',
        'ActivoInactivo',
    ];

    // Relación: Una transportadora tiene muchos envíos
    public function envios()
    {
        return $this->hasMany(Envio::class, 'IdTransportadora', 'IdTransportadora');
    }
}