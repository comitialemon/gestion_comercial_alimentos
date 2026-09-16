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
        $operadorFiltro = $request->get('operador_id');
        
        // ✅ Nombre del operador filtrado (para mostrar en el input)
        $operadorFiltroNombre = null;
        if ($operadorFiltro) {
            $op = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as op')
                ->join('todos_identificador as iden', 'iden.IdIdentificador', '=', 'op.IdIdentificador')
                ->where('op.IdOperador', $operadorFiltro)
                ->first(['iden.Nombre as nombre']);
            $operadorFiltroNombre = $op ? $op->nombre : null;
        }
        
        $matriz = null;
        $detalle = null;
        $resumen = null;
        
        if ($fechaSeleccionada) {
            $datos = $this->obtenerDatosCombinados($clienteId, $fechaSeleccionada, $operadorFiltro);
            $matriz = $datos['matriz'] ?? null;
            $detalle = $datos['detalle'] ?? null;
            $resumen = $datos['resumen'] ?? null;
        }
        
        return Inertia::render('Operacion/Pedidos/Reportes/InformePedidosClientesMayoristas', [
            'empresa' => $empresa,
            'operador' => $operador,
            'fechaSeleccionada' => $fechaSeleccionada,
            'operadorFiltro' => $operadorFiltro,
            'operadorFiltroNombre' => $operadorFiltroNombre, // ✅ NUEVO
            'matriz' => $matriz,
            'detalle' => $detalle,
            'resumen' => $resumen,
        ]);
    }
    /**
     * ✅ Obtener operadores que tienen pedidos en esa fecha para ese cliente
     * Se consulta desde la tabla de pedidos para no depender de relaciones inexistentes
     */
    private function obtenerOperadoresConPedidos($clienteId, $fecha)
    {
        return DB::connection('mysql_gestion_comercial_alimentos')
            ->table('pedidos_clientes as pc')
            ->join('todos_operador as op', 'op.IdOperador', '=', 'pc.IdOperador')
            ->join('todos_identificador as iden', 'iden.IdIdentificador', '=', 'op.IdIdentificador')
            ->where('pc.IdCliente', $clienteId)
            ->whereDate('pc.FechaEntrega', $fecha)
            ->where('pc.ActivoInactivo', 1)
            ->whereNotNull('pc.IdOperador')
            ->groupBy('op.IdOperador', 'iden.Nombre')
            ->orderBy('iden.Nombre')
            ->get([
                'op.IdOperador as id',
                'iden.Nombre as nombre',
            ]);
    }
    /**
     * ✅ BUSCAR OPERADORES PARA AUTOCOMPLETE (filtrado por fecha y cliente)
     * Devuelve solo operadores que tienen pedidos en esa fecha
     */
    public function buscarOperadores(Request $request)
    {
        $clienteId = session('cliente_id');
        $fecha = $request->get('fecha');
        $termino = $request->get('q', '');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('pedidos_clientes as pc')
            ->join('todos_operador as op', 'op.IdOperador', '=', 'pc.IdOperador')
            ->join('todos_identificador as iden', 'iden.IdIdentificador', '=', 'op.IdIdentificador')
            ->where('pc.IdCliente', $clienteId)
            ->where('pc.ActivoInactivo', 1)
            ->whereNotNull('pc.IdOperador');

        if (!empty($fecha)) {
            $query->whereDate('pc.FechaEntrega', $fecha);
        }

        if (!empty($termino)) {
            $query->where('iden.Nombre', 'LIKE', '%' . $termino . '%');
        }

        $operadores = $query
            ->groupBy('op.IdOperador', 'iden.Nombre')
            ->orderBy('iden.Nombre')
            ->limit(20)
            ->get([
                'op.IdOperador as id',
                'iden.Nombre as nombre',
            ]);

        return response()->json([
            'success' => true,
            'operadores' => $operadores,
        ]);
    }
    private function obtenerDatosCombinados($clienteId, $fecha, $operadorFiltro = null)
    {
        $query = PedidoCliente::where('IdCliente', $clienteId)
            ->whereDate('FechaEntrega', $fecha)
            ->where('ActivoInactivo', 1)
            ->with([
                'sucursal',
                'operador.identificador',
                'detalles.contenedor',
                'detalles.producto'
            ]);
        
        // ✅ FILTRO POR OPERADOR
        if (!empty($operadorFiltro)) {
            $query->where('IdOperador', $operadorFiltro);
        }
        
        $pedidos = $query
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
            
            // ✅ AGRUPAR POR OrdenContenedor (cada bloque independiente, igual que el PDF)
            $detallesAgrupados = $pedido->detalles
                ->groupBy('OrdenContenedor')
                ->sortKeys();
            
            foreach ($detallesAgrupados as $orden => $items) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                
                $totalContenedor = $items->sum(function ($it) {
                    return floatval($it->Cantidad);
                });
                
                $pedidoData['contenedores'][] = [
                    'orden' => intval($orden),                                    // ✅ NUEVO: para mostrarlo
                    'codigo' => $contenedor->Codigo ?? '-',
                    'nombre' => $contenedor->Nombre ?? ('Contenedor ' . ($contenedor->Codigo ?? '-')),
                    'capacidad' => $contenedor->CapacidadTotal ?? 0,
                    'productos' => $items->map(function ($item) {
                        return [
                            'nombre' => $item->producto->Descripcion ?? 'Sin nombre',
                            'cantidad' => floatval($item->Cantidad),
                        ];
                    })->values()->toArray(),
                    'total' => $totalContenedor,
                ];
                
                $pedidoData['total_pedido'] += $totalContenedor;
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
    // PDF - DETALLE (recorriendo TODOS los pedidos de la fecha,
    //                 con filtro opcional por operador)
    // =============================================
    public function exportarPdfDetalle(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
            'operador_id' => 'nullable|integer',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        $fecha = $request->fecha;
        $operadorFiltro = $request->operador_id; // ✅ NUEVO

        // ✅ Traer TODOS los pedidos de la fecha (con filtro opcional por operador)
        $query = PedidoCliente::where('IdCliente', $clienteId)
            ->whereDate('FechaEntrega', $fecha)
            ->where('ActivoInactivo', 1)
            ->with([
                'sucursal',
                'operador.identificador',
                'detalles.producto',
                'detalles.contenedor.tipoContenedor',
            ]);

        // ✅ FILTRO POR OPERADOR
        if (!empty($operadorFiltro)) {
            $query->where('IdOperador', $operadorFiltro);
        }

        $pedidos = $query
            ->orderBy('IdSucursal')
            ->orderBy('NumeroPedido')
            ->get();

        if ($pedidos->isEmpty()) {
            return redirect()->back()->with('error', 'No hay datos para los filtros seleccionados.');
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $operadorLogueado = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);

        // ============================================================
        // CREAR PDF
        // ============================================================
        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);

        // ============================================================
        // RECORRER CADA PEDIDO Y GENERAR SU BLOQUE COMPLETO
        // ============================================================
        foreach ($pedidos as $index => $pedido) {

            $pdf->AddPage();

            // ============================================================
            // HEADER EMPRESA
            // ============================================================
            $y = 8;

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 5, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'), 0, 1, 'C');
            $y += 5;

            $pdf->SetFont('helvetica', '', 7.5);
            if (!empty($pedido->sucursal->Nombre)) {
                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 3.5, $pedido->sucursal->Nombre, 0, 1, 'C');
                $y += 3.5;
            }
            if (!empty($pedido->sucursal->Direccion)) {
                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 3.5, $pedido->sucursal->Direccion, 0, 1, 'C');
                $y += 3.5;
            }
            if (!empty($empresa->NIT)) {
                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 3.5, 'NIT: ' . $empresa->NIT, 0, 1, 'C');
                $y += 3.5;
            }

            $y += 2;
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line(10, $y, 206, $y);
            $y += 5;

            // ============================================================
            // TÍTULO
            // ============================================================
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetTextColor(30, 60, 120);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 7, 'PEDIDO DE PRODUCTOS', 0, 1, 'C');
            $y += 7;

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 5, 'N° ' . ($pedido->NumeroPedido ?? '000000'), 0, 1, 'C');
            $y += 8;

            // ============================================================
            // INFO PEDIDO EN 2 COLUMNAS
            // ============================================================
            $pdf->SetFont('helvetica', '', 8);

            $colIzq_label = 12;
            $colIzq_valor = 45;
            $colDer_label = 108;
            $colDer_valor = 138;
            $yInfo = $y;
            $altoFila = 5;

            // COLUMNA IZQUIERDA
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colIzq_label, $yInfo);
            $pdf->Cell(33, $altoFila, 'Fecha Pedido:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colIzq_valor, $yInfo);
            $pdf->Cell(60, $altoFila, $pedido->FechaPedido ? Carbon::parse($pedido->FechaPedido)->format('d/m/Y H:i') : '-', 0, 0, 'L');
            $yInfo += $altoFila;

            if ($pedido->FechaEntrega) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colIzq_label, $yInfo);
                $pdf->Cell(33, $altoFila, 'Fecha Entrega:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colIzq_valor, $yInfo);
                $pdf->Cell(60, $altoFila, Carbon::parse($pedido->FechaEntrega)->format('d/m/Y'), 0, 0, 'L');
                $yInfo += $altoFila;
            }

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colIzq_label, $yInfo);
            $pdf->Cell(33, $altoFila, 'Operador:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colIzq_valor, $yInfo);
            $pdf->Cell(60, $altoFila, $pedido->operador->identificador->Nombre ?? 'Sin operador', 0, 0, 'L');
            $yInfo += $altoFila;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colIzq_label, $yInfo);
            $pdf->Cell(33, $altoFila, 'Sucursal:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colIzq_valor, $yInfo);
            $pdf->Cell(60, $altoFila, $pedido->sucursal->Nombre ?? 'Sin sucursal', 0, 0, 'L');
            $yInfo += $altoFila;

            // COLUMNA DERECHA
            $yInfoDer = $y;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colDer_label, $yInfoDer);
            $pdf->Cell(30, $altoFila, 'Estado:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colDer_valor, $yInfoDer);
            $pdf->Cell(58, $altoFila, $pedido->EstadoPedido ?? 'Pendiente', 0, 0, 'L');
            $yInfoDer += $altoFila;

            // CONFIG OPERADOR (Ciudad/Provincia/Destino)
            $configOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_operadores_clientes')
                ->where('IdOperador', $pedido->IdOperador)
                ->first();

            $destino = null;
            $tipoUbicacion = null;

            if ($configOperador) {
                if (isset($configOperador->Ciudad) && $configOperador->Ciudad == 1) {
                    $tipoUbicacion = 'Ciudad';
                } elseif (isset($configOperador->Provincia) && $configOperador->Provincia == 1) {
                    $tipoUbicacion = 'Provincia';
                }
                $destino = $configOperador->Destino ?? null;
            }

            if ($tipoUbicacion) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colDer_label, $yInfoDer);
                $pdf->Cell(30, $altoFila, 'Tipo:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colDer_valor, $yInfoDer);
                $pdf->Cell(58, $altoFila, $tipoUbicacion, 0, 0, 'L');
                $yInfoDer += $altoFila;
            }

            if ($destino) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colDer_label, $yInfoDer);
                $pdf->Cell(30, $altoFila, 'Destino:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colDer_valor, $yInfoDer);
                $pdf->Cell(58, $altoFila, $destino, 0, 0, 'L');
                $yInfoDer += $altoFila;
            }

            $y = max($yInfo, $yInfoDer) + 3;

            // ============================================================
            // OBSERVACIONES
            // ============================================================
            if (!empty($pedido->Observaciones)) {
                $pdf->SetDrawColor(251, 191, 36);
                $pdf->SetFillColor(255, 251, 235);

                $pdf->SetFont('helvetica', '', 7.5);
                $alturaTexto = $pdf->getStringHeight(180, $pedido->Observaciones);
                $alturaCaja = max(12, 6 + $alturaTexto + 3);

                $pdf->RoundedRect(10, $y, 196, $alturaCaja, 1.5, '1111', 'DF');

                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetTextColor(146, 64, 14);
                $pdf->SetXY(12, $y + 2);
                $pdf->Cell(100, 4, 'OBSERVACIONES:', 0, 0, 'L');

                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetTextColor(80, 40, 10);
                $pdf->SetXY(12, $y + 6.5);
                $pdf->MultiCell(192, 3, $pedido->Observaciones, 0, 'L');

                $y += $alturaCaja + 4;

                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetFillColor(255, 255, 255);
            }

            // ============================================================
            // AGRUPAR POR OrdenContenedor
            // ============================================================
            $detallesAgrupados = $pedido->detalles
                ->groupBy('OrdenContenedor')
                ->map(function ($items, $orden) {
                    $primerItem = $items->first();
                    $contenedor = $primerItem->contenedor;

                    $totalUnidadesContenedor = $items->sum('Cantidad');
                    $subtotal = $items->sum(function ($item) {
                        return $item->Cantidad * $item->Precio;
                    });

                    return [
                        'OrdenContenedor' => intval($orden),
                        'IdContenedor' => $primerItem->IdContenedor,
                        'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                        'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                        'productos' => $items->map(function ($item) {
                            return [
                                'IdProducto' => $item->IdProducto,
                                'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                                'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                                'Cantidad' => $item->Cantidad,
                                'Precio' => $item->Precio,
                                'Subtotal' => $item->Cantidad * $item->Precio,
                            ];
                        })->values()->toArray(),
                        'total_unidades' => $totalUnidadesContenedor,
                        'subtotal' => $subtotal,
                    ];
                })
                ->sortBy('IdContenedor')
                ->values();

            // ============================================================
            // CABECERA TABLA
            // ============================================================
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(180, 180, 180);

            $pdf->SetXY(10, $y);
            $pdf->Cell(8, 5, '#', 'TB', 0, 'C', 1);
            $pdf->Cell(72, 5, 'PRODUCTO', 'TB', 0, 'L', 1);
            $pdf->Cell(24, 5, 'CANTIDAD', 'TB', 0, 'C', 1);
            $pdf->Cell(30, 5, 'PRECIO UNIT.', 'TB', 0, 'C', 1);
            $pdf->Cell(62, 5, 'SUBTOTAL', 'TB', 1, 'C', 1);
            $y += 5;

            $pdf->SetFont('helvetica', '', 7);
            $contador = 0;

            // ============================================================
            // LISTA POR CONTENEDOR (cada contenedor = bloque independiente)
            // ============================================================
            foreach ($detallesAgrupados as $idx => $grupo) {
                if ($idx > 0) {
                    $y += 3;
                }

                // HEADER CONTENEDOR
                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->SetFillColor(225, 238, 255);
                $pdf->SetTextColor(20, 50, 110);

                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 6, '', 'LTR', 1, 'L', 1);

                $pdf->SetXY(10, $y);
                $pdf->Cell(110, 6, '  [' . $grupo['Codigo'] . ']  #' . $grupo['OrdenContenedor'], 'L', 0, 'L', 1);
                $pdf->Cell(86, 6, 'Cap: ' . number_format($grupo['CapacidadTotal'], 0, ',', '.') . ' und   ', 'R', 1, 'R', 1);
                $y += 6;

                $pdf->SetTextColor(0, 0, 0);

                // PRODUCTOS
                $pdf->SetFont('helvetica', '', 7);
                $fill = false;

                foreach ($grupo['productos'] as $producto) {
                    $contador++;
                    $nombreProducto = $producto['Descripcion'] ?? '-';
                    if (mb_strlen($nombreProducto, 'UTF-8') > 42) {
                        $nombreProducto = mb_substr($nombreProducto, 0, 40, 'UTF-8') . '...';
                    }

                    $pdf->SetXY(10, $y);
                    $pdf->Cell(8, 4, $contador . '.', 'LR', 0, 'C', $fill);
                    $pdf->Cell(72, 4, ' ' . $nombreProducto, 'LR', 0, 'L', $fill);
                    $pdf->Cell(24, 4, number_format($producto['Cantidad'], 0, ',', '.'), 'LR', 0, 'C', $fill);
                    $pdf->Cell(30, 4, number_format($producto['Precio'], 2, ',', '.'), 'LR', 0, 'C', $fill);
                    $pdf->Cell(62, 4, number_format($producto['Subtotal'], 2, ',', '.'), 'LR', 1, 'C', $fill);
                    $y += 4;
                    $fill = !$fill;
                }

                // TOTAL CONTENEDOR
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetFillColor(235, 245, 255);
                $pdf->SetXY(10, $y);
                $pdf->Cell(8, 4.5, '', 'LRB', 0, 'C', 1);
                $pdf->Cell(72, 4.5, 'TOTAL ' . $grupo['Codigo'] . ' #' . $grupo['OrdenContenedor'], 'LRB', 0, 'R', 1);
                $pdf->Cell(24, 4.5, number_format($grupo['total_unidades'], 0, ',', '.'), 'LRB', 0, 'C', 1);
                $pdf->Cell(30, 4.5, '', 'LRB', 0, 'C', 1);
                $pdf->Cell(62, 4.5, 'Bs. ' . number_format($grupo['subtotal'], 2, ',', '.'), 'LRB', 1, 'C', 1);
                $y += 4.5;
            }

            $y += 5;

            // ============================================================
            // TOTALES DEL PEDIDO
            // ============================================================
            $totalUnidades = $pedido->detalles->sum('Cantidad');
            $totalContenedores = $detallesAgrupados->count();
            $totalGeneral = $pedido->detalles->sum(function ($item) {
                return $item->Cantidad * $item->Precio;
            });

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);

            $pdf->SetXY(10, $y);
            $pdf->Cell(98, 5, 'Total Contenedores: ' . number_format($totalContenedores, 0, ',', '.'), 0, 0, 'L');
            $pdf->Cell(98, 5, 'Total Unidades: ' . number_format($totalUnidades, 0, ',', '.'), 0, 0, 'L');
            $y += 6;

            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line(10, $y, 206, $y);
            $y += 4;

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(20, 50, 110);
            $pdf->SetXY(10, $y);
            $pdf->Cell(136, 7, 'TOTAL GENERAL DEL PEDIDO', 0, 0, 'R');
            $pdf->SetXY(146, $y);
            $pdf->Cell(60, 7, 'Bs. ' . number_format($totalGeneral, 2, ',', '.'), 0, 1, 'R');
            $y += 10;

            // ============================================================
            // RESUMEN POR TIPO DE CONTENEDOR
            // ============================================================
            $resumenPorTipo = $detallesAgrupados
                ->groupBy('Codigo')
                ->map(function ($items, $codigo) {
                    return [
                        'Codigo' => $codigo,
                        'cantidad_contenedores' => $items->count(),
                        'total_unidades' => $items->sum('total_unidades'),
                        'subtotal' => $items->sum('subtotal'),
                    ];
                })
                ->sortBy('Codigo')
                ->values();

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(30, 60, 120);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 6, 'RESUMEN POR TIPO DE CONTENEDOR', 0, 1, 'C');
            $y += 7;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(180, 180, 180);

            $pdf->SetXY(10, $y);
            $pdf->Cell(90, 5, 'CONTENEDOR', 'TB', 0, 'L', 1);
            $pdf->Cell(30, 5, 'CANT.', 'TB', 0, 'C', 1);
            $pdf->Cell(30, 5, 'UNIDADES', 'TB', 0, 'C', 1);
            $pdf->Cell(46, 5, 'SUBTOTAL', 'TB', 1, 'C', 1);
            $y += 5;

            $pdf->SetFont('helvetica', '', 8);
            $fill = false;

            foreach ($resumenPorTipo as $item) {
                $pdf->SetXY(10, $y);
                $pdf->Cell(90, 5, '  ' . $item['Codigo'], 'LR', 0, 'L', $fill);
                $pdf->Cell(30, 5, $item['cantidad_contenedores'] . ' cont.', 'LR', 0, 'C', $fill);
                $pdf->Cell(30, 5, number_format($item['total_unidades'], 0, ',', '.') . ' und', 'LR', 0, 'C', $fill);
                $pdf->Cell(46, 5, 'Bs. ' . number_format($item['subtotal'], 2, ',', '.'), 'LR', 1, 'C', $fill);
                $y += 5;
                $fill = !$fill;
            }

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(235, 245, 255);
            $pdf->SetXY(10, $y);
            $pdf->Cell(90, 5, '  TOTAL GENERAL', 'LRB', 0, 'R', 1);
            $pdf->Cell(30, 5, $totalContenedores . ' cont.', 'LRB', 0, 'C', 1);
            $pdf->Cell(30, 5, number_format($totalUnidades, 0, ',', '.') . ' und', 'LRB', 0, 'C', 1);
            $pdf->Cell(46, 5, 'Bs. ' . number_format($totalGeneral, 2, ',', '.'), 'LRB', 1, 'C', 1);
            $y += 10;

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);

            // ============================================================
            // FIRMAS
            // ============================================================
            $y += 15;
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8);

            $pdf->SetXY(25, $y);
            $pdf->Cell(70, 0.3, '', 'T', 0, 'C');
            $pdf->SetXY(115, $y);
            $pdf->Cell(70, 0.3, '', 'T', 1, 'C');

            $pdf->SetXY(25, $y + 1);
            $pdf->Cell(70, 4, 'Firma Operador', 0, 0, 'C');
            $pdf->SetXY(115, $y + 1);
            $pdf->Cell(70, 4, 'Firma Recibido', 0, 1, 'C');
        }

        // ============================================================
        // SALIDA
        // ============================================================
        if (ob_get_length()) {
            ob_end_clean();
        }

        $nombreArchivo = 'Detalle_Pedidos_' . $fecha . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit;
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