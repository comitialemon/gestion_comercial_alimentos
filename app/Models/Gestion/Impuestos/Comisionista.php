<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\Identificador;

class Comisionista extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_comisionitas';
    protected $primaryKey = 'IdComisionista';
    public $timestamps = false;

    protected $fillable = [
        'IdIdentificador',
        'Comision',
        'IdCliente',
    ];

    /**
     * Scope para filtrar por cliente actual de la sesión
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    /**
     * Relación con el identificador (persona/empresa)
     */
    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    /**
     * Accesor para obtener el nombre
     */
    public function getNombreAttribute()
    {
        return $this->identificador?->Nombre ?? 'Sin nombre';
    }

    /**
     * Relación con la empresa
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }
}