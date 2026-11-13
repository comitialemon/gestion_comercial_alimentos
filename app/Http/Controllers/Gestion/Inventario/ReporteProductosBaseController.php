<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Services\Inventario\ProductoBaseComboCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteProductosBaseController extends Controller
{
    protected ProductoBaseComboCalculator $calculator;

    public function __construct(ProductoBaseComboCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function index(Request $request)
    {
        $clienteId = session('cliente_id');

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        $sucursalId = $request->filled('sucursal_id')
            ? (int) $request->sucursal_id
            : (int) session('cliente_sucursal_id');

        $fechaInicial      = $request->fecha_inicial ?? date('Y-m-01');
        $fechaFinal        = $request->fecha_final   ?? date('Y-m-d');
        $search            = $request->search;
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);

        $data = $this->calculator->calcular(
            (int) $clienteId,
            $sucursalId,
            $fechaInicial,
            $fechaFinal,
            $search,
            $soloConMovimiento
        );

        $productos = collect($data['filas'])->map(function ($fila) use ($data) {
            $valoresCombo   = $fila['valores'];
            $esFraccionado  = (bool) ($fila['es_fraccionado'] ?? false);
            $ventaCompuesta = 0;
            $detalles       = [];

            foreach ($valoresCombo as $idVenta => $cantidad) {
                if ($cantidad <= 0) continue;

                $columna = collect($data['columnas'])->firstWhere('id', (int) $idVenta);
                $ventaCompuesta += $cantidad;

                $detalles[] = [
                    'id_producto_venta'     => (int) $idVenta,
                    'nombre'                => $columna['nombre'] ?? "Producto #{$idVenta}",
                    'codigo'                => $columna['codigo'] ?? '',
                    'unidades_vendidas'     => null,
                    'cantidad_base'         => $esFraccionado ? round($cantidad, 4) : (int) $cantidad,
                    'es_suelto'             => false,
                    'tiene_personalizacion' => false,
                    'porcion'               => 0,
                    'tipo'                  => 'Combo/Pack',
                ];
            }

            $ventaSuelto = $esFraccionado
                ? round($fila['otras_salidas'], 4)
                : (int) $fila['otras_salidas'];

            if ($ventaSuelto > 0) {
                $detalles[] = [
                    'id_producto_venta'     => null,
                    'nombre'                => 'Venta suelta / otras salidas',
                    'codigo'                => '',
                    'unidades_vendidas'     => $ventaSuelto,
                    'cantidad_base'         => $ventaSuelto,
                    'es_suelto'             => true,
                    'tiene_personalizacion' => false,
                    'porcion'               => 1,
                    'tipo'                  => 'Suelto',
                ];
            }

            return [
                'IdProducto'            => $fila['id_producto'],
                'Codigo'                => $fila['codigo'],
                'Descripcion'           => $fila['descripcion'],
                'total_vendido'         => $esFraccionado ? round($fila['total'], 4) : (int) $fila['total'],
                'venta_suelto'          => $ventaSuelto,
                'venta_compuesta'       => $esFraccionado ? round($ventaCompuesta, 4) : (int) $ventaCompuesta,
                'detalles_venta'        => $detalles,
                'total_productos_venta' => count($detalles),
                'tiene_movimiento'      => $fila['total'] > 0,
                'es_fraccionado'        => $esFraccionado,
            ];
        });

        return Inertia::render('Gestion/Inventario/ReporteProductosBase/Index', [
            'productos'            => $productos->values(),
            'sucursales'           => $sucursales,
            'fechaInicial'         => $fechaInicial,
            'fechaFinal'           => $fechaFinal,
            'sucursalSeleccionada' => $sucursalId,
            'search'               => $search,
            'soloConMovimiento'    => $soloConMovimiento,
        ]);
    }
}