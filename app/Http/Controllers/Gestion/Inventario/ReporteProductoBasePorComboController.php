<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Services\Inventario\ProductoBaseComboCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ReporteProductoBasePorComboController extends Controller
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

        $sucursalNombreInicial = '';
        if ($sucursalId) {
            $sucursalEncontrada = $sucursales->firstWhere('id', $sucursalId);
            $sucursalNombreInicial = $sucursalEncontrada?->nombre ?? '';
        }

        return Inertia::render('Gestion/Inventario/ReporteProductoBasePorCombo/Index', [
            'columnas'              => $data['columnas'],
            'filas'                 => $data['filas'],
            'totalesPorColumna'     => (object) $data['totalesPorColumna'],
            'totalGeneral'          => $data['totalGeneral'],
            'totalOtrasSalidas'     => $data['totalOtrasSalidas'],
            'sucursales'            => $sucursales,
            'fechaInicial'          => $fechaInicial,
            'fechaFinal'            => $fechaFinal,
            'sucursalSeleccionada'  => $sucursalId,
            'sucursalNombreInicial' => $sucursalNombreInicial,
            'search'                => $search,
            'soloConMovimiento'     => $soloConMovimiento,
        ]);
    }

    public function exportarExcel(Request $request)
    {
        $clienteId = session('cliente_id');

        $sucursalId = $request->filled('sucursal_id')
            ? (int) $request->get('sucursal_id')
            : (int) session('cliente_sucursal_id');

        $fechaInicial      = $request->get('fecha_inicial') ?? date('Y-m-01');
        $fechaFinal        = $request->get('fecha_final')   ?? date('Y-m-d');
        $search            = $request->get('search');
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);

        $data = $this->calculator->calcular(
            (int) $clienteId,
            $sucursalId,
            $fechaInicial,
            $fechaFinal,
            $search,
            $soloConMovimiento
        );

        $columnas          = $data['columnas'];
        $filas             = $data['filas'];
        $totalesPorColumna = $data['totalesPorColumna'];
        $totalGeneral      = $data['totalGeneral'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos Base x Combo');

        $sheet->setCellValue('A1', $empresa->Nombre ?? '');
        $sheet->setCellValue('A2', $sucursal->Nombre ?? '');
        $sheet->setCellValue('A3', 'NIT: ' . ($empresa->NIT ?? ''));
        $sheet->setCellValue('A4', 'Salidas por Venta de Productos Base según Combo / Pack');
        $sheet->setCellValue('A5', 'Período: ' . $this->formatearFecha($fechaInicial) . ' al ' . $this->formatearFecha($fechaFinal));
        $sheet->setCellValue('A6', '(Unidades reales según inventario_propiamente)');

        $totalColumnas  = count($columnas) + 3;
        $ultimaColLetra = $this->numeroAColumna($totalColumnas - 1);

        foreach (['1','2','3','4','5','6'] as $filaMerge) {
            $sheet->mergeCells("A{$filaMerge}:{$ultimaColLetra}{$filaMerge}");
        }

        $sheet->getStyle('A1:A6')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle('A1:A6')->getFont()->getColor()->setRGB('000000');

        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        $sheet->getStyle('A2')->getFont()->setSize(12);
        $sheet->getStyle('A3')->getFont()->setSize(10);
        $sheet->getStyle('A4')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A5')->getFont()->setSize(11);
        $sheet->getStyle('A6')->getFont()->setSize(9)->setItalic(true);

        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $filaHeader  = 8;

        $sheet->setCellValue('A' . $filaHeader, 'Código');
        $sheet->setCellValue('B' . $filaHeader, 'Producto Base');

        foreach ($columnas as $idx => $col) {
            $colLetra = $this->numeroAColumna(2 + $idx);
            $sheet->setCellValue($colLetra . $filaHeader, $col['nombre']);
        }

        $colOtras = $this->numeroAColumna(2 + count($columnas));
        $colTotal = $this->numeroAColumna(3 + count($columnas));

        $sheet->setCellValue($colOtras . $filaHeader, 'Otras Salidas');
        $sheet->setCellValue($colTotal . $filaHeader, 'TOTAL REAL');

        $sheet->getStyle('A' . $filaHeader . ':' . $colTotal . $filaHeader)->applyFromArray($borderStyle);
        $sheet->getStyle('A' . $filaHeader . ':' . $colTotal . $filaHeader)->getFont()
            ->setBold(true)
            ->setColor(new Color('000000'));
        $sheet->getStyle('A' . $filaHeader . ':' . $colTotal . $filaHeader)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->getRowDimension($filaHeader)->setRowHeight(55);

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(35);
        foreach ($columnas as $idx => $col) {
            $colLetra = $this->numeroAColumna(2 + $idx);
            $sheet->getColumnDimension($colLetra)->setWidth(18);
        }
        $sheet->getColumnDimension($colOtras)->setWidth(14);
        $sheet->getColumnDimension($colTotal)->setWidth(16);

        $fila = $filaHeader + 1;

        foreach ($filas as $f) {
            $sheet->setCellValue('A' . $fila, $f['codigo']);
            $sheet->setCellValue('B' . $fila, $f['descripcion']);

            foreach ($columnas as $idx => $col) {
                $colLetra = $this->numeroAColumna(2 + $idx);
                $valor    = $f['valores'][(int) $col['id']] ?? 0;
                if ($valor > 0) {
                    $sheet->setCellValue($colLetra . $fila, (float) $valor);
                }
            }

            $sheet->setCellValue($colOtras . $fila, (float) ($f['otras_salidas'] ?? 0));
            $sheet->setCellValue($colTotal . $fila, (float) $f['total']);

            $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->applyFromArray($borderStyle);
            $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->getFont()
                ->getColor()->setRGB('000000');
            $sheet->getStyle($colTotal . $fila)->getFont()->setBold(true);
            $sheet->getStyle($colOtras . $fila)->getFont()->setBold(true);

            $sheet->getStyle('B' . $fila)->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->getRowDimension($fila)->setRowHeight(-1);

            $fila++;
        }

        $sheet->setCellValue('A' . $fila, 'TOTAL POR COLUMNA');
        $sheet->mergeCells('A' . $fila . ':B' . $fila);
        $sheet->getStyle('A' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($columnas as $idx => $col) {
            $colLetra = $this->numeroAColumna(2 + $idx);
            $sheet->setCellValue($colLetra . $fila, (float) ($totalesPorColumna[(int) $col['id']] ?? 0));
        }

        $sheet->setCellValue($colOtras . $fila, (float) $data['totalOtrasSalidas']);
        $sheet->setCellValue($colTotal . $fila, (float) $totalGeneral);

        $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->applyFromArray($borderStyle);
        $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->getFont()
            ->setBold(true)
            ->setColor(new Color('000000'));
        $sheet->getRowDimension($fila)->setRowHeight(25);

        if ($fila > $filaHeader + 1) {
            $rangoNum = 'C' . ($filaHeader + 1) . ':' . $colTotal . $fila;
            $sheet->getStyle($rangoNum)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $sheet->freezePane('C9');

        $nombreArchivo = 'ProductosBasePorCombo_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    private function numeroAColumna($numero)
    {
        $letra = '';
        while ($numero >= 0) {
            $letra  = chr(($numero % 26) + 65) . $letra;
            $numero = intdiv($numero, 26) - 1;
        }
        return $letra;
    }

    private function formatearFecha($fecha)
    {
        if (!$fecha) return '-';
        $partes = explode('-', $fecha);
        if (count($partes) === 3) {
            return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
        }
        return $fecha;
    }
}