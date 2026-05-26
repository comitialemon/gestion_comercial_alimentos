<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Compra;
use App\Models\Gestion\Impuestos\CompraDetalle;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TCPDF;

class CompraController extends Controller
{
    public function index()
    {
        $compras = Compra::porContexto()
            ->with(['almacen', 'proveedor'])
            ->orderBy('IdCompras', 'desc')
            ->paginate(20);

        return Inertia::render('Gestion/Impuestos/Compras/Index', [
            'compras' => $compras,
        ]);
    }

    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Buscar compra borrador para este operador (pendiente)
        $borrador = Compra::porContexto()
            ->borradorPorOperador()
            ->first();

        // ✅ NO CREAR COMPRA AUTOMÁTICAMENTE
        // Si no existe, $borrador será null
        
        // Cargar detalles si existe compra pendiente
        $detalles = [];
        if ($borrador) {
            $detalles = CompraDetalle::where('IdCompras', $borrador->IdCompras)
                ->with('producto')
                ->get();
        }

        // Datos para selects (siempre se cargan)
        $almacenes = Almacen::porContexto()
            ->orderBy('Almacen')
            ->get(['IdAlmacen as id', 'Almacen as nombre']);

        $tiposFactura = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_compras_tipofactura')
            ->orderBy('IdTipoFactura')
            ->get();

        $proveedores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $fechas = $this->getFechasDisponibles();

        $productos = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Codigo')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);

        return Inertia::render('Gestion/Impuestos/Compras/Create', [
            'compra' => $borrador,  // Puede ser null
            'detalles' => $detalles,
            'almacenes' => $almacenes,
            'tiposFactura' => $tiposFactura,
            'proveedores' => $proveedores,
            'fechas' => $fechas,
            'productos' => $productos,
        ]);
    }
    /**
     * Crear una nueva compra (primera vez que se guarda cabecera)
     */
    public function crearCompra(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $compra = Compra::create([
            'NumeroCorrelativo' => 0,
            'IdDiario' => 0,
            'IdCuenta' => 0,
            'IdAlmacen' => 0,
            'IdTipoFactura' => 0,
            'NumeroFactura' => 0,
            'IdNIT' => 0,
            'NumeroDUI' => 0,
            'NumeroAutorizacion' => 0,
            'IdFecha' => 0,
            'IdCliente' => $clienteId,
            'IdSucursal' => $sucursalId,
            'ActivoInactivo' => 0,
            'FechaIngreso' => now(),
            'IdOperadorIngresa' => $operadorId,
            'IdOperadorActualiza' => $operadorId,
            'ImporteFactura' => 0,
            'Observacion' => '',
        ]);

        return response()->json([
            'success' => true,
            'compra' => $compra,
            'message' => 'Compra creada correctamente'
        ]);
    }

    public function actualizarCabecera(Request $request, $id)
    {
        try {
            $compra = Compra::findOrFail($id);

            if ($compra->ActivoInactivo != 0) {
                return response()->json(['success' => false, 'message' => 'La compra ya fue contabilizada'], 400);
            }

            $compra->update([
                'IdAlmacen' => $request->IdAlmacen,
                'IdTipoFactura' => $request->IdTipoFactura,
                'IdFecha' => $request->IdFecha,
                'NumeroFactura' => $request->NumeroFactura,
                'IdNIT' => $request->IdNIT,
                'NumeroAutorizacion' => $request->NumeroAutorizacion ?? 0,
                'ImporteFactura' => $request->ImporteFactura ?? 0,
                'Observacion' => $request->Observacion ?? '',
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cabecera actualizada correctamente',
                'data' => $compra->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Compra no encontrada. Recargue la página e intente nuevamente.'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error actualizando cabecera: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }
    public function agregarDetalle(Request $request)
    {
        $request->validate([
            'IdCompras' => 'required|exists:impuestos_compras,IdCompras',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Unidades' => 'required|numeric|min:0.0001',
            'TotalBolivianos' => 'required|numeric|min:0',
        ]);

        $compra = Compra::findOrFail($request->IdCompras);

        if ($compra->ActivoInactivo != 0) {
            return response()->json(['success' => false, 'message' => 'La compra ya fue contabilizada'], 400);
        }

        $precio = $request->TotalBolivianos / $request->Unidades;

        $detalle = CompraDetalle::create([
            'IdCompras' => $request->IdCompras,
            'IdProducto' => $request->IdProducto,
            'Unidades' => $request->Unidades,
            'TotalBolivianos' => $request->TotalBolivianos,
            'Precio' => $precio,
        ]);

        $nuevoTotal = CompraDetalle::where('IdCompras', $request->IdCompras)->sum('TotalBolivianos');
        $compra->update(['ImporteFactura' => $nuevoTotal]);

        $detalle->load('producto');

        return response()->json([
            'success' => true,
            'detalle' => $detalle,
            'total_compra' => $nuevoTotal
        ]);
    }

    public function eliminarDetalle($id)
    {
        $detalle = CompraDetalle::findOrFail($id);
        $compra = Compra::findOrFail($detalle->IdCompras);

        if ($compra->ActivoInactivo != 0) {
            return response()->json(['success' => false, 'message' => 'La compra ya fue contabilizada'], 400);
        }

        $detalle->delete();

        $nuevoTotal = CompraDetalle::where('IdCompras', $compra->IdCompras)->sum('TotalBolivianos');
        $compra->update(['ImporteFactura' => $nuevoTotal]);

        return response()->json([
            'success' => true,
            'total_compra' => $nuevoTotal
        ]);
    }

    public function contabilizar($id)
    {
        $compra = Compra::porContexto()
            ->with(['detalles.producto'])
            ->findOrFail($id);

        if ($compra->ActivoInactivo != 0) {
            return redirect()->back()->with('error', 'La compra ya fue contabilizada');
        }

        // Validaciones
        if ($compra->IdAlmacen == 0) {
            return redirect()->back()->with('error', 'Seleccione un almacén');
        }
        if ($compra->IdTipoFactura == 0) {
            return redirect()->back()->with('error', 'Seleccione el tipo de factura');
        }
        if ($compra->IdFecha == 0) {
            return redirect()->back()->with('error', 'Seleccione una fecha');
        }
        if (empty($compra->NumeroFactura)) {
            return redirect()->back()->with('error', 'Ingrese el número de documento');
        }
        if ($compra->IdNIT == 0) {
            return redirect()->back()->with('error', 'Seleccione un proveedor');
        }
        if ($compra->detalles->count() == 0) {
            return redirect()->back()->with('error', 'Agregue al menos un producto');
        }

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // 1. Generar número correlativo
            $maxCorrelativo = Compra::porContexto()->max('NumeroCorrelativo');
            $numeroCorrelativo = ($maxCorrelativo ?? 0) + 1;

            // 2. Crear diario contable
            $maxNumeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->max('NumeroDiario');
            $numeroDiario = ($maxNumeroDiario ?? 0) + 1;

            $diarioId = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->insertGetId([
                    'IdFecha' => $compra->IdFecha,
                    'IdTipoDiario' => 7,
                    'NumeroDiario' => $numeroDiario,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'Contabilizado' => 1,
                    'IdOperadorIngreso' => $operadorId,
                    'FechaIngreso' => now(),
                    'IdoperadorEdita' => $operadorId,
                    'FechaEdita' => now(),
                ]);

            // 3. Obtener cuentas de parámetros
            $cuentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_parametros_cuentas')
                ->where('IdCliente', $clienteId)
                ->first();

            // 4. Factor de cambio
            $ufvActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_factorcambio')
                ->where('IdFecha', $compra->IdFecha)
                ->where('IdMoneda', 3)
                ->value('FactorCambio') ?? 1;

            $tipoDoc = $compra->IdTipoFactura == 1 ? 'Factura' : 'Recibo';
            $glosaBase = "Nota de Ingreso No {$numeroCorrelativo}, {$tipoDoc} No {$compra->NumeroFactura}";

            // 5. Insertar movimientos de inventario
            foreach ($compra->detalles as $detalle) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->insert([
                        'IdTipoDeOperacion' => 1,
                        'IdDocumento' => $diarioId,
                        'IdFecha' => $compra->IdFecha,
                        'IdAlmacen' => $compra->IdAlmacen,
                        'IdProducto' => $detalle->IdProducto,
                        'Glosa' => "Diario No {$numeroDiario}, {$glosaBase}",
                        'D_H' => 'D',
                        'Unidades' => $detalle->Unidades,
                        'Bolivianos' => $detalle->TotalBolivianos,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                    ]);
            }

            // 6. Insertar asientos contables
            $totalCompraBolivianos = $compra->ImporteFactura;
            $totalCompraUFV = round($totalCompraBolivianos / $ufvActual, 2);

            if ($compra->IdTipoFactura == 1) {
                $totalIVACF = round($totalCompraBolivianos * 0.13, 2);
                $totalNeto = $totalCompraBolivianos - $totalIVACF;
                $totalNetoUFV = round($totalNeto / $ufvActual, 2);

                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $diarioId,
                        'IdCuenta' => $cuentas->ComprasFacturadas ?? 1,
                        'Glosa' => $glosaBase,
                        'D_H' => 'D',
                        'MontoBolivianos' => $totalNeto,
                        'TipoCambio' => $ufvActual,
                        'MontoOtraMoneda' => $totalNetoUFV,
                        'IdIdentificador' => $compra->IdNIT,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $diarioId,
                        'IdCuenta' => $cuentas->CreditoFiscalIVA ?? 2,
                        'Glosa' => $glosaBase,
                        'D_H' => 'D',
                        'MontoBolivianos' => $totalIVACF,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $totalIVACF,
                        'IdIdentificador' => $compra->IdNIT,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);
            } else {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $diarioId,
                        'IdCuenta' => $cuentas->ComprasNoFacturadas ?? 1,
                        'Glosa' => $glosaBase,
                        'D_H' => 'D',
                        'MontoBolivianos' => $totalCompraBolivianos,
                        'TipoCambio' => $ufvActual,
                        'MontoOtraMoneda' => $totalCompraUFV,
                        'IdIdentificador' => $compra->IdNIT,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);
            }

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $cuentas->Proveedores ?? 3,
                    'Glosa' => $glosaBase,
                    'D_H' => 'H',
                    'MontoBolivianos' => $totalCompraBolivianos,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $totalCompraBolivianos,
                    'IdIdentificador' => $compra->IdNIT,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // 7. Actualizar compra
            $compra->update([
                'NumeroCorrelativo' => $numeroCorrelativo,
                'IdDiario' => $diarioId,
                'ActivoInactivo' => 1,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            // 8. Redirigir al PDF
            return redirect()->route('compras.pdf', $compra->IdCompras);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al contabilizar: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al contabilizar: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $compra = Compra::porContexto()
            ->with(['detalles.producto', 'almacen', 'proveedor', 'fecha'])
            ->findOrFail($id);

        return Inertia::render('Gestion/Impuestos/Compras/Show', [
            'compra' => $compra,
        ]);
    }
    /*pdf CON LIBRERIA TCPDF*/
    public function pdf($id)
    {
        // Cargar la compra con sus detalles y la relación producto
        $compra = Compra::porContexto()
            ->with([
                'almacen', 
                'proveedor', 
                'fecha',
                'detalles.producto'  // ← Esto es CLAVE: carga la relación producto
            ])
            ->findOrFail($id);

        // Crear PDF
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        
        $pdf->SetFont('courier', '', 8);
        
        // Logo
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 10, 50, 20);
        }

        // Número de compra
        $pdf->SetXY(179, 10);
        $pdf->SetFont('courier', 'B', 16);
        $pdf->Cell(18, 3, 'No ' . $compra->NumeroCorrelativo, 0, 1, 'L');

        // Número de diario
        $y = $pdf->GetY();
        $pdf->SetXY(179, $y + 1);
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(18, 3, 'Diario : ' . ($compra->IdDiario ?? '-'), 0, 1, 'L');

        // Título
        $y = $pdf->GetY();
        $pdf->SetXY(5, $y + 10);
        $pdf->SetFont('courier', 'B', 20);
        $pdf->Cell(200, 5, 'NOTA DE COMPRAS EXTERNAS', 0, 1, 'C');
        
        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 12);
        $pdf->SetXY(5, $y + 7);
        $pdf->Cell(200, 3, '(Expresado en Unidades)', 0, 1, 'C');

        // Datos de cabecera
        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 10);
        
        $pdf->SetXY(15, $y + 12);
        $pdf->Cell(200, 3, 'Proveedor : ' . ($compra->proveedor->CI_NIT ?? '') . ' - ' . ($compra->proveedor->Nombre ?? ''), 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->Cell(200, 3, 'Tipo      : ' . ($compra->IdTipoFactura == 1 ? 'Factura' : 'Recibo'), 0, 1, 'L');
        
        $pdf->SetXY(155, $y + 2);
        $pdf->Cell(200, 3, 'Numero  : ' . $compra->NumeroFactura, 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->Cell(200, 3, 'Almacen   : ' . ($compra->almacen->Almacen ?? ''), 0, 1, 'L');
        
        $pdf->SetXY(155, $y + 2);
        $pdf->Cell(200, 3, 'Fecha   : ' . date('d/m/Y', strtotime($compra->FechaIngreso)), 0, 1, 'L');

        // Observación
        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 9);
        $pdf->SetXY(15, $y + 4);
        $pdf->Cell(185, 4, 'OBSERVACION:', 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetFont('courier', '', 9);
        $pdf->SetXY(15, $y);
        $pdf->MultiCell(185, 4, $compra->Observacion ?? '', 0, 'L');
        
        $y = $pdf->GetY();

        // Tabla
        $y = $y + 4;
        $pdf->Line(15, $y, 200, $y);
        
        $pdf->SetFont('courier', 'B', 10);
        $pdf->SetXY(15, $y + 1);
        $pdf->Cell(10, 3, 'No', 0, 1, 'C');
        $pdf->SetXY(40, $y + 1);
        $pdf->Cell(10, 3, 'Codigo', 0, 1, 'C');
        $pdf->SetXY(90, $y + 1);
        $pdf->Cell(10, 3, 'Descripcion', 0, 1, 'C');
        $pdf->SetXY(155, $y + 1);
        $pdf->Cell(10, 3, 'Unidades', 0, 1, 'C');
        $pdf->SetXY(184, $y + 1);
        $pdf->Cell(10, 3, 'Total Bs', 0, 1, 'C');

        $y = $y + 7;
        $pdf->Line(15, $y, 200, $y);

        $y = $y + 4;
        $contador = 0;
        $sumaTotal = 0;
        
        foreach ($compra->detalles as $detalle) {
            $contador++;
            $producto = $detalle->producto;  // ← Esto carga el producto relacionado
            
            $codigo = $producto->Codigo ?? 'S/C';
            $descripcion = $producto->Descripcion ?? 'S/D';
            
            $pdf->SetFont('courier', '', 8);
            $pdf->SetXY(14, $y);
            $pdf->Cell(10, 4, $contador . '.-', 0, 1, 'R');
            
            $pdf->SetXY(27, $y);
            $pdf->MultiCell(40, 4, $codigo, 0, 'L', false);
            
            $pdf->SetXY(70, $y);
            $pdf->MultiCell(80, 4, $descripcion, 0, 'L', false);
            
            $pdf->SetXY(155, $y);
            $pdf->Cell(10, 4, number_format($detalle->Unidades, 4, ',', '.'), 0, 1, 'L');
            
            $pdf->SetXY(188, $y);
            $pdf->Cell(10, 4, number_format($detalle->TotalBolivianos, 2, ',', '.'), 0, 1, 'R');
            
            $y = $y + 7;
            $sumaTotal += $detalle->TotalBolivianos;
        }

        $pdf->Line(15, $y, 200, $y);
        
        $pdf->SetFont('courier', 'B', 10);
        $y = $y + 5;
        $pdf->SetXY(155, $y);
        $pdf->Cell(10, 3, 'TOTAL :', 0, 1, 'R');
        $pdf->SetXY(188, $y);
        $pdf->Cell(10, 3, number_format($sumaTotal, 2, ',', '.'), 0, 1, 'R');

        // Firmas
        $y = $y + 30;
        $pdf->SetFont('courier', 'B', 10);
        
        $pdf->SetXY(15, $y);
        $pdf->MultiCell(80, 3, '------------------------------------', 0, 'L');
        $pdf->SetXY(15, $y + 2);
        $pdf->MultiCell(80, 3, '           REALIZADO POR', 0, 'L');
        $pdf->SetXY(15, $y + 6);
        $pdf->MultiCell(80, 3, 'Nombre : _________________', 0, 'L');
        $pdf->SetXY(15, $y + 12);
        $pdf->MultiCell(80, 3, 'CI     : _________________', 0, 'L');

        $pdf->SetXY(123, $y);
        $pdf->MultiCell(80, 3, '------------------------------------', 0, 'L');
        $pdf->SetXY(123, $y + 2);
        $pdf->MultiCell(80, 3, '           REVISADO POR', 0, 'L');
        $pdf->SetXY(123, $y + 6);
        $pdf->MultiCell(80, 3, 'Nombre : _________________', 0, 'L');
        $pdf->SetXY(123, $y + 12);
        $pdf->MultiCell(80, 3, 'CI     : _________________', 0, 'L');

        $pdf->Output('compra_' . $compra->NumeroCorrelativo . '.pdf', 'I');
        exit;
    }

    private function getFechasDisponibles()
    {
        // Obtener fechas de la tabla principal
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('ActivoInactivo', 0)
            ->where('CierreSucursal', 0)
            ->where('CierrePermanente', 0)
            ->select('IdFecha as id', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as fecha_display"), 'Fecha as fecha_raw')
            ->orderBy('IdFecha', 'desc')
            ->get();

        // Obtener fechas auxiliares de la sucursal
        $fechasAux = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha_auxiliar_sucursal')
            ->join('todos_fecha', 'todos_fecha_auxiliar_sucursal.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('todos_fecha_auxiliar_sucursal.IdCliente', session('cliente_id'))
            ->where('todos_fecha_auxiliar_sucursal.IdSucursal', session('cliente_sucursal_id'))
            ->select('todos_fecha.IdFecha as id', DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha_display"), 'todos_fecha.Fecha as fecha_raw')
            ->orderBy('todos_fecha.IdFecha', 'desc')
            ->get();

        $todasFechas = $fechas->merge($fechasAux)->unique('id');
        
        // 🔥 LOG para depuración
        \Log::info('Fechas disponibles para combo:', $todasFechas->toArray());
        
        return $todasFechas;
    }
    
}