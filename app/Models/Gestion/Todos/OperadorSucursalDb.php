<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Operacion\Pedidos\ClientesMayoristas\OperadorPedidoCliente;

class OperadorSucursalDb extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_operador_sucursaldb';
    protected $primaryKey = 'IdSucursalDB';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdSucursal',
        'IdOperador',
    ];

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    public function empresa()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    // ✅ NUEVO: config PedidoClientes por asignación
    public function pedidoClienteConfig()
    {
        return $this->hasOne(OperadorPedidoCliente::class, 'IdSucursalDB', 'IdSucursalDB');
    }
}