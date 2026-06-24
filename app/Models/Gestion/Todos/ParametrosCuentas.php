<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class ParametrosCuentas extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_parametros_cuentas';
    protected $primaryKey = 'IdParametros';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'VentasFacturadas',
        'VentasNoFacturadas',
        'DebitoFiscalIVA',
        'ITPagados',
        'ITxPagar',
        'ControlDFIVA',
        'ComprasFacturadas',
        'ComprasNoFacturadas',
        'CreditoFiscalIVA',
        'CuentaPersonalVendedor',
        'Inventario',
        'Proveedores',
        'DiferenciaInventarioSobrante',
        'DiferenciaInventarioFaltante',
        'AnticipoProveedores',
        'DiferenciaDeCambioIngreso',
        'DiferenciaDeCambioGasto',
        'AporteLaboralPasivo',
        'AportePatronalPasivo',
        'CajaSaludPasivo',
        'AguinaldoPorPagarPasivo',
        'IndemnizacionPasivo',
        'SueldosGasto',
        'AportesPatronalesGasto',
        'CajaSaludGasto',
        'AguinaldoGasto',
        'IndemnizacionGasto',
        'DescuentosDiciplinarios',
        'CajaBolivianos',
        'CajaChica',
    ];

    // Scope para cliente
    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('IdCliente', $clienteId);
    }
}