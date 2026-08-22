<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;

class ContenedorCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_contenedor_cliente';
    protected $primaryKey = 'IdContenedorCliente';
    public $timestamps = false;

    protected $fillable = [
        'IdContenedor',
        'IdIdentificador',
        'IdCliente',
        'IdSucursal',
        'CantidadMinima',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'CantidadMinima' => 'decimal:2',
        'ActivoInactivo' => 'integer',
    ];

    // ==================== RELACIONES ====================
    
    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'IdContenedor', 'IdContenedor');
    }

    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    // ==================== SCOPES ====================
    
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('IdCliente', $clienteId);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('IdSucursal', $sucursalId);
    }

    public function scopePorIdentificador($query, $identificadorId)
    {
        return $query->where('IdIdentificador', $identificadorId);
    }

    public function scopePorContenedor($query, $contenedorId)
    {
        return $query->where('IdContenedor', $contenedorId);
    }
}