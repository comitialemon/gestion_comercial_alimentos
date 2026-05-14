<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Fecha;

class AjusteInventario extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_ajustesprincipal';
    protected $primaryKey = 'IdAjustesPrincipal';
    public $timestamps = false;

    protected $fillable = [
        'IdFecha',
        'ConceptoOperacion',
        'IdTipoOperacion',
        'NumeroCorrelativo',
        'IdAlmacen',
        'IdRealizadoPor',
        'IdAutorizadoPor',
        'Explicacion',
        'IdCliente',
        'IdSucursal',
        'IdOperadorIngresa',
        'FechaIngreso',
        'IdOperadorEdita',
        'FechaActualiza',
        'ActivoInactivo',
    ];

    protected $casts = [
        'ActivoInactivo' => 'integer',
        'NumeroCorrelativo' => 'integer',
        'Unidades' => 'decimal:2',
        'Bolivianos' => 'decimal:2',
    ];

    // Relaciones
    public function detalles()
    {
        return $this->hasMany(AjusteInventarioDetalle::class, 'IdAjustesPrincipal', 'IdAjustesPrincipal');
    }

    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    public function tipoOperacion()
    {
        return $this->belongsTo(TipoOperacion::class, 'IdTipoOperacion', 'IdTipoOperacion');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'IdAlmacen', 'IdAlmacen');
    }

    public function realizadoPor()
    {
        return $this->belongsTo(Identificador::class, 'IdRealizadoPor', 'IdIdentificador');
    }

    public function autorizadoPor()
    {
        return $this->belongsTo(Identificador::class, 'IdAutorizadoPor', 'IdIdentificador');
    }

    // Scopes
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    public function scopePendientePorOperador($query)
    {
        return $query->where('ActivoInactivo', 0)
                     ->where('IdOperadorIngresa', session('operador_id'));
    }
}