<?php

namespace App\Models\Operacion\Pedidos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Operador;

class Pedido extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_ventas_pedidos';
    protected $primaryKey = 'IdPedidos';
    public $timestamps = false;

    protected $fillable = [
        'IdTipoPedido',
        'ProduceDistribuye',
        'FechaRealiza',
        'FechaDelPedido',
        'IdProducto',
        'Unidades',
        'AutorizadoNoAutorizado',
        'IdCliente',
        'IdSucursal',
        'idOperador',
        'UnidadesAutoriza',
        'IdOperadorAutoriza',
        'IdDistribucion',
        'UnidadesDistribuidas',
        'DistribuidoSiNo',
        'IdOperadorRecibe',
        'UnidadesRecibidas',
        'IdOperadorPedidoExtraordinario',  // ✅ AGREGADO
    ];

    protected $casts = [
        'FechaRealiza' => 'datetime',
        'FechaDelPedido' => 'date',
        'Unidades' => 'decimal:2',
        'AutorizadoNoAutorizado' => 'boolean',
        'UnidadesAutoriza' => 'decimal:2',
        'UnidadesDistribuidas' => 'decimal:2',
        'DistribuidoSiNo' => 'boolean',
        'UnidadesRecibidas' => 'decimal:2',
    ];

    // Relaciones
    public function tipoPedido()
    {
        return $this->belongsTo(TipoPedido::class, 'IdTipoPedido', 'IdTipoPedido');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'idOperador', 'IdOperador');
    }

    public function operadorAutoriza()
    {
        return $this->belongsTo(Operador::class, 'IdOperadorAutoriza', 'IdOperador');
    }

    // Scopes
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    public function scopePorOperador($query)
    {
        return $query->where('idOperador', session('operador_id'));
    }

    public function scopeFuturos($query)
    {
        return $query->whereDate('FechaDelPedido', '>', now('America/La_Paz'));
    }

    public function scopeNoDistribuidos($query)
    {
        return $query->where('DistribuidoSiNo', 0);
    }
}