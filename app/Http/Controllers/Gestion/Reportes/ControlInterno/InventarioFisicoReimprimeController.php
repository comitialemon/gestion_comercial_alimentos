<?php

namespace App\Http\Controllers\Gestion\Reportes\ControlInterno;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use TCPDF;
use Inertia\Inertia;

class InventarioFisicoReimprimeController extends Controller
{
    /**
     * Muestra el formulario del reporte
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        
        // Obtener sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);
        
        return Inertia::render('Gestion/Reportes/ControlInterno/InventarioFisicoReimprime', [
            'sucursales' => $sucursales,
        ]);
    }
    
    /**
     * Obtener números correlativos por sucursal (AJAX)
     */
    public function getCorrelativos(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);
        
        $sucursalId = $request->sucursal_id;
        
        $correlativos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_fisicorealizado')
            ->where('IdSucursal', $sucursalId)
            ->where('NumeroCorrelativo', '>', 0)
            ->orderBy('NumeroCorrelativo', 'desc')
            ->get(['IdFisico', 'NumeroCorrelativo']);
        
        return response()->json([
            'success' => true,
            'correlativos' => $correlativos
        ]);
    }
    
    /**
     * Genera el PDF de reimpresión
     */
    public function generarPdf(Request $request)
    {
        $request->validate([
            'id_fisico' => 'required|integer|exists:inventario_fisicorealizado,IdFisico',
        ]);
        
        $idFisico = $request->id_fisico;
        $clienteId = session('cliente_id');
        
        // =============================================
        // OBTENER DATOS DE CABECERA
        // =============================================
        
        $ajustePrincipal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_fisicorealizado')
            ->where('IdFisico', $idFisico)
            ->where('IdCliente', $clienteId)
            ->first();
        
        if (!$ajustePrincipal) {
            throw new \Exception('Inventario Físico no encontrado');
        }
        
        $numeroCorrelativo = $ajustePrincipal->NumeroCorrelativo;
        $idFecha = $ajustePrincipal->IdFecha;
        $idSucursal = $ajustePrincipal->IdSucursal;
        $idRealizadoPor = $ajustePrincipal->IdRealizadoPor;
        $idEncargadoSucursal = $ajustePrincipal->IdEncargadoSucursal;
        $observacion = $ajustePrincipal->Observacion ?? '';
        
        // Nombre de la empresa
        $nombreEmpresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->value('Nombre');
        
        // Nombre de la sucursal
        $nombreSucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $idSucursal)
            ->value('Nombre');
        
        // Fecha formateada
        $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $idFecha)
            ->first();
        $fechaTexto = $fechaData ? date('d-m-Y', strtotime($fechaData->Fecha)) : '';
        
        // Realizado por
        $realizadoPor = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->where('IdIdentificador', $idRealizadoPor)
            ->first();
        $ciRealizado = $realizadoPor->CI_NIT ?? '';
        $nombreRealizado = $realizadoPor->Nombre ?? '';
        
        // Encargado de sucursal
        $encargadoSucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->where('IdIdentificador', $idEncargadoSucursal)
            ->first();
        $ciEncargado = $encargadoSucursal->CI_NIT ?? '';
        $nombreEncargado = $encargadoSucursal->Nombre ?? '';
        
        // =============================================
        // OBTENER DETALLE DEL INVENTARIO
        // =============================================
        
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_fisicorealizado_detalle as d')
            ->join('inventario_productodetalle as p', 'd.IdProducto', '=', 'p.IdProducto')
            ->where('d.IdFisico', $idFisico)
            ->orderBy('p.Descripcion')
            ->select(
                'd.IdFisicoPropiamente',
                'd.IdProducto',
                'd.UnidadesSaldo',
                'd.Unidades',
                'd.UnidadesAjuste',
                'p.Codigo',
                'p.Descripcion'
            )
            ->get();
        
        // Filtrar solo productos con saldo o físico diferente de cero
        $detallesFiltrados = $detalles->filter(function($item) {
            return $item->UnidadesSaldo != 0 || $item->Unidades != 0;
        });
        
        // =============================================
        // GENERAR PDF CON TCPDF
        // =============================================
        
        $pdf = new TCPDF('P', 'mm', 'LETTER');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 14);
        $pdf->AddPage();
        
        // Variables para salto de página
        $limiteY = 250;
        $paginaActual = 1;
        $primeraPagina = true;
        
        // =============================================
        // FUNCIONES INTERNAS
        // =============================================
        
        // Encabezado común de cada página
        $encabezadoPagina = function(&$pdf, $numeroCorrelativo, $fechaTexto, $paginaActual) use ($nombreEmpresa) {
            // Logo (ajusta la ruta según tu proyecto)
            $logoPath = public_path('images/logohamacas.png');
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 15, 10, 70, 20);
            }
            
            // Número correlativo
            $pdf->SetFont('courier', 'B', 16);
            $pdf->SetXY(180, 25);
            $pdf->Cell(18, 3, 'No ' . $numeroCorrelativo, 0, 1, 'L');
            
            // Título principal
            $pdf->SetFont('courier', 'B', 20);
            $pdf->SetXY(5, 27);
            $pdf->Cell(200, 5, 'Inventario Físico', 0, 1, 'C');
            
            // Subtítulo y página
            $pdf->SetFont('courier', 'B', 12);
            $pdf->SetXY(5, $pdf->GetY() + 1);
            $pdf->Cell(200, 3, '(Expresado en Unidades) - Pág ' . $paginaActual, 0, 1, 'C');
            
            // Fecha
            $pdf->SetFont('courier', 'B', 10);
            $pdf->SetXY(5, $pdf->GetY() + 1);
            $pdf->Cell(200, 3, $fechaTexto, 0, 1, 'C');
            
            return $pdf->GetY();
        };
        
        // Agregar nueva página con encabezado
        $nuevaPagina = function(&$pdf, &$paginaActual, $numeroCorrelativo, $fechaTexto) use ($encabezadoPagina) {
            $pdf->AddPage();
            $paginaActual++;
            return $encabezadoPagina($pdf, $numeroCorrelativo, $fechaTexto, $paginaActual) + 5;
        };
        
        // Dibujar cabecera de tabla
        $dibujarCabeceraTabla = function(&$pdf, $titulo = '') {
            if ($titulo) {
                $pdf->SetFont('courier', 'B', 16);
                $pdf->Cell(0, 8, $titulo, 0, 1, 'C');
            }
            $y0 = $pdf->GetY();
            $pdf->Line(20, $y0, 195, $y0);
            
            // Primera fila
            $pdf->SetFont('courier', 'B', 10);
            $pdf->SetXY(20, $y0 + 2);
            $pdf->Cell(15, 6, 'No', 0, 0, 'C');
            $pdf->Cell(40, 6, 'Código Producto', 0, 0, 'C');
            $pdf->Cell(60, 6, 'Descripción', 0, 0, 'C');
            $pdf->Cell(60, 6, 'Unidades', 0, 1, 'C');
            
            // Segunda fila (subcolumnas)
            $pdf->SetFont('courier', 'B', 9);
            $pdf->SetXY(135, $y0 + 8);
            $pdf->Cell(20, 6, 'Saldo', 0, 0, 'C');
            $pdf->Cell(20, 6, 'Físico', 0, 0, 'C');
            $pdf->Cell(20, 6, 'Ajuste', 0, 1, 'C');
            
            // Línea inferior
            $y2 = $pdf->GetY();
            $pdf->Line(20, $y2, 195, $y2);
            
            return $y2 + 4;
        };
        
        // =============================================
        // PRIMER ENCABEZADO
        // =============================================
        $y = $encabezadoPagina($pdf, $numeroCorrelativo, $fechaTexto, $paginaActual);
        
        // Información específica (solo primera página)
        if ($primeraPagina) {
            $pdf->SetFont('courier', 'B', 10);
            $lineas = [
                "Sucursal           : $nombreSucursal",
                "Realizado por      : $ciRealizado - $nombreRealizado",
                "Encargado Sucursal : $ciEncargado - $nombreEncargado",
                "Observación        : $observacion"
            ];
            foreach ($lineas as $linea) {
                $pdf->SetXY(15, $y + 5);
                $pdf->Cell(200, 3, $linea, 0, 1, 'L');
                $y = $pdf->GetY();
            }
            $primeraPagina = false;
        }
        
        // =============================================
        // DETALLE DEL INVENTARIO FÍSICO
        // =============================================
        $fila = $dibujarCabeceraTabla($pdf, 'Detalle de Inventario Físico');
        $cont = 0;
        
        foreach ($detallesFiltrados as $detalle) {
            // Verificar salto de página
            if ($pdf->GetY() > $limiteY) {
                $fila = $nuevaPagina($pdf, $paginaActual, $numeroCorrelativo, $fechaTexto);
                $fila = $dibujarCabeceraTabla($pdf);
            }
            
            $cont++;
            $codigo = $detalle->Codigo ?? '';
            $descripcion = $detalle->Descripcion ?? '';
            $valSaldo = (float) $detalle->UnidadesSaldo;
            $valFisico = (float) $detalle->Unidades;
            $valAjuste = (float) $detalle->UnidadesAjuste;
            
            $pdf->SetFont('courier', '', 9);
            
            // Nº
            $pdf->SetXY(20, $fila);
            $pdf->Cell(10, 6, $cont . '.', 0, 0, 'R');
            
            // Código
            $pdf->SetXY(35, $fila);
            $pdf->MultiCell(40, 5, $codigo, 0, 'L', false, 0, '', '', true, 0, false, true, 10);
            
            // Descripción
            $xDesc = 35 + 40 + 5;
            $pdf->SetXY($xDesc, $fila);
            $pdf->MultiCell(60, 5, $descripcion, 0, 'L', false, 0, '', '', true, 0, false, true, 10);
            
            // Unidades Saldo
            $pdf->SetXY(135, $fila);
            $pdf->Cell(20, 6, number_format($valSaldo, 3), 0, 0, 'R');
            
            // Unidades Físico
            $pdf->SetXY(155, $fila);
            $pdf->Cell(20, 6, number_format($valFisico, 3), 0, 0, 'R');
            
            // Unidades Ajuste
            $pdf->SetXY(175, $fila);
            $pdf->Cell(20, 6, number_format($valAjuste, 3), 0, 1, 'R');
            
            $fila = max($pdf->GetY(), $fila + 10);
        }
        
        // =============================================
        // SALIDA DEL PDF
        // =============================================
        $nombreArchivo = 'InventarioFisico_No_' . $numeroCorrelativo . '.pdf';
        
        $pdf->Output($nombreArchivo, 'I');
        exit();
    }
}