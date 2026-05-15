<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Fecha;

class Ingreso extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_comprobante_ingreso';
    protected $primaryKey = 'IdIngreso';
    public $timestamps = false;

    protected $fillable = [
        'IdDiario',
        'IdFecha',
        'IdCuentaDebe',
        'IdCuentaHaber',
        'IdIdentificador',
        'Glosa',
        'TotalBolivianos',
        'NumeroIngreso',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
        'IdOperador',
    ];

    protected $casts = [
        'TotalBolivianos' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'NumeroIngreso' => 'integer',
    ];

    // Relaciones
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

    // Scopes
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

    public function scopePorOperador($query)
    {
        return $query->where('IdOperador', session('operador_id'));
    }
}