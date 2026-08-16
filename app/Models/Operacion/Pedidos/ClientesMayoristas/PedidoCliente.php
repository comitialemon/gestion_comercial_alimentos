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
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'FechaPedido' => 'datetime',
        'FechaEntrega' => 'date',
        'TotalUnidades' => 'decimal:2',
        'ActivoInactivo' => 'integer',
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

    public function scopePorSucursal($query, $sucursalId = null)
    {
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        return $query->where('IdSucursal', $sucursalId);
    }

    public function scopePorOperador($query, $operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        return $query->where('IdOperador', $operadorId);
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeBorradores($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    public function scopePendientes($query)
    {
        return $query->where('EstadoPedido', 'Pendiente');
    }

    public function scopeEntregados($query)
    {
        return $query->where('EstadoPedido', 'Entregado');
    }

    public function scopeCancelados($query)
    {
        return $query->where('EstadoPedido', 'Cancelado');
    }

    /**
     * ✅ Obtener borrador del operador actual
     */
    public static function obtenerBorradorActivo()
    {
        return self::porContexto()
            ->porSucursal()
            ->porOperador()
            ->borradores()
            ->first();
    }

    /**
     * ✅ Verificar si existe un borrador activo
     */
    public static function existeBorradorActivo()
    {
        return self::obtenerBorradorActivo() !== null;
    }

    /**
     * ✅ Crear o actualizar borrador
     */
    public static function obtenerOCrearBorrador($data = [])
    {
        $borrador = self::obtenerBorradorActivo();

        if ($borrador) {
            $borrador->update($data);
            return $borrador;
        }

        return self::create(array_merge([
            'IdCliente' => session('cliente_id'),
            'IdSucursal' => session('cliente_sucursal_id'),
            'IdOperador' => session('operador_id'),
            'NumeroPedido' => '0',
            'FechaPedido' => Carbon::now('America/La_Paz'),
            'FechaEntrega' => null,
            'TotalUnidades' => 0,
            'TotalContenedores' => 0,
            'ActivoInactivo' => 0,
            'EstadoPedido' => 'Borrador',
            'Observaciones' => null,
            'IdOperadorInserta' => session('operador_id'),
            'FechaInserta' => Carbon::now('America/La_Paz'),
        ], $data));
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

    public function getEstadoBadgeAttribute()
    {
        $badges = [
            'Borrador' => 'bg-yellow-100 text-yellow-800',
            'Pendiente' => 'bg-blue-100 text-blue-800',
            'En Proceso' => 'bg-orange-100 text-orange-800',
            'Entregado' => 'bg-green-100 text-green-800',
            'Cancelado' => 'bg-red-100 text-red-800',
        ];
        return $badges[$this->EstadoPedido] ?? 'bg-gray-100 text-gray-800';
    }

    public function getFechaPedidoFormateadaAttribute()
    {
        return $this->FechaPedido ? Carbon::parse($this->FechaPedido)->format('d/m/Y H:i') : '-';
    }

    public function getFechaEntregaFormateadaAttribute()
    {
        return $this->FechaEntrega ? Carbon::parse($this->FechaEntrega)->format('d/m/Y') : '-';
    }

    public function getTotalUnidadesFormateadaAttribute()
    {
        return number_format($this->TotalUnidades, 0, ',', '.');
    }

    public function getTotalContenedoresFormateadaAttribute()
    {
        return number_format($this->TotalContenedores, 0, ',', '.');
    }

    public function getNumeroPedidoFormateadoAttribute()
    {
        return str_pad($this->NumeroPedido, 6, '0', STR_PAD_LEFT);
    }
}