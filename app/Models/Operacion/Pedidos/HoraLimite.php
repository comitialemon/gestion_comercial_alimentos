<?php

namespace App\Models\Operacion\Pedidos;

use Illuminate\Database\Eloquent\Model;

class HoraLimite extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_ventas_pedidos_horalimite';
    protected $primaryKey = 'IdHoraLimite';
    public $timestamps = false;

    // ==================== CONSTANTES DE TIPO ====================
    public const TIPO_PEDIDO_ORDINARIO         = 'pedido_ordinario';
    public const TIPO_PEDIDO_CLIENTE_MAYORISTA = 'pedido_cliente_mayorista';

    protected $fillable = [
        'Hora',
        'ActivaControlDia',
        'IdCliente',
        'IdSucursal',
        'Tipo',
    ];

    protected $casts = [
        'Hora' => 'integer',
        'ActivaControlDia' => 'boolean',
        'IdSucursal' => 'integer',
    ];

    // ==================== HELPERS ESTÁTICOS ====================

    /**
     * Mapa de tipos disponibles (para frontend y validaciones)
     */
    public static function tiposDisponibles(): array
    {
        return [
            self::TIPO_PEDIDO_ORDINARIO => [
                'value' => self::TIPO_PEDIDO_ORDINARIO,
                'label' => 'Pedidos Ordinarios',
                'descripcion' => 'Pedidos de producción/distribución diaria',
            ],
            self::TIPO_PEDIDO_CLIENTE_MAYORISTA => [
                'value' => self::TIPO_PEDIDO_CLIENTE_MAYORISTA,
                'label' => 'Pedidos Clientes Mayoristas',
                'descripcion' => 'Pedidos por contenedores',
            ],
        ];
    }

    /**
     * Obtiene la hora activa para un tipo específico
     */
    public static function obtenerHoraActiva(string $tipo): ?self
    {
        return static::porContexto()
            ->porTipo($tipo)
            ->activos()
            ->first();
    }

    /**
     * Obtiene todas las horas de un tipo
     */
    public static function obtenerHorasPorTipo(string $tipo)
    {
        return static::porContexto()
            ->porTipo($tipo)
            ->ordenado()
            ->get();
    }

    // ==================== SCOPES ====================

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('Tipo', $tipo);
    }

    public function scopeParaPedidosOrdinarios($query)
    {
        return $query->where('Tipo', self::TIPO_PEDIDO_ORDINARIO);
    }

    public function scopeParaPedidosClientesMayoristas($query)
    {
        return $query->where('Tipo', self::TIPO_PEDIDO_CLIENTE_MAYORISTA);
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivaControlDia', 0);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('Hora');
    }

    // ==================== ACCESORS ====================

    public function getHoraFormateadaAttribute()
    {
        return str_pad($this->Hora, 2, '0', STR_PAD_LEFT) . ':00';
    }

    public function getEstadoTextoAttribute()
    {
        return $this->ActivaControlDia ? 'Inactivo' : 'Activo';
    }

    public function getEstadoColorAttribute()
    {
        return $this->ActivaControlDia ? 'red' : 'green';
    }

    public function getTipoTextoAttribute()
    {
        $tipos = self::tiposDisponibles();
        return $tipos[$this->Tipo]['label'] ?? 'Desconocido';
    }
}