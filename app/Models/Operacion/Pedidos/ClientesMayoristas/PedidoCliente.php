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
        'TipoPrecio',
        'NumeroPedido',
        'FechaPedido',
        'FechaEntrega',
        'TotalUnidades',
        'TotalContenedores',
        'TotalGeneral',
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
        'TotalContenedores' => 'integer',
        'TotalGeneral' => 'decimal:2',
        'ActivoInactivo' => 'integer',
    ];

    // ==================== CONSTANTES ====================
    
    const ESTADO_BORRADOR = 'Borrador';
    const ESTADO_ESPERANDO_PAGO = 'Esperando Pago';
    const ESTADO_PENDIENTE = 'Pendiente';
    const ESTADO_EN_PROCESO = 'En Proceso';
    const ESTADO_ENTREGADO = 'Entregado';
    const ESTADO_CANCELADO = 'Cancelado';

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

    /**
     * ✅ Relación con pagos QR
     */
    public function pagos()
    {
        return $this->hasMany(PedidoClientePago::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }

    /**
     * ✅ Último pago (el más reciente)
     */
    public function ultimoPago()
    {
        return $this->hasOne(PedidoClientePago::class, 'IdPedidoCliente', 'IdPedidoCliente')
            ->latest('IdPagoPedido');
    }

    /**
     * ✅ Pago pendiente actual (si existe)
     */
    public function pagoPendiente()
    {
        return $this->hasOne(PedidoClientePago::class, 'IdPedidoCliente', 'IdPedidoCliente')
            ->where('Estado', 'PENDIENTE')
            ->latest('IdPagoPedido');
    }

    /**
     * ✅ Pago exitoso (si existe)
     */
    public function pagoExitoso()
    {
        return $this->hasOne(PedidoClientePago::class, 'IdPedidoCliente', 'IdPedidoCliente')
            ->where('Estado', 'PAGADO')
            ->latest('IdPagoPedido');
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
        return $query->where('ActivoInactivo', 0)
            ->where('EstadoPedido', self::ESTADO_BORRADOR);
    }

    public function scopeEsperandoPago($query)
    {
        return $query->where('EstadoPedido', self::ESTADO_ESPERANDO_PAGO);
    }

    public function scopePendientes($query)
    {
        return $query->where('EstadoPedido', self::ESTADO_PENDIENTE);
    }

    public function scopeEntregados($query)
    {
        return $query->where('EstadoPedido', self::ESTADO_ENTREGADO);
    }

    public function scopeCancelados($query)
    {
        return $query->where('EstadoPedido', self::ESTADO_CANCELADO);
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
     * ✅ Obtener el pedido en "Esperando Pago" del operador (si tiene uno)
     */
    public static function obtenerEsperandoPago()
    {
        return self::porContexto()
            ->porSucursal()
            ->porOperador()
            ->esperandoPago()
            ->latest('IdPedidoCliente')
            ->first();
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
            'TipoPrecio' => 'sin_factura',
            'NumeroPedido' => '0',
            'FechaPedido' => Carbon::now('America/La_Paz'),
            'FechaEntrega' => null,
            'TotalUnidades' => 0,
            'TotalContenedores' => 0,
            'TotalGeneral' => 0,
            'ActivoInactivo' => 0,
            'EstadoPedido' => self::ESTADO_BORRADOR,
            'Observaciones' => null,
            'IdOperadorInserta' => session('operador_id'),
            'FechaInserta' => Carbon::now('America/La_Paz'),
        ], $data));
    }

    // ==================== ACCESORS ====================
    
    public function getEstadoColorAttribute()
    {
        $colores = [
            self::ESTADO_BORRADOR => 'yellow',
            self::ESTADO_ESPERANDO_PAGO => 'purple',
            self::ESTADO_PENDIENTE => 'blue',
            self::ESTADO_EN_PROCESO => 'orange',
            self::ESTADO_ENTREGADO => 'green',
            self::ESTADO_CANCELADO => 'red',
        ];
        return $colores[$this->EstadoPedido] ?? 'gray';
    }

    public function getEstadoIconoAttribute()
    {
        $iconos = [
            self::ESTADO_BORRADOR => 'fa-pencil-alt',
            self::ESTADO_ESPERANDO_PAGO => 'fa-qrcode',
            self::ESTADO_PENDIENTE => 'fa-clock',
            self::ESTADO_EN_PROCESO => 'fa-cog',
            self::ESTADO_ENTREGADO => 'fa-check-circle',
            self::ESTADO_CANCELADO => 'fa-times-circle',
        ];
        return $iconos[$this->EstadoPedido] ?? 'fa-circle';
    }

    public function getEstadoBadgeAttribute()
    {
        $badges = [
            self::ESTADO_BORRADOR => 'bg-yellow-100 text-yellow-800',
            self::ESTADO_ESPERANDO_PAGO => 'bg-purple-100 text-purple-800',
            self::ESTADO_PENDIENTE => 'bg-blue-100 text-blue-800',
            self::ESTADO_EN_PROCESO => 'bg-orange-100 text-orange-800',
            self::ESTADO_ENTREGADO => 'bg-green-100 text-green-800',
            self::ESTADO_CANCELADO => 'bg-red-100 text-red-800',
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

    public function getTotalGeneralFormateadoAttribute()
    {
        return number_format($this->TotalGeneral, 2, ',', '.');
    }

    public function getTotalGeneralConMonedaAttribute()
    {
        return 'Bs. ' . number_format($this->TotalGeneral, 2, ',', '.');
    }

    public function getTieneTotalGeneralAttribute()
    {
        return $this->TotalGeneral > 0;
    }

    public function getTipoPrecioTextoAttribute()
    {
        return $this->TipoPrecio === 'con_factura' ? 'Con Factura' : 'Sin Factura';
    }

    public function getTipoPrecioBadgeAttribute()
    {
        return $this->TipoPrecio === 'con_factura' 
            ? 'bg-blue-100 text-blue-800' 
            : 'bg-gray-100 text-gray-800';
    }

    public function getTipoPrecioIconoAttribute()
    {
        return $this->TipoPrecio === 'con_factura' 
            ? 'fa-file-invoice-dollar' 
            : 'fa-receipt';
    }

    // ==================== HELPERS DE ESTADO ====================
    
    public function estaEnBorrador(): bool
    {
        return $this->EstadoPedido === self::ESTADO_BORRADOR;
    }

    public function estaEsperandoPago(): bool
    {
        return $this->EstadoPedido === self::ESTADO_ESPERANDO_PAGO;
    }

    public function estaPendiente(): bool
    {
        return $this->EstadoPedido === self::ESTADO_PENDIENTE;
    }

    public function estaPagado(): bool
    {
        return $this->pagoExitoso()->exists();
    }

    public function estaCancelado(): bool
    {
        return $this->EstadoPedido === self::ESTADO_CANCELADO;
    }

    public function puedeGenerarQR(): bool
    {
        return in_array($this->EstadoPedido, [
            self::ESTADO_BORRADOR,
            self::ESTADO_ESPERANDO_PAGO,
        ]);
    }

    public function puedeCancelar(): bool
    {
        return $this->EstadoPedido === self::ESTADO_BORRADOR;
    }
}