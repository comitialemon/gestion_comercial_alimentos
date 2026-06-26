<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class ClienteSucursal extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_cliente_sucursal';
    protected $primaryKey = 'IdClienteSucursal';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdPlaza',
        'Nombre',
        'Direccion',
        'Celular',
        'NumeroSucursal',
        'ActivaInactivaR',
        'Orden',
        'ActivoInactivo',
        'ControlInternoEfectivo',
        'facturacion_habilitada',
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function plaza()
    {
        return $this->belongsTo(ClientePlaza::class, 'IdPlaza', 'IdPlaza');
    }

    // Scope para filtrar por contexto
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }
}