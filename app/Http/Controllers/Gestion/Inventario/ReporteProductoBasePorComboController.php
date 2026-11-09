<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ReporteProductoBasePorComboController extends Controller
{
    /**
     * 📊 Matriz: productos base × combos/packs
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        $sucursalId        = $request->sucursal_id;
        $fechaInicial      = $request->fecha_inicial ?? date('Y-m-01');
        $fechaFinal        = $request->fecha_final   ?? date('Y-m-d');
        $search            = $request->search;
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);

        $fechaInicialConHora = $fechaInicial . ' 00:00:00';
        $fechaFinalConHora   = $fechaFinal   . ' 23:59:59';

        $data = $this->calcularMatriz(
            $clienteId,
            $sucursalId,
            $fechaInicialConHora,
            $fechaFinalConHora,
            $search,
            $soloConMovimiento
        );

        return Inertia::render('Gestion/Inventario/ReporteProductoBasePorCombo/Index', [
            'columnas'             => $data['columnas'],
            'filas'                => $data['filas'],
            'totalesPorColumna'    => (object) $data['totalesPorColumna'],
            'totalGeneral'         => $data['totalGeneral'],
            'sucursales'           => $sucursales,
            'fechaInicial'         => $fechaInicial,
            'fechaFinal'           => $fechaFinal,
            'sucursalSeleccionada' => $sucursalId,
            'search'               => $search,
            'soloConMovimiento'    => $soloConMovimiento,
        ]);
    }

    /**
     * 📥 Exportar matriz a Excel (sin colores)
     */
    public function exportarExcel(Request $request)
    {
        $clienteId = session('cliente_id');

        $sucursalId        = $request->get('sucursal_id');
        $fechaInicial      = $request->get('fecha_inicial') ?? date('Y-m-01');
        $fechaFinal        = $request->get('fecha_final')   ?? date('Y-m-d');
        $search            = $request->get('search');
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);

        $fechaInicialConHora = $fechaInicial . ' 00:00:00';
        $fechaFinalConHora   = $fechaFinal   . ' 23:59:59';

        // Info empresa y sucursal
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);

        // Calcular datos
        $data = $this->calcularMatriz(
            $clienteId,
            $sucursalId,
            $fechaInicialConHora,
            $fechaFinalConHora,
            $search,
            $soloConMovimiento
        );

        $columnas          = $data['columnas'];
        $filas             = $data['filas'];
        $totalesPorColumna = $data['totalesPorColumna'];
        $totalGeneral      = $data['totalGeneral'];

        // =============================================
        // CREAR EXCEL (SIN COLORES)
        // =============================================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos Base x Combo');

        // Cabecera
        $sheet->setCellValue('A1', $empresa->Nombre ?? '');
        $sheet->setCellValue('A2', $sucursal->Nombre ?? '');
        $sheet->setCellValue('A3', 'NIT: ' . ($empresa->NIT ?? ''));
        $sheet->setCellValue('A4', 'Productos Base vendidos por Combo / Pack');
        $sheet->setCellValue('A5', 'Período: ' . $this->formatearFecha($fechaInicial) . ' al ' . $this->formatearFecha($fechaFinal));
        $sheet->setCellValue('A6', '(Cantidades reales - incluye sustituciones)');

        $totalColumnas  = count($columnas) + 2;
        $ultimaColLetra = $this->numeroAColumna($totalColumnas - 1);

        $sheet->mergeCells('A1:' . $ultimaColLetra . '1');
        $sheet->mergeCells('A2:' . $ultimaColLetra . '2');
        $sheet->mergeCells('A3:' . $ultimaColLetra . '3');
        $sheet->mergeCells('A4:' . $ultimaColLetra . '4');
        $sheet->mergeCells('A5:' . $ultimaColLetra . '5');
        $sheet->mergeCells('A6:' . $ultimaColLetra . '6');

        $sheet->getStyle('A1:A6')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Todo en negro
        $sheet->getStyle('A1:A6')->getFont()->getColor()->setRGB('000000');

        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        $sheet->getStyle('A2')->getFont()->setSize(12);
        $sheet->getStyle('A3')->getFont()->setSize(10);
        $sheet->getStyle('A4')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A5')->getFont()->setSize(11);
        $sheet->getStyle('A6')->getFont()->setSize(9)->setItalic(true);

        // =============================================
        // HEADER DE TABLA (fila 8) - SIN COLOR
        // =============================================
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

        $filaHeader = 8;

        $sheet->setCellValue('A' . $filaHeader, 'Código');
        $sheet->setCellValue('B' . $filaHeader, 'Producto Base');

        foreach ($columnas as $idx => $col) {
            $colLetra = $this->numeroAColumna(2 + $idx);
            $sheet->setCellValue($colLetra . $filaHeader, $col['nombre']);
        }

        $colTotal = $this->numeroAColumna($totalColumnas - 1);
        $sheet->setCellValue($colTotal . $filaHeader, 'TOTAL');

        $sheet->getStyle('A' . $filaHeader . ':' . $colTotal . $filaHeader)->applyFromArray($borderStyle);
        $sheet->getStyle('A' . $filaHeader . ':' . $colTotal . $filaHeader)->getFont()
            ->setBold(true)
            ->setColor(new Color('000000'));
        $sheet->getStyle('A' . $filaHeader . ':' . $colTotal . $filaHeader)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // Altura del header
        $sheet->getRowDimension($filaHeader)->setRowHeight(55);

        // Anchos
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(35);
        foreach ($columnas as $idx => $col) {
            $colLetra = $this->numeroAColumna(2 + $idx);
            $sheet->getColumnDimension($colLetra)->setWidth(18);
        }
        $sheet->getColumnDimension($colTotal)->setWidth(14);

        // =============================================
        // FILAS DE DATOS
        // =============================================
        $fila = $filaHeader + 1;

        foreach ($filas as $f) {
            $sheet->setCellValue('A' . $fila, $f['codigo']);
            $sheet->setCellValue('B' . $fila, $f['descripcion']);

            foreach ($columnas as $idx => $col) {
                $colLetra = $this->numeroAColumna(2 + $idx);
                $valor    = $f['valores'][$col['id']] ?? 0;
                if ($valor > 0) {
                    $sheet->setCellValue($colLetra . $fila, (float) $valor);
                }
            }

            $sheet->setCellValue($colTotal . $fila, (float) $f['total']);

            $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->applyFromArray($borderStyle);
            $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->getFont()
                ->getColor()->setRGB('000000');

            // Total en negrita
            $sheet->getStyle($colTotal . $fila)->getFont()->setBold(true);

            // Wrap text en descripción
            $sheet->getStyle('B' . $fila)->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER);

            // Auto-ajustar altura de fila
            $sheet->getRowDimension($fila)->setRowHeight(-1);

            $fila++;
        }

        // =============================================
        // FOOTER: TOTAL POR COLUMNA - SIN COLOR
        // =============================================
        $sheet->setCellValue('A' . $fila, 'TOTAL POR COLUMNA');
        $sheet->mergeCells('A' . $fila . ':B' . $fila);
        $sheet->getStyle('A' . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($columnas as $idx => $col) {
            $colLetra = $this->numeroAColumna(2 + $idx);
            $sheet->setCellValue($colLetra . $fila, (float) ($totalesPorColumna[$col['id']] ?? 0));
        }

        $sheet->setCellValue($colTotal . $fila, (float) $totalGeneral);

        $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->applyFromArray($borderStyle);
        $sheet->getStyle('A' . $fila . ':' . $colTotal . $fila)->getFont()
            ->setBold(true)
            ->setColor(new Color('000000'));
        $sheet->getRowDimension($fila)->setRowHeight(25);

        // Formato de números
        if ($fila > $filaHeader + 1) {
            $rangoNum = 'C' . ($filaHeader + 1) . ':' . $colTotal . $fila;
            $sheet->getStyle($rangoNum)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Congelar paneles (fila 9, columna C)
        $sheet->freezePane('C9');

        // =============================================
        // DESCARGAR COMO XLSX
        // =============================================
        $nombreArchivo = 'ProductosBasePorCombo_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    /**
     * 🔧 Lógica compartida: calcular la matriz
     */
    private function calcularMatriz(
        $clienteId,
        $sucursalId,
        $fechaInicialConHora,
        $fechaFinalConHora,
        $search,
        $soloConMovimiento
    ) {
        $columnas          = [];
        $filas             = [];
        $totalesPorColumna = [];
        $totalGeneral      = 0;

        if (!$sucursalId || $sucursalId <= 0) {
            return compact('columnas', 'filas', 'totalesPorColumna', 'totalGeneral');
        }

        // 1. Categorías PACK y COMBOS + subcategorías (recursivo)
        $categoriasRaiz = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_menu_categoria')
            ->where('id_cliente', $clienteId)
            ->whereIn(DB::raw('UPPER(nombre)'), ['PACK', 'COMBOS'])
            ->pluck('id_categoria')
            ->toArray();

        $todasLasCategorias = [];
        $porProcesar        = $categoriasRaiz;
        $visitadas          = [];

        while (!empty($porProcesar)) {
            $idActual = array_shift($porProcesar);
            if (isset($visitadas[$idActual])) continue;
            $visitadas[$idActual]          = true;
            $todasLasCategorias[$idActual] = true;

            $hijas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_menu_categoria')
                ->where('id_cliente', $clienteId)
                ->where('id_padre', $idActual)
                ->pluck('id_categoria')
                ->toArray();

            foreach ($hijas as $h) {
                if (!isset($visitadas[$h])) $porProcesar[] = $h;
            }
        }
        $idsCategoriasComboPack = array_keys($todasLasCategorias);

        // 2. Productos de venta en esas categorías
        $idsProductosVentaEnComboPack = [];
        if (!empty($idsCategoriasComboPack)) {
            $idsProductosVentaEnComboPack = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_producto_categoria')
                ->whereIn('id_categoria', $idsCategoriasComboPack)
                ->where('id_sucursal', $sucursalId)
                ->distinct()
                ->pluck('id_detalle_producto')
                ->toArray();
        }

        // 3. Composición
        $composicionPorProductoVenta = [];
        $idsProductosBaseValidos     = [];

        if (!empty($idsProductosVentaEnComboPack)) {
            $composiciones = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->whereIn('IdDetalleProducto', $idsProductosVentaEnComboPack)
                ->select('IdDetalleProducto', 'IdProducto', 'Porcion')
                ->get();

            foreach ($composiciones as $c) {
                $composicionPorProductoVenta[$c->IdDetalleProducto][] = [
                    'id_producto_base' => (int) $c->IdProducto,
                    'porcion'          => (float) $c->Porcion,
                ];
                $idsProductosBaseValidos[$c->IdProducto] = true;
            }
        }

        // 4. Info productos de venta
        $productosVentaInfo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdCliente', $clienteId)
            ->get(['IdDetalleProducto', 'Codigo', 'Detalle'])
            ->keyBy('IdDetalleProducto');

        // 5. Ventas del período
        $ventas = collect();
        if (!empty($idsProductosVentaEnComboPack)) {
            $ventas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas as v')
                ->join('impuestos_ventas_detalle as d', 'v.IdVentas', '=', 'd.idventas')
                ->where('v.IdCliente', $clienteId)
                ->where('v.IdClienteSucursal', $sucursalId)
                ->where('v.ActivoInactivo', 1)
                ->whereBetween('v.FechaVenta', [$fechaInicialConHora, $fechaFinalConHora])
                ->whereIn('d.idrelacionventainventario', $idsProductosVentaEnComboPack)
                ->select(
                    'd.idrelacionventainventario as id_producto_venta',
                    'd.unidades',
                    'd.personalizacion'
                )
                ->get();
        }

        // 6. Matriz
        $matriz = [];
        foreach ($ventas as $venta) {
            $idProductoVenta  = (int) $venta->id_producto_venta;
            $unidadesVendidas = (float) $venta->unidades;
            if ($unidadesVendidas <= 0) continue;

            $personalizacion = null;
            if ($venta->personalizacion
                && $venta->personalizacion !== 'null'
                && $venta->personalizacion !== '[]'
            ) {
                $personalizacion = json_decode($venta->personalizacion, true);
            }

            $composicion = $composicionPorProductoVenta[$idProductoVenta] ?? [];

            if ($personalizacion && is_array($personalizacion) && count($personalizacion) > 0) {
                foreach ($personalizacion as $comboData) {
                    $sustitutos = $comboData['sustitutos'] ?? [];
                    foreach ($sustitutos as $sust) {
                        $idProductoReal = (int) ($sust['id_producto_sustituto'] ?? 0);
                        $cantidad       = (float) ($sust['cantidad'] ?? 0);
                        if ($idProductoReal > 0 && $cantidad > 0) {
                            if (!isset($matriz[$idProductoReal])) $matriz[$idProductoReal] = [];
                            if (!isset($matriz[$idProductoReal][$idProductoVenta])) $matriz[$idProductoReal][$idProductoVenta] = 0;
                            $matriz[$idProductoReal][$idProductoVenta] += $cantidad;
                            $idsProductosBaseValidos[$idProductoReal] = true;
                        }
                    }
                }
            } else {
                foreach ($composicion as $comp) {
                    $idProductoReal = $comp['id_producto_base'];
                    $cantidad       = $comp['porcion'] * $unidadesVendidas;
                    if ($cantidad > 0) {
                        if (!isset($matriz[$idProductoReal])) $matriz[$idProductoReal] = [];
                        if (!isset($matriz[$idProductoReal][$idProductoVenta])) $matriz[$idProductoReal][$idProductoVenta] = 0;
                        $matriz[$idProductoReal][$idProductoVenta] += $cantidad;
                    }
                }
            }
        }

        // 7. Columnas
        $idsProductosVenta = [];
        foreach ($matriz as $valores) {
            foreach ($valores as $idVenta => $cantidad) {
                if ($cantidad > 0) $idsProductosVenta[$idVenta] = true;
            }
        }

        $columnasTemp = [];
        foreach (array_keys($idsProductosVenta) as $idVenta) {
            $info = $productosVentaInfo[$idVenta] ?? null;
            $columnasTemp[] = [
                'id'     => $idVenta,
                'nombre' => $info->Detalle ?? "Producto #{$idVenta}",
                'codigo' => $info->Codigo ?? '',
            ];
        }
        usort($columnasTemp, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
        $columnas = $columnasTemp;

        // 8. Filas
        $idsProductosBaseValidos = array_keys($idsProductosBaseValidos);

        $queryProductosBase = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle as p')
            ->where('p.IdCliente', $clienteId)
            ->where('p.ActivoInactivo', 0)
            ->whereIn('p.IdProducto', $idsProductosBaseValidos)
            ->select('p.IdProducto', 'p.Codigo', 'p.Descripcion');

        if ($search) {
            $queryProductosBase->where(function ($q) use ($search) {
                $q->where('p.Codigo', 'like', "%{$search}%")
                  ->orWhere('p.Descripcion', 'like', "%{$search}%");
            });
        }

        $productosBase = $queryProductosBase->orderBy('p.Descripcion')->get();

        $filasTemp = [];
        foreach ($productosBase as $productoBase) {
            $idProductoBase = $productoBase->IdProducto;
            $valores        = $matriz[$idProductoBase] ?? [];

            $total          = 0;
            $valoresLimpios = [];
            foreach ($valores as $idVenta => $cantidad) {
                if ($cantidad > 0) {
                    $valoresLimpios[$idVenta] = round($cantidad, 2);
                    $total += $cantidad;
                }
            }

            if ($soloConMovimiento && $total <= 0) continue;

            $filasTemp[] = [
                'id_producto' => $idProductoBase,
                'codigo'      => $productoBase->Codigo,
                'descripcion' => $productoBase->Descripcion,
                'valores'     => $valoresLimpios,
                'total'       => round($total, 2),
            ];
        }

        usort($filasTemp, function ($a, $b) {
            if ($b['total'] !== $a['total']) return $b['total'] <=> $a['total'];
            return strcasecmp($a['descripcion'], $b['descripcion']);
        });
        $filas = $filasTemp;

        // 9. Totales
        foreach ($columnas as $col) {
            $sumaCol = 0;
            foreach ($filas as $fila) {
                $sumaCol += $fila['valores'][$col['id']] ?? 0;
            }
            $totalesPorColumna[$col['id']] = round($sumaCol, 2);
            $totalGeneral += $sumaCol;
        }
        $totalGeneral = round($totalGeneral, 2);

        return compact('columnas', 'filas', 'totalesPorColumna', 'totalGeneral');
    }

    /**
     * Convierte número a letra de columna (0=A, 25=Z, 26=AA...)
     */
    private function numeroAColumna($numero)
    {
        $letra = '';
        while ($numero >= 0) {
            $letra  = chr(($numero % 26) + 65) . $letra;
            $numero = intdiv($numero, 26) - 1;
        }
        return $letra;
    }

    /**
     * Formatea Y-m-d a d/m/Y
     */
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