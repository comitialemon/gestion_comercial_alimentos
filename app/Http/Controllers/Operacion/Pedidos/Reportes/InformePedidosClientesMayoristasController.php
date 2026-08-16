<?php

namespace App\Http\Controllers\Operacion\Pedidos\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class InformePedidosClientesMayoristasController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);
        
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);
        
        $fechaSeleccionada = $request->get('fecha', Carbon::now('America/La_Paz')->format('Y-m-d'));
        
        $matriz = null;
        $detalle = null;
        $resumen = null;
        
        if ($fechaSeleccionada) {
            $datos = $this->obtenerDatosCombinados($clienteId, $fechaSeleccionada);
            $matriz = $datos['matriz'] ?? null;
            $detalle = $datos['detalle'] ?? null;
            $resumen = $datos['resumen'] ?? null;
        }
        
        return Inertia::render('Operacion/Pedidos/Reportes/InformePedidosClientesMayoristas', [
            'empresa' => $empresa,
            'operador' => $operador,
            'fechaSeleccionada' => $fechaSeleccionada,
            'matriz' => $matriz,
            'detalle' => $detalle,
            'resumen' => $resumen,
        ]);
    }

    private function obtenerDatosCombinados($clienteId, $fecha)
    {
        $pedidos = PedidoCliente::where('IdCliente', $clienteId)
            ->whereDate('FechaEntrega', $fecha)
            ->where('ActivoInactivo', 1)
            ->with([
                'sucursal',
                'operador.identificador',
                'detalles.contenedor',
                'detalles.producto'
            ])
            ->orderBy('IdSucursal')
            ->orderBy('NumeroPedido')
            ->get();

        if ($pedidos->isEmpty()) {
            return ['matriz' => null, 'detalle' => null, 'resumen' => null];
        }

        $productosUnicos = [];
        foreach ($pedidos as $pedido) {
            foreach ($pedido->detalles as $detalle) {
                $id = $detalle->IdProducto;
                if (!isset($productosUnicos[$id])) {
                    $productosUnicos[$id] = [
                        'id' => $id,
                        'nombre' => $detalle->producto->Descripcion ?? 'Sin nombre',
                        'orden' => $detalle->producto->OrdenInformes ?? 0
                    ];
                }
            }
        }
        
        usort($productosUnicos, function($a, $b) {
            return $a['orden'] <=> $b['orden'];
        });
        $productosUnicos = array_values($productosUnicos);

        $matriz = $this->construirMatrizJerarquica($pedidos, $productosUnicos);
        $detalle = $this->construirDetalleCompleto($pedidos);
        $resumen = $this->calcularResumenGeneral($pedidos);

        return ['matriz' => $matriz, 'detalle' => $detalle, 'resumen' => $resumen];
    }

    private function construirMatrizJerarquica($pedidos, $productos)
    {
        $sucursales = [];
        $numProductos = count($productos);
        $totalesGenerales = array_fill(0, $numProductos, 0);
        $totalGeneral = 0;
        
        foreach ($pedidos as $pedido) {
            $sucursalNombre = $pedido->sucursal->Nombre ?? 'Sin sucursal';
            $operadorNombre = $pedido->operador->identificador->Nombre ?? 'Sin operador';
            
            if (!isset($sucursales[$sucursalNombre])) {
                $sucursales[$sucursalNombre] = [
                    'nombre' => $sucursalNombre,
                    'operadores' => [],
                    'subtotal' => array_fill(0, $numProductos, 0),
                    'total_sucursal' => 0
                ];
            }
            
            if (!isset($sucursales[$sucursalNombre]['operadores'][$operadorNombre])) {
                $sucursales[$sucursalNombre]['operadores'][$operadorNombre] = [
                    'nombre' => $operadorNombre,
                    'valores' => array_fill(0, $numProductos, 0),
                    'total' => 0
                ];
            }
            
            foreach ($pedido->detalles as $detalle) {
                $indice = array_search($detalle->IdProducto, array_column($productos, 'id'));
                if ($indice !== false) {
                    $cantidad = floatval($detalle->Cantidad);
                    
                    $sucursales[$sucursalNombre]['operadores'][$operadorNombre]['valores'][$indice] += $cantidad;
                    $sucursales[$sucursalNombre]['operadores'][$operadorNombre]['total'] += $cantidad;
                    $sucursales[$sucursalNombre]['subtotal'][$indice] += $cantidad;
                    $sucursales[$sucursalNombre]['total_sucursal'] += $cantidad;
                    $totalesGenerales[$indice] += $cantidad;
                    $totalGeneral += $cantidad;
                }
            }
        }
        
        foreach ($sucursales as &$sucursal) {
            $sucursal['operadores'] = array_values($sucursal['operadores']);
        }
        
        return [
            'productos' => $productos,
            'sucursales' => array_values($sucursales),
            'totales_generales' => $totalesGenerales,
            'total_general' => $totalGeneral
        ];
    }

    private function construirDetalleCompleto($pedidos)
    {
        $detalle = [];
        
        foreach ($pedidos as $pedido) {
            $sucursalNombre = $pedido->sucursal->Nombre ?? 'Sin sucursal';
            $operadorNombre = $pedido->operador->identificador->Nombre ?? 'Sin operador';
            
            if (!isset($detalle[$sucursalNombre])) {
                $detalle[$sucursalNombre] = [
                    'sucursal' => $sucursalNombre,
                    'operadores' => [],
                    'total_sucursal' => 0
                ];
            }
            
            if (!isset($detalle[$sucursalNombre]['operadores'][$operadorNombre])) {
                $detalle[$sucursalNombre]['operadores'][$operadorNombre] = [
                    'nombre' => $operadorNombre,
                    'pedidos' => [],
                    'total_operador' => 0
                ];
            }
            
            $pedidoData = [
                'numero' => $pedido->NumeroPedido ?? '000000',
                'fecha_pedido' => $pedido->FechaPedido ? Carbon::parse($pedido->FechaPedido)->format('d/m/Y H:i') : 'No definida',
                'fecha_entrega' => $pedido->FechaEntrega ? Carbon::parse($pedido->FechaEntrega)->format('d/m/Y') : 'No definida',
                'operador' => $pedido->operador->identificador->Nombre ?? 'Sin operador',
                'contenedores' => [],
                'total_pedido' => 0
            ];
            
            foreach ($pedido->detalles as $detalleItem) {
                $contenedorId = $detalleItem->IdContenedor;
                
                if (!isset($pedidoData['contenedores'][$contenedorId])) {
                    $pedidoData['contenedores'][$contenedorId] = [
                        'codigo' => $detalleItem->contenedor->Codigo ?? '-',
                        'nombre' => $detalleItem->contenedor->Nombre ?? 'Contenedor',
                        'capacidad' => $detalleItem->contenedor->CapacidadTotal ?? 0,
                        'productos' => [],
                        'total' => 0
                    ];
                }
                
                $pedidoData['contenedores'][$contenedorId]['productos'][] = [
                    'nombre' => $detalleItem->producto->Descripcion ?? 'Sin nombre',
                    'cantidad' => floatval($detalleItem->Cantidad)
                ];
                
                $pedidoData['contenedores'][$contenedorId]['total'] += floatval($detalleItem->Cantidad);
                $pedidoData['total_pedido'] += floatval($detalleItem->Cantidad);
            }
            
            $detalle[$sucursalNombre]['operadores'][$operadorNombre]['pedidos'][] = $pedidoData;
            $detalle[$sucursalNombre]['operadores'][$operadorNombre]['total_operador'] += $pedidoData['total_pedido'];
            $detalle[$sucursalNombre]['total_sucursal'] += $pedidoData['total_pedido'];
        }
        
        foreach ($detalle as &$sucursal) {
            $sucursal['operadores'] = array_values($sucursal['operadores']);
        }
        
        return array_values($detalle);
    }

    private function calcularResumenGeneral($pedidos)
    {
        $totalUnidades = 0;
        $totalSucursales = [];
        $totalOperadores = [];
        $totalPedidos = 0;
        $totalContenedores = [];
        
        foreach ($pedidos as $pedido) {
            $totalUnidades += $pedido->detalles->sum('Cantidad');
            $totalSucursales[$pedido->IdSucursal] = true;
            $totalOperadores[$pedido->IdOperador] = true;
            $totalPedidos++;
            
            foreach ($pedido->detalles as $detalle) {
                $totalContenedores[$detalle->IdContenedor] = true;
            }
        }
        
        return [
            'total_unidades' => $totalUnidades,
            'total_sucursales' => count($totalSucursales),
            'total_operadores' => count($totalOperadores),
            'total_pedidos' => $totalPedidos,
            'total_contenedores' => count($totalContenedores),
        ];
    }

    // =============================================
    // PDF - SOLO RESUMEN (MATRIZ)
    // =============================================
    public function exportarPdfResumen(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        $fecha = $request->fecha;

        $datos = $this->obtenerDatosCombinados($clienteId, $fecha);
        
        if (!$datos['matriz']) {
            return redirect()->back()->with('error', 'No hay datos para la fecha seleccionada.');
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);

        $pdf = new \TCPDF('L', 'mm', 'Legal', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $primerPedido = $datos['detalle'][0]['operadores'][0]['pedidos'][0] ?? null;
        $fechaPedido = $primerPedido ? $primerPedido['fecha_pedido'] : Carbon::parse($fecha)->format('d/m/Y H:i');
        $fechaEntrega = $primerPedido ? $primerPedido['fecha_entrega'] : Carbon::parse($fecha)->format('d/m/Y');
        $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

        // HTML - SOLO RESUMEN
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: helvetica, sans-serif; font-size: 9px; color: #333; }
            .header {
                border-bottom: 3px solid #1a237e;
                padding-bottom: 6px;
                margin-bottom: 8px;
                width: 100%;
            }
            .header-table {
                width: 100%;
                border-collapse: collapse;
            }
            .header-table td {
                vertical-align: top;
                padding: 0;
                margin: 0;
            }
            .header-left {
                text-align: left;
                width: 45%;
            }
            .header-right {
                text-align: right;
                width: 55%;
            }
            .header-left .fecha-item {
                font-size: 8.5px;
                color: #333;
                padding: 1.5px 0;
                line-height: 1.2;
            }
            .header-left .fecha-item strong {
                color: #1a237e;
                font-weight: bold;
            }
            .header-right h1 {
                font-size: 18px;
                font-weight: bold;
                margin: 0;
                padding: 0;
                color: #1a237e;
                letter-spacing: 1px;
                line-height: 1.1;
            }
            .header-right .empresa {
                font-size: 12px;
                font-weight: bold;
                margin: 2px 0 0 0;
                padding: 0;
                color: #333;
                line-height: 1.1;
            }
            .seccion-titulo {
                font-size: 12px;
                font-weight: bold;
                background: #e3f2fd;
                padding: 3px 8px;
                margin: 10px 0 5px 0;
                border-left: 4px solid #1a237e;
                color: #0d47a1;
                border-radius: 2px;
                clear: both;
            }
            .matriz-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 7px;
                margin: 4px 0;
            }
            .matriz-table th {
                background: #d9e1f2;
                border: 1px solid #999;
                padding: 4px 3px;
                text-align: center;
                font-weight: bold;
                font-size: 6.5px;
                color: #1a237e;
            }
            .matriz-table td {
                border: 1px solid #999;
                padding: 3px 2px;
                text-align: center;
            }
            .matriz-table .sucursal-titulo {
                background: #e8eaf6;
                font-weight: bold;
                font-size: 10px;
                padding: 5px 8px;
                text-align: left;
                color: #1a237e;
                border-left: 3px solid #1a237e;
            }
            .matriz-table .operador-fila td {
                border-top: none;
            }
            .matriz-table .operador-nombre {
                text-align: left;
                padding-left: 20px;
                font-weight: normal;
                font-size: 7.5px;
                border-top: none;
                color: #555;
                background: #fafafa;
            }
            .matriz-table .operador-cantidad {
                border-top: none;
                font-size: 7px;
                background: #fafafa;
            }
            .matriz-table .operador-total {
                border-top: none;
                font-weight: bold;
                font-size: 7px;
                background: #fafafa;
            }
            .matriz-table .subtotal td {
                background: #e8f5e9;
                font-weight: bold;
                font-size: 7.5px;
                color: #1b5e20;
            }
            .matriz-table .total td {
                background: #fff3e0;
                font-weight: bold;
                font-size: 8px;
                color: #e65100;
            }
            .pie-pagina {
                font-size: 7px;
                text-align: center;
                margin-top: 10px;
                color: #999;
                border-top: 1px solid #ddd;
                padding-top: 5px;
            }
        </style>
        </head>
        <body>
        
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <div class="fecha-item"><strong>Fecha Pedido:</strong> ' . $fechaPedido . '</div>
                        <div class="fecha-item"><strong>Fecha Entrega:</strong> ' . $fechaEntrega . '</div>
                        <div class="fecha-item"><strong>Fecha Impresion:</strong> ' . $fechaImpresion . '</div>
                    </td>
                    <td class="header-right">
                        <h1>PEDIDO DE PRODUCTOS</h1>
                        <div class="empresa">' . htmlspecialchars($empresa->Nombre ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                    </td>
                </tr>
            </table>
        </div>
        ';

        $html .= '<div class="seccion-titulo">RESUMEN POR SUCURSAL Y OPERADOR</div>';
        $html .= $this->generarHTMLMatriz($datos['matriz']);
        $html .= '<div class="pie-pagina">Documento generado automaticamente por el sistema - ' . $fechaImpresion . '</div>';

        $html .= '</body></html>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $nombreArchivo = 'Resumen_Pedidos_' . $fecha . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit();
    }

    // =============================================
    // PDF - SOLO DETALLE
    // =============================================
    public function exportarPdfDetalle(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        $fecha = $request->fecha;

        $datos = $this->obtenerDatosCombinados($clienteId, $fecha);
        
        if (!$datos['matriz']) {
            return redirect()->back()->with('error', 'No hay datos para la fecha seleccionada.');
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);

        $pdf = new \TCPDF('P', 'mm', 'Letter', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $primerPedido = $datos['detalle'][0]['operadores'][0]['pedidos'][0] ?? null;
        $fechaPedido = $primerPedido ? $primerPedido['fecha_pedido'] : Carbon::parse($fecha)->format('d/m/Y H:i');
        $fechaEntrega = $primerPedido ? $primerPedido['fecha_entrega'] : Carbon::parse($fecha)->format('d/m/Y');
        $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

        // HTML - SOLO DETALLE (Vertical)
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: helvetica, sans-serif; font-size: 9px; color: #333; }
            .header {
                border-bottom: 3px solid #1a237e;
                padding-bottom: 6px;
                margin-bottom: 8px;
                width: 100%;
            }
            .header-table {
                width: 100%;
                border-collapse: collapse;
            }
            .header-table td {
                vertical-align: top;
                padding: 0;
                margin: 0;
            }
            .header-left {
                text-align: left;
                width: 45%;
            }
            .header-right {
                text-align: right;
                width: 55%;
            }
            .header-left .fecha-item {
                font-size: 8.5px;
                color: #333;
                padding: 1.5px 0;
                line-height: 1.2;
            }
            .header-left .fecha-item strong {
                color: #1a237e;
                font-weight: bold;
            }
            .header-right h1 {
                font-size: 18px;
                font-weight: bold;
                margin: 0;
                padding: 0;
                color: #1a237e;
                letter-spacing: 1px;
                line-height: 1.1;
            }
            .header-right .empresa {
                font-size: 12px;
                font-weight: bold;
                margin: 2px 0 0 0;
                padding: 0;
                color: #333;
                line-height: 1.1;
            }
            .seccion-titulo {
                font-size: 12px;
                font-weight: bold;
                background: #e3f2fd;
                padding: 3px 8px;
                margin: 10px 0 5px 0;
                border-left: 4px solid #1a237e;
                color: #0d47a1;
                border-radius: 2px;
                clear: both;
            }
            .detalle-sucursal {
                font-size: 12px;
                font-weight: bold;
                background: #e3f2fd;
                padding: 5px 10px;
                margin: 8px 0 4px 0;
                border-left: 4px solid #1a237e;
                color: #0d47a1;
                border-radius: 2px;
            }
            .detalle-operador {
                font-size: 10px;
                font-weight: bold;
                margin-left: 12px;
                padding: 3px 8px;
                background: #f5f5f5;
                border-left: 3px solid #78909c;
                color: #37474f;
                border-radius: 2px;
            }
            .detalle-pedido {
                font-size: 8.5px;
                margin-left: 20px;
                padding: 3px 6px;
                background: #fafafa;
                color: #455a64;
                border-bottom: 1px dashed #ddd;
            }
            .detalle-contenedor {
                font-size: 8px;
                margin-left: 28px;
                border: 1px solid #ddd;
                padding: 5px 8px;
                margin-top: 3px;
                background: #fff;
                border-radius: 3px;
            }
            .detalle-producto {
                font-size: 7.5px;
                margin-left: 14px;
                padding: 2px 0;
                border-bottom: 1px dotted #eee;
                color: #444;
            }
            .detalle-producto .cantidad {
                font-weight: bold;
                color: #1a237e;
            }
            .detalle-total {
                font-weight: bold;
                text-align: right;
                font-size: 8px;
                border-top: 1px solid #555;
                padding-top: 3px;
                margin-top: 3px;
                color: #1a237e;
            }
            .pie-pagina {
                font-size: 7px;
                text-align: center;
                margin-top: 10px;
                color: #999;
                border-top: 1px solid #ddd;
                padding-top: 5px;
            }
        </style>
        </head>
        <body>
        
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <div class="fecha-item"><strong>Fecha Pedido:</strong> ' . $fechaPedido . '</div>
                        <div class="fecha-item"><strong>Fecha Entrega:</strong> ' . $fechaEntrega . '</div>
                        <div class="fecha-item"><strong>Fecha Impresion:</strong> ' . $fechaImpresion . '</div>
                    </td>
                    <td class="header-right">
                        <h1>PEDIDO DE PRODUCTOS</h1>
                        <div class="empresa">' . htmlspecialchars($empresa->Nombre ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                    </td>
                </tr>
            </table>
        </div>
        ';

        $html .= '<div class="seccion-titulo">DETALLE DE PEDIDOS</div>';
        $html .= $this->generarHTMLDetalle($datos['detalle']);
        $html .= '<div class="pie-pagina">Documento generado automaticamente por el sistema - ' . $fechaImpresion . '</div>';

        $html .= '</body></html>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $nombreArchivo = 'Detalle_Pedidos_' . $fecha . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit();
    }

    private function generarHTMLMatriz($matriz)
    {
        $html = '<table class="matriz-table">';
        $html .= '<tr><th style="width:16%;">SUCURSAL</th>';
        foreach ($matriz['productos'] as $p) {
            $html .= '<th style="width:' . (64/count($matriz['productos'])) . '%;">' . htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '<th style="width:8%;">TOTAL</th></tr>';

        foreach ($matriz['sucursales'] as $sucursal) {
            $html .= '<tr><td class="sucursal-titulo" colspan="' . (count($matriz['productos']) + 2) . '">' . htmlspecialchars($sucursal['nombre'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
            
            foreach ($sucursal['operadores'] as $operador) {
                $html .= '<tr class="operador-fila">';
                $html .= '<td class="operador-nombre">- ' . htmlspecialchars($operador['nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
                foreach ($operador['valores'] as $valor) {
                    $html .= '<td class="operador-cantidad">' . number_format($valor, 2) . '</td>';
                }
                $html .= '<td class="operador-total">' . number_format($operador['total'], 2) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '<tr class="subtotal">';
            $html .= '<td style="text-align:right;padding-right:10px;">SUBTOTAL</td>';
            foreach ($sucursal['subtotal'] as $valor) {
                $html .= '<td>' . number_format($valor, 2) . '</td>';
            }
            $html .= '<td>' . number_format($sucursal['total_sucursal'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr class="total">';
        $html .= '<td style="text-align:right;padding-right:10px;">TOTAL GENERAL</td>';
        foreach ($matriz['totales_generales'] as $valor) {
            $html .= '<td>' . number_format($valor, 2) . '</td>';
        }
        $html .= '<td>' . number_format($matriz['total_general'], 2) . '</td>';
        $html .= '</tr>';
        
        $html .= '</table>';
        return $html;
    }

    private function generarHTMLDetalle($detalle)
    {
        $html = '';
        foreach ($detalle as $s) {
            $html .= '<div class="detalle-sucursal">SUCURSAL: ' . htmlspecialchars($s['sucursal'], ENT_QUOTES, 'UTF-8') . ' - Total: ' . number_format($s['total_sucursal'], 2) . ' und</div>';
            
            foreach ($s['operadores'] as $op) {
                $html .= '<div class="detalle-operador">OPERADOR: ' . htmlspecialchars($op['nombre'], ENT_QUOTES, 'UTF-8') . ' - Total: ' . number_format($op['total_operador'], 2) . ' und</div>';
                
                foreach ($op['pedidos'] as $p) {
                    $html .= '<div class="detalle-pedido">';
                    $html .= 'Pedido #' . $p['numero'];
                    $html .= ' | Fecha Pedido: ' . $p['fecha_pedido'];
                    $html .= ' | Fecha Entrega: ' . $p['fecha_entrega'];
                    $html .= ' | Total: ' . number_format($p['total_pedido'], 2) . ' und';
                    $html .= '</div>';
                    
                    foreach ($p['contenedores'] as $con) {
                        $html .= '<div class="detalle-contenedor">';
                        $html .= '<b>' . htmlspecialchars($con['nombre'], ENT_QUOTES, 'UTF-8') . '</b> (Cod: ' . $con['codigo'] . ' | Cap: ' . number_format($con['capacidad'], 0) . ' und)';
                        
                        foreach ($con['productos'] as $prod) {
                            $html .= '<div class="detalle-producto">- ' . htmlspecialchars($prod['nombre'], ENT_QUOTES, 'UTF-8') . ' <span class="cantidad">' . number_format($prod['cantidad'], 2) . ' und</span></div>';
                        }
                        
                        $html .= '<div class="detalle-total">TOTAL CONTENEDOR: ' . number_format($con['total'], 2) . ' und</div>';
                        $html .= '</div>';
                    }
                }
            }
        }
        return $html;
    }
}