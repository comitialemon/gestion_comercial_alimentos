<?php

namespace App\Http\Controllers\Gestion\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Impuestos\VentaEstado;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class InformeVentasController extends Controller
{
    /**
     * 📋 GRID DE INFORME DE VENTAS
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // =============================================
        // CONSULTA PRINCIPAL
        // =============================================
        $query = Venta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->where('IdEstado', 1);
        
        // FILTROS
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('IdClienteSucursal', $request->sucursal_id);
        }
        
        if ($request->filled('vendedor_id') && $request->vendedor_id !== '') {
            $query->where('IdOperadorIngresa', $request->vendedor_id);
        }
        
        if ($request->filled('comisionista_id') && $request->comisionista_id !== '') {
            $query->where('IdComisionista', $request->comisionista_id);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('NumeroFactura', 'like', "%{$search}%")
                  ->orWhere('NumeroAutorizacion', 'like', "%{$search}%");
            });
        }
        
        $ventas = $query->orderBy('FechaVenta', 'desc')
            ->paginate(20)
            ->withQueryString();
        
        // =============================================
        // ENRIQUECER DATOS
        // =============================================
        $ventas->getCollection()->transform(function($venta) {
            $estado = VentaEstado::find($venta->IdEstado);
            $venta->estado_nombre = $estado ? $estado->Detalle : 'Desconocido';
            $venta->estado_abrev = $estado ? $estado->Abreviacion : '?';
            
            $cliente = Identificador::find($venta->IdNIT);
            $venta->cliente_nit = $cliente ? $cliente->CI_NIT : '0';
            
            $operador = Operador::with('identificador')->find($venta->IdOperadorIngresa);
            $venta->vendedor_nombre = $operador && $operador->identificador ? $operador->identificador->Nombre : 'Desconocido';
            
            $sucursal = ClienteSucursal::find($venta->IdClienteSucursal);
            $venta->sucursal_nombre = $sucursal ? $sucursal->Nombre : 'Sin sucursal';
            $venta->sucursal_numero = $sucursal ? $sucursal->NumeroSucursal : null;
            
            if ($venta->IdComisionista) {
                $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_comisionitas as c')
                    ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                    ->where('c.IdComisionista', $venta->IdComisionista)
                    ->first();
                $venta->comisionista_nombre = $comisionista ? $comisionista->Nombre : 'Sin comisionista';
            } else {
                $venta->comisionista_nombre = 'Sin comisionista';
            }
            
            return $venta;
        });
        
        // =============================================
        // CATÁLOGOS PARA FILTROS
        // =============================================
        
        // Sucursales
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // Vendedores (ActivoInactivo = 0 = ACTIVO)
        $vendedores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador_sucursaldb as tos')
            ->join('todos_operador as t', 'tos.IdOperador', '=', 't.IdOperador')
            ->join('todos_identificador as ti', 't.IdIdentificador', '=', 'ti.IdIdentificador')
            ->where('tos.IdCliente', $clienteId)
            ->where('t.ActivoInactivo', 0)
            ->select(
                't.IdOperador as id',
                DB::raw("CONCAT(ti.CI_NIT, ' - ', ti.Nombre) as nombre_completo"),
                'ti.IdIdentificador',
                'ti.Nombre'
            )
            ->distinct()
            ->orderBy('ti.Nombre', 'asc')
            ->get();
        
        // Fallback: si no hay en la relación, traer todos los activos
        if ($vendedores->isEmpty()) {
            $vendedores = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as t')
                ->join('todos_identificador as ti', 't.IdIdentificador', '=', 'ti.IdIdentificador')
                ->where('t.ActivoInactivo', 0)
                ->select(
                    't.IdOperador as id',
                    DB::raw("CONCAT(ti.CI_NIT, ' - ', ti.Nombre) as nombre_completo"),
                    'ti.IdIdentificador',
                    'ti.Nombre'
                )
                ->orderBy('ti.Nombre', 'asc')
                ->get();
        }
        
        // Comisionistas
        $comisionistas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_comisionitas as c')
            ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('c.IdCliente', $clienteId)
            ->select(
                'c.IdComisionista as id',
                'i.Nombre as nombre'
            )
            ->orderBy('i.Nombre', 'asc')
            ->get();
        
        // =============================================
        // ESTADÍSTICAS
        // =============================================
        $totalVentasGeneral = Venta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->where('IdEstado', 1)
            ->count();
        
        $totalImporteGeneral = Venta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->where('IdEstado', 1)
            ->sum('ImporteVenta');
        
        $estadisticasPorSucursal = [];
        $sucursalSeleccionada = $request->sucursal_id;
        
        if ($sucursalSeleccionada) {
            $sucursal = $sucursales->firstWhere('id', $sucursalSeleccionada);
            $totalVentas = Venta::where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->where('IdEstado', 1)
                ->where('IdClienteSucursal', $sucursalSeleccionada)
                ->count();
            
            $totalImporte = Venta::where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->where('IdEstado', 1)
                ->where('IdClienteSucursal', $sucursalSeleccionada)
                ->sum('ImporteVenta');
            
            $estadisticasPorSucursal[] = [
                'sucursal_id' => $sucursalSeleccionada,
                'sucursal_nombre' => $sucursal ? $sucursal->nombre : 'Sucursal seleccionada',
                'total_ventas' => $totalVentas,
                'total_importe' => $totalImporte,
            ];
        } else {
            foreach ($sucursales as $sucursal) {
                $totalVentas = Venta::where('IdCliente', $clienteId)
                    ->where('ActivoInactivo', 1)
                    ->where('IdEstado', 1)
                    ->where('IdClienteSucursal', $sucursal->id)
                    ->count();
                
                $totalImporte = Venta::where('IdCliente', $clienteId)
                    ->where('ActivoInactivo', 1)
                    ->where('IdEstado', 1)
                    ->where('IdClienteSucursal', $sucursal->id)
                    ->sum('ImporteVenta');
                
                $estadisticasPorSucursal[] = [
                    'sucursal_id' => $sucursal->id,
                    'sucursal_nombre' => $sucursal->nombre,
                    'total_ventas' => $totalVentas,
                    'total_importe' => $totalImporte,
                ];
            }
        }
        
        return Inertia::render('Gestion/Reportes/InformeVentas/Index', [
            'ventas' => $ventas,
            'sucursales' => $sucursales,
            'vendedores' => $vendedores,
            'comisionistas' => $comisionistas,
            'estadisticas' => [
                'total_ventas' => $totalVentasGeneral,
                'total_importe' => $totalImporteGeneral,
                'por_sucursal' => $estadisticasPorSucursal,
                'sucursal_seleccionada' => $sucursalSeleccionada,
            ],
            'filtros' => [
                'sucursal_id' => $request->sucursal_id,
                'vendedor_id' => $request->vendedor_id,
                'comisionista_id' => $request->comisionista_id,
                'search' => $request->search,
            ],
        ]);
    }
    
    /**
     * 🔥 REIMPRIMIR FACTURA - Con altura dinámica
     */
    public function reimprimir($id)
    {
        try {
            $clienteId = session('cliente_id');
            
            // 🔥 BUSCAR LA VENTA
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->where('IdCliente', $clienteId)
                ->first();
            
            if (!$venta) {
                abort(404, 'Venta no encontrada');
            }
            
            // =============================================
            // OBTENER DATOS PARA EL PDF
            // =============================================
            
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first();
            
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $venta->IdClienteSucursal)
                ->first();
            
            $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $venta->IdNIT)
                ->first();
            
            $nombreCliente = $cliente ? $cliente->Nombre : 'CONSUMIDOR FINAL';
            $nitCliente = $cliente ? $cliente->CI_NIT : '0';
            
            $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle as d')
                ->join('inventario_relacion_ventainventario as p', 'd.idrelacionventainventario', '=', 'p.IdDetalleProducto')
                ->where('d.idventas', $id)
                ->select('p.Detalle as nombre', 'd.unidades', 'd.preciounidades', 'd.totalbolivianos')
                ->get();
            
            $pagos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion as l')
                ->join('impuestos_ventas_liquidacion_concepto as c', 'l.IdCuenta', '=', 'c.IdCuenta')
                ->leftJoin('todos_identificador as i', 'l.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('l.IdVentas', $id)
                ->select('c.Concepto', 'l.Bolivianos', 'i.CI_NIT', 'i.Nombre as identificador_nombre')
                ->get();
            
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $venta->IdOperadorIngresa)
                ->first();
            
            // =============================================
            // CALCULAR ALTURA DINÁMICA
            // =============================================
            
            $pdfCalc = new \TCPDF('P', 'mm', array(72, 300));
            $pdfCalc->setPrintHeader(false);
            $pdfCalc->setPrintFooter(false);
            $pdfCalc->SetMargins(4, 4, 4);
            $pdfCalc->SetAutoPageBreak(false);
            $pdfCalc->AddPage();
            $pdfCalc->SetFont('helvetica', '', 8);
            
            $x = 4;
            $y = 4;
            $width = 64;
            $alturaTotal = 0;
            
            // CABECERA
            $alturaTotal += 5;  // Nombre empresa
            $alturaTotal += 4;  // Sucursal
            $alturaTotal += 4;  // Dirección
            $alturaTotal += 4;  // Teléfono
            $alturaTotal += 4;  // Santa Cruz
            $alturaTotal += 6;  // Espacio
            $alturaTotal += 5;  // FACTURA
            $alturaTotal += 5;  // N° Factura
            $alturaTotal += 6;  // Espacio
            $alturaTotal += 4;  // Fecha
            $alturaTotal += 4;  // NIT
            $alturaTotal += 4;  // Cliente
            $alturaTotal += 6;  // Espacio
            $alturaTotal += 4;  // Encabezado tabla (CANT, PRODUCTO, etc.)
            $alturaTotal += 4;  // Línea separadora
            
            // PRODUCTOS
            $pdfCalc->SetFont('helvetica', '', 7);
            foreach ($detalles as $detalle) {
                $nombreProducto = $detalle->nombre ?? 'Producto';
                $alturaNombre = $pdfCalc->getStringHeight(35, $nombreProducto);
                $alturaTotal += max(4, $alturaNombre);
            }
            
            $alturaTotal += 4;  // Línea separadora
            $alturaTotal += 6;  // TOTAL
            
            // PAGOS
            foreach ($pagos as $pago) {
                $alturaTotal += 4;
                if ($pago->CI_NIT && $pago->CI_NIT != 0) {
                    $alturaTotal += 3;
                }
            }
            
            $alturaTotal += 8;  // TOTAL PAGO
            $alturaTotal += 6;  // VENDEDOR
            $alturaTotal += 6;  // Línea final
            $alturaTotal += 10; // Margen inferior
            
            $alturaMinima = 80;
            $alturaFinal = max($alturaMinima, min(500, $alturaTotal));
            
            // =============================================
            // GENERAR PDF CON LA ALTURA CALCULADA
            // =============================================
            
            $pdf = new \TCPDF('P', 'mm', array(72, $alturaFinal));
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(4, 4, 4);
            $pdf->SetAutoPageBreak(false);
            $pdf->AddPage();
            
            $pdf->SetFont('helvetica', '', 8);
            $x = 4;
            $y = 4;
            $width = 64;
            
            // CABECERA
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 5, $empresa->Nombre ?? '', 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "SUCURSAL " . ($sucursal->NumeroSucursal ?? ''), 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, $sucursal->Direccion ?? '', 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "Tel.: " . ($sucursal->Telefono ?? '') . " - Cel.: " . ($sucursal->Celular ?? ''), 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "SANTA CRUZ - BOLIVIA", 0, 1, 'C');
            $y += 6;
            
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 5, "FACTURA", 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "N° " . ($venta->NumeroFactura ?? '0'), 0, 1, 'C');
            $y += 6;
            
            // DATOS DEL CLIENTE
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "FECHA: " . date('d/m/Y H:i:s', strtotime($venta->FechaVenta)), 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "NIT/CI: " . $nitCliente, 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "CLIENTE: " . $nombreCliente, 0, 1, 'L');
            $y += 6;
            
            // TABLA DE PRODUCTOS
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(8, 4, "CANT", 0, 0, 'L');
            $pdf->Cell(35, 4, "PRODUCTO", 0, 0, 'L');
            $pdf->Cell(10, 4, "P.U.", 0, 0, 'R');
            $pdf->Cell(11, 4, "TOTAL", 0, 1, 'R');
            
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($x, $y + 1);
            $pdf->Cell($width, 1, "", 'T', 1);
            $y += 4;
            
            $totalGeneral = 0;
            foreach ($detalles as $detalle) {
                $nombreProducto = $detalle->nombre ?? 'Producto';
                $cantidad = $detalle->unidades;
                $precio = $detalle->preciounidades;
                $subtotal = $detalle->totalbolivianos;
                $totalGeneral += $subtotal;
                
                $startY = $y;
                
                $pdf->SetXY($x, $startY);
                $pdf->Cell(8, 4, number_format($cantidad, 0), 0, 0, 'L');
                
                $pdf->SetXY($x + 43, $startY);
                $pdf->Cell(10, 4, number_format($precio, 2, '.', ','), 0, 0, 'R');
                
                $pdf->SetXY($x + 53, $startY);
                $pdf->Cell(11, 4, number_format($subtotal, 2, '.', ','), 0, 1, 'R');
                
                $pdf->SetXY($x + 8, $startY);
                $pdf->MultiCell(35, 4, $nombreProducto, 0, 'L');
                
                $y = $pdf->GetY();
            }
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 1, "", 'T', 1);
            $y += 3;
            
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(53, 5, "TOTAL:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalGeneral, 2, '.', ','), 0, 1, 'R');
            $y += 6;
            
            // PAGOS
            foreach ($pagos as $pago) {
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetXY($x, $y);
                $pdf->Cell(53, 4, $pago->Concepto, 0, 0, 'R');
                $pdf->Cell(11, 4, number_format($pago->Bolivianos, 2, '.', ','), 0, 1, 'R');
                $y += 4;
                
                if ($pago->CI_NIT && $pago->CI_NIT != 0) {
                    $pdf->SetFont('helvetica', '', 6);
                    $pdf->SetXY($x + 5, $y);
                    $pdf->Cell(59, 3, "NIT/CI: " . $pago->CI_NIT . " - " . ($pago->identificador_nombre ?? 'SIN NOMBRE'), 0, 1, 'L');
                    $y += 3;
                }
            }
            
            // TOTAL PAGO
            $totalPagos = $pagos->sum('Bolivianos');
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(53, 5, "TOTAL PAGO:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalPagos, 2, '.', ','), 0, 1, 'R');
            $y += 8;
            
            // VENDEDOR
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "VENDEDOR: " . ($operador->Nombre ?? ''), 0, 1, 'L');
            $y += 6;
            
            // LÍNEA FINAL
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 1, "________________________________________", 0, 1, 'C');
            
            $pdf->Output("factura_{$venta->NumeroFactura}.pdf", 'I');
            exit;
            
        } catch (\Exception $e) {
            \Log::error('Error reimprimiendo factura: ' . $e->getMessage());
            abort(500, 'Error: ' . $e->getMessage());
        }
    }
}