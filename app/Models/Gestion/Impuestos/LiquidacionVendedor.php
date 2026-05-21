<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class LiquidacionVendedor extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_liquidacion_vendedor';
    protected $primaryKey = 'iDLiquidacionVendedor';
    public $timestamps = false;

    protected $fillable = [
        'IdFecha',
        'IdDiario',
        'vEntas',
        'vEntasConfirma',
        'eFectivoBolivianos',
        'eFectivoBolivianosConfirma',
        'cLientes',
        'cLientesConfirma',
        'pOrCobrarPersonal',
        'pOrCobrarPersonalConfirma',
        'tArjetaATC',
        'tArjetaATCconfirma',
        'dIfVendedor',
        'ActivoInactivo',
        'dIfVendedorConfirma',
        'LiquidadoSupervisor',
        'iDcliente',
        'iDsucursal',
        'iDtipoOperadorVentas',
        'iDoperadorVendedor',
    ];

    protected $casts = [
        'vEntas' => 'decimal:2',
        'vEntasConfirma' => 'decimal:2',
        'eFectivoBolivianos' => 'decimal:2',
        'eFectivoBolivianosConfirma' => 'decimal:2',
        'cLientes' => 'decimal:2',
        'cLientesConfirma' => 'decimal:2',
        'pOrCobrarPersonal' => 'decimal:2',
        'pOrCobrarPersonalConfirma' => 'decimal:2',
        'tArjetaATC' => 'decimal:2',
        'tArjetaATCconfirma' => 'decimal:2',
        'dIfVendedor' => 'decimal:2',
        'dIfVendedorConfirma' => 'decimal:2',
        'ActivoInactivo' => 'integer',
    ];

    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }
}