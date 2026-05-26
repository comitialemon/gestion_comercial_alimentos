<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Fecha;  // 👈 Importante: está en Todos, no en Impuestos
use Illuminate\Support\Facades\DB;

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
        'dIfVendedor',
        'dIfVendedorConfirma',
        'ActivoInactivo',
        'LiquidadoSupervisor',
        'iDcliente',
        'iDsucursal',
        'iDtipoOperadorVentas',
        'iDoperadorVendedor',
    ];

    protected $casts = [
        'vEntas' => 'decimal:2',
        'vEntasConfirma' => 'decimal:2',
        'dIfVendedor' => 'decimal:2',
        'dIfVendedorConfirma' => 'decimal:2',
        'ActivoInactivo' => 'integer',
    ];

    // Relaciones
    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    public function detalles()
    {
        return $this->hasMany(LiquidacionVendedorDetalle::class, 'iDLiquidacionVendedor', 'iDLiquidacionVendedor');
    }

    // Scopes
    public function scopePorContexto($query)
    {
        return $query->where('iDcliente', session('cliente_id'))
                     ->where('iDsucursal', session('cliente_sucursal_id'));
    }

    public function scopePorVendedor($query)
    {
        return $query->where('iDoperadorVendedor', session('operador_id'));
    }

    public function scopePendientes($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    public function scopeContabilizadas($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function getNumeroDiarioAttribute()
    {
        if (!$this->IdDiario || $this->IdDiario == 0) {
            return null;
        }
        
        $numero = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->where('IdDiario', $this->IdDiario)
            ->value('NumeroDiario');
        
        return $numero ?? null;
    }
    
}