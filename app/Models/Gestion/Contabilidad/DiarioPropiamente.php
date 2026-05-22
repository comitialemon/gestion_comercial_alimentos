<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\ClienteActividad;

class DiarioPropiamente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_diario_propiamente';
    protected $primaryKey = 'IdContaPropiamente';
    public $timestamps = false;

    protected $fillable = [
        'IdDiario',
        'IdCuenta',
        'Glosa',
        'D_H',
        'MontoBolivianos',
        'TipoCambio',
        'MontoOtraMoneda',
        'IdIdentificador',
        'IdActividad',
        'Deducible',
        'IdActivoFijo',
    ];

    protected $casts = [
        'MontoBolivianos' => 'decimal:2',
        'TipoCambio' => 'decimal:6',
        'MontoOtraMoneda' => 'decimal:2',
    ];

    public function diario()
    {
        return $this->belongsTo(Diario::class, 'IdDiario', 'IdDiario');
    }

    public function cuenta()
    {
        return $this->belongsTo(ContaCuenta::class, 'IdCuenta', 'IdCuenta');
    }

    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    public function actividad()
    {
        return $this->belongsTo(ClienteActividad::class, 'IdActividad', 'IdActividad');
    }
}