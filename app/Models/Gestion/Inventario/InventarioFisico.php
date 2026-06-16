<?php
// app/Models/Gestion/Inventario/InventarioFisico.php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Fecha;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;

class InventarioFisico extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_fisicorealizado';
    protected $primaryKey = 'IdFisico';
    public $timestamps = false;

    protected $fillable = [
        'NumeroCorrelativo',
        'IdFecha',
        'IdAlmacen',
        'IdRealizadoPor',
        'IdEncargadoSucursal',
        'Observacion',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
        'IdOperador',
    ];

    protected $casts = [
        'ActivoInactivo' => 'integer',
        'NumeroCorrelativo' => 'integer',
    ];

    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'IdAlmacen', 'IdAlmacen');
    }

    public function realizadoPor()
    {
        return $this->belongsTo(Identificador::class, 'IdRealizadoPor', 'IdIdentificador');
    }

    public function encargadoSucursal()
    {
        return $this->belongsTo(Identificador::class, 'IdEncargadoSucursal', 'IdIdentificador');
    }

    public function detalles()
    {
        return $this->hasMany(InventarioFisicoDetalle::class, 'IdFisico', 'IdFisico');
    }

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdOperador', session('operador_id'));
    }
}