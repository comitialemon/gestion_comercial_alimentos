<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class ContenedorGrupo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_contenedor_grupo';
    protected $primaryKey = 'IdContenedorGrupo';
    public $timestamps = false;

    protected $fillable = [
        'IdContenedor',
        'IdGrupoAnalisis',
    ];

    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'IdContenedor', 'IdContenedor');
    }

    public function grupoAnalisis()
    {
        return $this->belongsTo(\App\Models\Gestion\Inventario\ProductoGrupoAnalisis::class, 'IdGrupoAnalisis', 'IdGrupoAnalisis');
    }
}