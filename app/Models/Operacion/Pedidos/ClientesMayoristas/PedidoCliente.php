<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Operador;
use Carbon\Carbon;

class PedidoCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes';
    protected $primaryKey = 'IdPedidoCliente';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdSucursal',
        'IdOperador',
        'NumeroPedido',
        'FechaPedido',
        'FechaEntrega',
        'TotalUnidades',
        'TotalContenedores',
        'ActivoInactivo',
        'EstadoPedido',
        'Observaciones',
        'FechaInserta',
    ];

    protected $casts = [
        'FechaPedido' => 'datetime',
        'FechaEntrega' => 'date',
        'TotalUnidades' => 'decimal:2',
        'ActivoInactivo' => 'boolean',
    ];

    // ==================== RELACIONES ====================
    
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
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    public function detalles()
    {
        return $this->hasMany(PedidoClienteDetalle::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }

    // ==================== SCOPES ====================
    
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeBorradores($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    // ==================== ACCESORS ====================
    
    public function getEstadoColorAttribute()
    {
        $colores = [
            'Borrador' => 'yellow',
            'Pendiente' => 'blue',
            'En Proceso' => 'orange',
            'Entregado' => 'green',
            'Cancelado' => 'red',
        ];
        return $colores[$this->EstadoPedido] ?? 'gray';
    }

    public function getEstadoIconoAttribute()
    {
        $iconos = [
            'Borrador' => 'fa-pencil-alt',
            'Pendiente' => 'fa-clock',
            'En Proceso' => 'fa-cog',
            'Entregado' => 'fa-check-circle',
            'Cancelado' => 'fa-times-circle',
        ];
        return $iconos[$this->EstadoPedido] ?? 'fa-circle';
    }
}