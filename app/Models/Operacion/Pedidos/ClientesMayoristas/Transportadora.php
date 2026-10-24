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
        'IdIdentificador',
        'Destino',
        'ActivoInactivo',
    ];

    // 🔥 Relación con la tabla maestra de Identificadores
    public function identificador()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    public function envios()
    {
        return $this->hasMany(Envio::class, 'IdTransportadora', 'IdTransportadora');
    }
}