<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Fecha;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Operador;

class Diario extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_diario';
    protected $primaryKey = 'IdDiario';
    public $timestamps = false;

    protected $fillable = [
        'IdFecha',
        'IdTipoDiario',
        'NumeroDiario',
        'IdCliente',
        'IdSucursal',
        'Contabilizado',
        'IdOperadorIngreso',
        'FechaIngreso',
        'IdOperadorEdita',
        'FechaEdita',
    ];

    protected $casts = [
        'NumeroDiario' => 'integer',
        'Contabilizado' => 'integer',
    ];

    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    public function tipoDiario()
    {
        return $this->belongsTo(TipoDiario::class, 'IdTipoDiario', 'IdTipoDiario');
    }

    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function operadorIngreso()
    {
        return $this->belongsTo(Operador::class, 'IdOperadorIngreso', 'IdOperador');
    }

    public function asientos()
    {
        return $this->hasMany(DiarioPropiamente::class, 'IdDiario', 'IdDiario');
    }

    // Scopes
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function scopePendientes($query)
    {
        return $query->where('Contabilizado', 0)
                     ->where('IdOperadorIngreso', session('operador_id'));
    }

    public function scopeContabilizados($query)
    {
        return $query->where('Contabilizado', 1);
    }

    public function scopeNoCerrados($query)
    {
        return $query->whereNotIn('IdTipoDiario', [6, 7, 11, 12, 15, 17, 19]);
    }
}