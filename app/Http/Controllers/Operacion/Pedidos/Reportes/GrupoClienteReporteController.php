<?php

namespace App\Http\Controllers\Operacion\Pedidos\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GrupoClienteReporteController extends Controller
{
    /**
     * ✅ EXPORTAR EXCEL: Una hoja por cada Grupo Cliente
     */
    public function exportarExcel(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            // ✅ Empresa
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first(['Nombre', 'NIT']);

            $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

            // ✅ Grupos activos
            $grupos = GrupoCliente::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->orderBy('Nombre')
                ->get();

            if ($grupos->isEmpty()) {
                return redirect()->back()->with('error', 'No hay grupos de clientes para exportar.');
            }

            // ============================================================
            // CREAR SPREADSHEET
            // ============================================================
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            $nombresUsados = [];

            foreach ($grupos as $grupo) {
                $nombreHoja = $this->generarNombreHoja($grupo->Nombre, $nombresUsados);
                $nombresUsados[] = $nombreHoja;

                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($nombreHoja);

                // ✅ Cargar datos
                $clientes = $this->obtenerClientesDelGrupo($grupo->IdGrupoCliente);
                $gruposAnalisis = $this->obtenerGruposAnalisisConProductos($grupo->IdGrupoCliente, $clienteId, $sucursalId);

                $fila = 1;

                // ============================================
                // HEADER EMPRESA
                // ============================================
                $sheet->setCellValue('A' . $fila, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'));
                $sheet->mergeCells('A' . $fila . ':F' . $fila);
                $sheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(14)
                    ->getColor()->setRGB('1E3C78');
                $sheet->getStyle('A' . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $fila++;

                if (!empty($empresa->NIT)) {
                    $sheet->setCellValue('A' . $fila, 'NIT: ' . $empresa->NIT);
                    $sheet->mergeCells('A' . $fila . ':F' . $fila);
                    $sheet->getStyle('A' . $fila)->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $fila++;
                }

                $sheet->setCellValue('A' . $fila, 'GRUPO: ' . $grupo->Nombre);
                $sheet->mergeCells('A' . $fila . ':F' . $fila);
                $sheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12)
                    ->getColor()->setRGB('1E3C78');
                $sheet->getStyle('A' . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $fila++;

                $sheet->setCellValue('A' . $fila, 'Fecha: ' . $fechaImpresion);
                $sheet->mergeCells('A' . $fila . ':F' . $fila);
                $sheet->getStyle('A' . $fila)->getFont()->setItalic(true)->setSize(9)
                    ->getColor()->setRGB('666666');
                $sheet->getStyle('A' . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $fila += 2;

                // ============================================
                // BLOQUE 1: CLIENTES
                // ============================================
                $sheet->setCellValue('A' . $fila, 'CLIENTES (' . count($clientes) . ')');
                $sheet->mergeCells('A' . $fila . ':C' . $fila);
                $sheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(11)
                    ->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A' . $fila)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('8B1A1A');
                $sheet->getStyle('A' . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $fila++;

                // Encabezados clientes
                $headersClientes = ['N°', 'CLIENTE', 'CÓDIGO'];
                $col = 'A';
                foreach ($headersClientes as $h) {
                    $sheet->setCellValue($col . $fila, $h);
                    $sheet->getStyle($col . $fila)->getFont()->setBold(true)
                        ->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle($col . $fila)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('8B1A1A');
                    $sheet->getStyle($col . $fila)->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($col . $fila)->getBorders()->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    $col++;
                }
                $fila++;

                // Filas clientes
                if (empty($clientes)) {
                    $sheet->setCellValue('A' . $fila, 'Sin clientes asignados');
                    $sheet->mergeCells('A' . $fila . ':C' . $fila);
                    $sheet->getStyle('A' . $fila)->getFont()->setItalic(true)
                        ->getColor()->setRGB('999999');
                    $fila++;
                } else {
                    foreach ($clientes as $i => $cli) {
                        $sheet->setCellValue('A' . $fila, $i + 1);
                        $sheet->setCellValue('B' . $fila, $cli->Nombre);
                        $sheet->setCellValue('C' . $fila, $cli->CI_NIT);

                        $sheet->getStyle('A' . $fila)
                            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('C' . $fila)
                            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                        $sheet->getStyle('A' . $fila . ':C' . $fila)->getBorders()->getAllBorders()
                            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                        $fila++;
                    }
                }

                $fila += 2;

                // ============================================
                // BLOQUE 2: PRODUCTOS AGRUPADOS POR GRUPO DE ANÁLISIS
                // ============================================
                $sheet->setCellValue('A' . $fila, 'PRODUCTOS');
                $sheet->mergeCells('A' . $fila . ':F' . $fila);
                $sheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(11)
                    ->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A' . $fila)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('8B1A1A');
                $sheet->getStyle('A' . $fila)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $fila++;

                // Encabezados productos
                $headersProductos = ['N°', 'NOMBRE DE PRODUCTO', 'SIN FACTURA', 'CON FACTURA', 'CONDICIONES PRODUCCIÓN', 'CONDICIÓN COMERCIAL'];
                $col = 'A';
                foreach ($headersProductos as $h) {
                    $sheet->setCellValue($col . $fila, $h);
                    $sheet->getStyle($col . $fila)->getFont()->setBold(true)->setSize(9)
                        ->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle($col . $fila)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('8B1A1A');
                    $sheet->getStyle($col . $fila)->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($col . $fila)->getBorders()->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    $col++;
                }
                $fila++;

                // Contador global de productos
                $contadorGlobal = 0;

                // ✅ Recorrer cada Grupo de Análisis
                foreach ($gruposAnalisis as $grupoAnalisis) {
                    $filaInicioGrupo = $fila;

                    foreach ($grupoAnalisis->Productos as $prod) {
                        $contadorGlobal++;

                        $sheet->setCellValue('A' . $fila, $contadorGlobal);
                        $sheet->setCellValue('B' . $fila, $prod->Descripcion);
                        $sheet->setCellValue('C' . $fila, (float) $prod->PrecioSinFactura);
                        $sheet->setCellValue('D' . $fila, (float) $prod->PrecioConFactura);

                        // ✅ CONDICIONES PRODUCCIÓN: viene de producto_minimo
                        $condProduccion = $prod->CantidadMinimaProducto > 0
                            ? 'Pedido mínimo ' . (int) $prod->CantidadMinimaProducto . ' unidades'
                            : '';
                        $sheet->setCellValue('E' . $fila, $condProduccion);

                        // CONDICIÓN COMERCIAL: se agrega después (celda combinada)
                        $sheet->setCellValue('F' . $fila, '');

                        // Formato moneda
                        $sheet->getStyle('C' . $fila . ':D' . $fila)
                            ->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');

                        // Centrar
                        $sheet->getStyle('A' . $fila)
                            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('C' . $fila . ':D' . $fila)
                            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('E' . $fila)
                            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                        // Bordes
                        $sheet->getStyle('A' . $fila . ':F' . $fila)->getBorders()->getAllBorders()
                            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                        $fila++;
                    }

                    $filaFinGrupo = $fila - 1;

                    // ✅ CONDICIÓN COMERCIAL: celda combinada con el mínimo del grupo
                    if ($grupoAnalisis->CantidadMinimaGrupo > 0) {
                        $textoComercial = 'Pedido total de '
                            . (int) $grupoAnalisis->CantidadMinimaGrupo
                            . ' '
                            . strtolower($grupoAnalisis->NombreGrupo)
                            . ' en adelante';

                        $sheet->mergeCells('F' . $filaInicioGrupo . ':F' . $filaFinGrupo);
                        $sheet->setCellValue('F' . $filaInicioGrupo, $textoComercial);

                        $sheet->getStyle('F' . $filaInicioGrupo)->getAlignment()
                            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                            ->setWrapText(true);

                        $sheet->getStyle('F' . $filaInicioGrupo . ':F' . $filaFinGrupo)
                            ->getBorders()->getAllBorders()
                            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    }
                }

                // ============================================
                // AUTO AJUSTAR COLUMNAS
                // ============================================
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(28);
                $sheet->getColumnDimension('F')->setWidth(28);
            }

            // ============================================================
            // DESCARGAR
            // ============================================================
            $nombreArchivo = 'Grupos_Clientes_' . Carbon::now('America/La_Paz')->format('Y-m-d_His') . '.xlsx';

            if (ob_get_length()) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();

        } catch (\Exception $e) {
            Log::error('Error al exportar Excel: ' . $e->getMessage());
            Log::error('Stack: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Error al exportar Excel: ' . $e->getMessage());
        }
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function obtenerClientesDelGrupo($idGrupoCliente)
    {
        return DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_grupo_cliente_detalle as d')
            ->join('todos_identificador as i', 'd.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('d.IdGrupoCliente', $idGrupoCliente)
            ->where('d.ActivoInactivo', 1)
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->orderBy('i.Nombre')
            ->get();
    }

    /**
     * ✅ Obtener productos AGRUPADOS por Grupo de Análisis
     *
     * ✅ AHORA:
     *   - Mínimo del grupo viene de `grupo_cliente_minimo`
     *   - Mínimo del producto viene de `producto_minimo` (CantidadMinimaProducto)
     *   - Solo productos con DisponibleParaPedido = 1
     */
    private function obtenerGruposAnalisisConProductos($idGrupoCliente, $clienteId, $sucursalId)
    {
        // 1. Obtener mínimos del grupo cliente
        $minimos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_grupo_cliente_minimo as gm')
            ->join('inventario_productogrupoanalisis as ga', 'gm.IdGrupoAnalisis', '=', 'ga.IdGrupoAnalisis')
            ->where('gm.IdGrupoCliente', $idGrupoCliente)
            ->where('gm.ActivoInactivo', 1)
            ->where('gm.CantidadMinimaGrupo', '>', 0)
            ->select(
                'gm.IdGrupoAnalisis',
                'ga.Grupo as NombreGrupo',
                'gm.CantidadMinimaGrupo'
            )
            ->orderBy('ga.Grupo')
            ->get();

        $resultado = collect();

        foreach ($minimos as $minimo) {
            // ✅ Productos con precio + mínimo individual + disponible
            $productos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_producto_precio as gp')
                ->join('inventario_productodetalle as p', 'gp.IdProducto', '=', 'p.IdProducto')
                ->leftJoin('operacion_pedidos_clientes_producto_minimo as pm', function ($join) use ($clienteId, $sucursalId) {
                    $join->on('pm.IdProducto', '=', 'p.IdProducto')
                         ->where('pm.IdCliente', '=', $clienteId)
                         ->where('pm.IdSucursal', '=', $sucursalId)
                         ->where('pm.ActivoInactivo', '=', 1);
                })
                ->where('gp.IdGrupoCliente', $idGrupoCliente)
                ->where('p.IdGrupoAnalisis', $minimo->IdGrupoAnalisis)
                ->where('gp.ActivoInactivo', 1)
                ->where('p.ActivoInactivo', 0)
                ->where('pm.DisponibleParaPedido', 1) // ✅ Solo disponibles
                ->where(function ($q) {
                    $q->where('gp.PrecioSinFactura', '>', 0)
                      ->orWhere('gp.PrecioConFactura', '>', 0);
                })
                ->select(
                    'gp.IdProducto',
                    'p.Codigo',
                    'p.Descripcion',
                    'gp.PrecioSinFactura',
                    'gp.PrecioConFactura',
                    'pm.CantidadMinimaProducto' // ✅ Mínimo viene de producto_minimo
                )
                ->orderBy('p.Descripcion')
                ->get();

            if ($productos->isNotEmpty()) {
                $resultado->push((object) [
                    'IdGrupoAnalisis' => $minimo->IdGrupoAnalisis,
                    'NombreGrupo' => $minimo->NombreGrupo,
                    'CantidadMinimaGrupo' => $minimo->CantidadMinimaGrupo,
                    'Productos' => $productos,
                ]);
            }
        }

        return $resultado;
    }

    private function generarNombreHoja($nombre, $nombresUsados = [])
    {
        $nombre = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $nombre);
        $nombre = trim($nombre);

        if (mb_strlen($nombre, 'UTF-8') > 31) {
            $nombre = mb_substr($nombre, 0, 28, 'UTF-8') . '...';
        }

        if (empty($nombre)) {
            $nombre = 'Grupo';
        }

        $nombreBase = $nombre;
        $contador = 1;
        while (in_array($nombre, $nombresUsados)) {
            $sufijo = '_' . $contador;
            $nombre = mb_substr($nombreBase, 0, 31 - mb_strlen($sufijo), 'UTF-8') . $sufijo;
            $contador++;
        }

        return $nombre;
    }
}