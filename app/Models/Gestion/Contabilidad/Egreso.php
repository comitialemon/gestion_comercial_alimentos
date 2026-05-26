<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Fecha;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use Illuminate\Support\Facades\DB;

class Egreso extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_comprobante_egreso';
    protected $primaryKey = 'IdEgreso';
    public $timestamps = false;

    protected $fillable = [
        'IdDiario',
        'IdFecha',
        'IdCuentaDebe',
        'IdCuentaHaber',
        'IdIdentificador',
        'Glosa',
        'TotalBolivianos',
        'NumeroEgreso',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
        'IdOperador',
    ];

    protected $casts = [
        'TotalBolivianos' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'NumeroEgreso' => 'integer',
    ];

    // ==================== RELACIONES ====================
    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    public function cuentaDebe()
    {
        return $this->belongsTo(ContaCuenta::class, 'IdCuentaDebe', 'IdCuenta');
    }

    public function cuentaHaber()
    {
        return $this->belongsTo(ContaCuenta::class, 'IdCuentaHaber', 'IdCuenta');
    }

    // ==================== SCOPES ====================
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeInactivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    // ==================== ACCESSORS ====================
    public function getNumeroDiarioAttribute()
    {
        if (!$this->IdDiario) return '-';
        
        $numero = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->where('IdDiario', $this->IdDiario)
            ->value('NumeroDiario');
        
        return $numero ?? '-';
    }

    public function getFechaFormateadaAttribute()
    {
        if ($this->fecha) {
            return date('d/m/Y', strtotime($this->fecha->Fecha));
        }
        
        $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $this->IdFecha)
            ->first();
        
        if ($fechaData) {
            return date('d/m/Y', strtotime($fechaData->Fecha));
        }
        
        return '-';
    }

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Contabilizado' : 'Borrador';
    }

    public function getEstadoColorAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'green' : 'yellow';
    }

    public function getEstadoIconoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'fa-check-circle' : 'fa-pencil-alt';
    }
}