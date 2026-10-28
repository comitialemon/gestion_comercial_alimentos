<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class Reclamo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_reclamos';
    protected $primaryKey = 'IdReclamo';
    public $timestamps = false;

    // Tipos válidos como constantes para no repetir strings mágicos
    public const TIPO_DEFECTUOSO = 'DEFECTUOSO';
    public const TIPO_FALTANTE   = 'FALTANTE';

    protected $fillable = [
        'IdPedidoCliente',
        'IdCliente',
        'IdOperador',
        'Estado',
        'TipoReclamo',        // 🔥 FALTABA
        'Descripcion',
        'RespuestaCalidad',
        'FotoUrl',
        'FechaCreacion',
        'FechaActualizacion',
    ];

    protected $casts = [
        'FechaCreacion'      => 'datetime',
        'FechaActualizacion' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(PedidoCliente::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }

    public function detalles()
    {
        return $this->hasMany(ReclamoDetalle::class, 'IdReclamo', 'IdReclamo');
    }

    public function esFaltante(): bool
    {
        return $this->TipoReclamo === self::TIPO_FALTANTE;
    }

    public function esDefectuoso(): bool
    {
        return $this->TipoReclamo === self::TIPO_DEFECTUOSO;
    }
}