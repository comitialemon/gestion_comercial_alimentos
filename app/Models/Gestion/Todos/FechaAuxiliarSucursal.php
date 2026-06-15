<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class FechaAuxiliarSucursal extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_fecha_auxiliar_sucursal';
    protected $primaryKey = 'IdFechaAuxiliar';
    public $timestamps = false;

    protected $fillable = [
        'IdFecha',
        'IdCliente',
        'IdSucursal',
        'FechaApertura',
    ];

    protected $casts = [
        'FechaApertura' => 'datetime',
    ];

    /**
     * Scope para filtrar por cliente actual
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    /**
     * Relación con la fecha
     */
    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    /**
     * Relación con la sucursal
     */
    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }
}