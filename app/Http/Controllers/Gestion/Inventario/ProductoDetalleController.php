<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;
use App\Models\Gestion\Inventario\ProductoLinea;
use App\Models\Gestion\Inventario\ProductoEstado;
use App\Models\Gestion\Inventario\UnidadMedida;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoAnalisisMinimo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ProductoMinimo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProductoDetalleController extends Controller
{
    /**
     * Listado de productos (GRID)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle as p')
            ->leftJoin('inventario_productogrupoanalisis as g', 'p.IdGrupoAnalisis', '=', 'g.IdGrupoAnalisis')
            ->leftJoin('inventario_producto_linea as l', 'p.IdLineaProducto', '=', 'l.IdLinea')
            ->leftJoin('inventario_producto_estado as e', 'p.IdEstadoProducto', '=', 'e.IdEstado')
            ->leftJoin('inventario_unidadmedida as u', 'p.IdUnidadMedida', '=', 'u.IdUnidadMedida')
            ->where('p.IdCliente', $clienteId)
            ->select(
                'p.IdProducto',
                'p.Codigo',
                'p.Descripcion',
                'p.ActivoInactivo',
                'p.IdGrupoAnalisis',
                'p.IdLineaProducto',
                'p.IdEstadoProducto',
                'p.IdUnidadMedida',
                'p.OrdenInformes',
                'g.Grupo as grupo_nombre',
                'l.Linea as linea_nombre',
                'e.Estado as estado_nombre',
                'u.UnidadMedida as unidad_nombre'
            );

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('p.Codigo', 'like', "%{$search}%")
                    ->orWhere('p.Descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('p.ActivoInactivo', $request->estado);
        }

        if ($request->filled('linea')) {
            $query->where('p.IdLineaProducto', $request->linea);
        }

        if ($request->filled('estadoProducto') && $request->estadoProducto !== '') {
            $query->where('p.IdEstadoProducto', $request->estadoProducto);
        }

        if ($request->filled('grupo')) {
            $query->where('p.IdGrupoAnalisis', $request->grupo);
        }

        $productosData = $query->orderBy('p.Codigo')->paginate(20);

        $productos = new \stdClass();
        $productos->data = [];

        foreach ($productosData as $item) {
            $productos->data[] = (object) [
                'IdProducto' => $item->IdProducto,
                'Codigo' => $item->Codigo,
                'Descripcion' => $item->Descripcion,
                'ActivoInactivo' => $item->ActivoInactivo,
                'IdGrupoAnalisis' => $item->IdGrupoAnalisis,
                'IdLineaProducto' => $item->IdLineaProducto,
                'IdEstadoProducto' => $item->IdEstadoProducto,
                'IdUnidadMedida' => $item->IdUnidadMedida,
                'OrdenInformes' => $item->OrdenInformes ?? 0,
                'grupoAnalisis' => (object) [
                    'IdGrupoAnalisis' => $item->IdGrupoAnalisis,
                    'Grupo' => $item->grupo_nombre ?? '-'
                ],
                'linea' => (object) [
                    'IdLinea' => $item->IdLineaProducto,
                    'Linea' => $item->linea_nombre ?? '-'
                ],
                'estado' => (object) [
                    'IdEstado' => $item->IdEstadoProducto,
                    'Estado' => $item->estado_nombre ?? '-'
                ],
                'unidadMedida' => (object) [
                    'IdUnidadMedida' => $item->IdUnidadMedida,
                    'UnidadMedida' => $item->unidad_nombre ?? '-'
                ]
            ];
        }

        $productos->links = $productosData->links();
        $productos->currentPage = $productosData->currentPage();
        $productos->lastPage = $productosData->lastPage();
        $productos->from = $productosData->firstItem();
        $productos->to = $productosData->lastItem();
        $productos->total = $productosData->total();
        $productos->perPage = $productosData->perPage();
        $productos->path = $productosData->path();

        $totalActivos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->count();

        $totalInactivos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->count();

        $grupos = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
            ->orderBy('IdGrupoAnalisis')
            ->get(['IdGrupoAnalisis as id', 'Grupo as nombre']);

        $lineas = ProductoLinea::where('IdCliente', $clienteId)
            ->orderBy('Linea')
            ->get(['IdLinea as id', 'Linea as nombre']);

        $estados = ProductoEstado::where('IdCliente', $clienteId)
            ->orderBy('Estado')
            ->get(['IdEstado as id', 'Estado as nombre']);

        $unidades = UnidadMedida::orderBy('IdUnidadMedida')
            ->get(['IdUnidadMedida as id', 'UnidadMedida as nombre']);

        $unidadId = null;
        $unidad = $unidades->firstWhere('nombre', 'Unidad');
        if ($unidad) {
            $unidadId = $unidad->id;
        }

        // ✅ Lista de IDs de grupos activos (para mostrar/ocultar sección en el modal)
        $gruposConMinimo = GrupoAnalisisMinimo::obtenerIdsActivos($clienteId, $sucursalId);
        $gruposConMinimo = array_map('intval', $gruposConMinimo);

        // ✅ Mapa de mínimos de productos (para saber qué productos ya están configurados)
        $productosConMinimo = ProductoMinimo::porContexto($clienteId, $sucursalId)
            ->activos()
            ->pluck('CantidadMinimaProducto', 'IdProducto')
            ->toArray();

        return Inertia::render('Gestion/Inventario/ProductoDetalle/Index', [
            'productos' => $productos,
            'totalActivos' => $totalActivos,
            'totalInactivos' => $totalInactivos,
            'grupos' => $grupos,
            'lineas' => $lineas,
            'estados' => $estados,
            'unidades' => $unidades,
            'unidadId' => $unidadId,
            'gruposConMinimo' => $gruposConMinimo,
            'productosConMinimo' => $productosConMinimo,
            'filtros' => [
                'search' => $request->search,
                'estado' => $request->estado,
                'linea' => $request->linea,
                'grupo' => $request->grupo,
                'estadoProducto' => $request->estadoProducto,
            ],
        ]);
    }

    /**
     * Store - Crear producto
     * (SIN CAMBIOS: el mínimo se guarda desde el frontend con un segundo request)
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'IdGrupoAnalisis' => 'required|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'IdLineaProducto' => 'required|exists:inventario_producto_linea,IdLinea',
            'IdEstadoProducto' => 'required|exists:inventario_producto_estado,IdEstado',
            'IdUnidadMedida' => 'required|exists:inventario_unidadmedida,IdUnidadMedida',
            'Codigo' => 'required|string|max:200|unique:inventario_productodetalle,Codigo,NULL,IdProducto,IdCliente,' . $clienteId,
            'Descripcion' => 'required|string|max:200|unique:inventario_productodetalle,Descripcion,NULL,IdProducto,IdCliente,' . $clienteId,
            'OrdenInformes' => 'nullable|integer|min:0',
            'ActivoInactivo' => 'nullable|boolean',
        ]);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $producto = ProductoDetalle::create([
                'IdGrupoAnalisis' => $request->IdGrupoAnalisis,
                'IdLineaProducto' => $request->IdLineaProducto,
                'IdEstadoProducto' => $request->IdEstadoProducto,
                'IdUnidadMedida' => $request->IdUnidadMedida,
                'OrdenInformes' => $request->OrdenInformes ?? 0,
                'Codigo' => $request->Codigo,
                'Descripcion' => $request->Descripcion,
                'Precio' => 0,
                'ActivoInactivo' => $request->ActivoInactivo ?? 0,
                'CkeckListRuta' => 0,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
                'CierrePermanente' => 0,
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto creado correctamente',
                'producto' => $producto
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al crear producto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update - Actualizar producto
     * (SIN CAMBIOS: el mínimo se guarda desde el frontend con un segundo request)
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $producto = ProductoDetalle::where('IdCliente', $clienteId)->findOrFail($id);

        $request->validate([
            'IdGrupoAnalisis' => 'required|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'IdLineaProducto' => 'required|exists:inventario_producto_linea,IdLinea',
            'IdEstadoProducto' => 'required|exists:inventario_producto_estado,IdEstado',
            'IdUnidadMedida' => 'required|exists:inventario_unidadmedida,IdUnidadMedida',
            'Codigo' => 'required|string|max:200|unique:inventario_productodetalle,Codigo,' . $id . ',IdProducto,IdCliente,' . $clienteId,
            'Descripcion' => 'required|string|max:200|unique:inventario_productodetalle,Descripcion,' . $id . ',IdProducto,IdCliente,' . $clienteId,
            'OrdenInformes' => 'nullable|integer|min:0',
            'ActivoInactivo' => 'nullable|boolean',
        ]);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $producto->update([
                'IdGrupoAnalisis' => $request->IdGrupoAnalisis,
                'IdLineaProducto' => $request->IdLineaProducto,
                'IdEstadoProducto' => $request->IdEstadoProducto,
                'IdUnidadMedida' => $request->IdUnidadMedida,
                'OrdenInformes' => $request->OrdenInformes ?? 0,
                'Codigo' => $request->Codigo,
                'Descripcion' => $request->Descripcion,
                'ActivoInactivo' => $request->ActivoInactivo ?? 0,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado correctamente',
                'producto' => $producto
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al actualizar producto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit - Obtener producto + su mínimo actual
     */
    public function edit($id)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');

            $producto = ProductoDetalle::where('IdCliente', $clienteId)
                ->with(['grupoAnalisis', 'linea', 'estado', 'unidadMedida'])
                ->findOrFail($id);

            // Buscar el mínimo actual del producto
            $minimo = ProductoMinimo::porContexto($clienteId, $sucursalId)
                ->where('IdProducto', $id)
                ->where('ActivoInactivo', 1)
                ->first(['CantidadMinimaProducto', 'DisponibleParaPedido']);

            return response()->json([
                'success' => true,
                'producto' => $producto,
                'minimo' => $minimo ? [
                    'CantidadMinimaProducto' => (float) $minimo->CantidadMinimaProducto,
                    'DisponibleParaPedido' => (int) $minimo->DisponibleParaPedido,
                ] : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo producto para editar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el producto'
            ], 500);
        }
    }

    /**
     * Destroy - Eliminar producto
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoDetalle::where('IdCliente', $clienteId)->findOrFail($id);

        try {
            $producto->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX: Validar si el código ya existe
     */
    public function validarCodigo(Request $request)
    {
        $clienteId = session('cliente_id');
        $codigo = $request->codigo;
        $id = $request->id;

        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('Codigo', $codigo);

        if ($id) {
            $query->where('IdProducto', '!=', $id);
        }

        $existe = $query->exists();

        return response()->json([
            'existe' => $existe,
            'message' => $existe ? '¡El código ya existe para este cliente!' : null
        ]);
    }

    /**
     * AJAX: Validar si la descripción ya existe
     */
    public function validarDescripcion(Request $request)
    {
        $clienteId = session('cliente_id');
        $descripcion = $request->descripcion;
        $id = $request->id;

        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('Descripcion', $descripcion);

        if ($id) {
            $query->where('IdProducto', '!=', $id);
        }

        $existe = $query->exists();

        return response()->json([
            'existe' => $existe,
            'message' => $existe ? '¡La descripción ya existe para este cliente!' : null
        ]);
    }

    /**
     * Cambiar estado (Activar/Desactivar)
     */
    public function toggleEstado($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoDetalle::where('IdCliente', $clienteId)->findOrFail($id);

        try {
            $nuevoEstado = $producto->ActivoInactivo == 0 ? 1 : 0;
            $producto->update([
                'ActivoInactivo' => $nuevoEstado,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $nuevoEstado == 0 ? 'Producto activado' : 'Producto desactivado',
                'nuevo_estado' => $nuevoEstado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para obtener productos (selectores)
     */
    public function getProductos(Request $request)
    {
        $clienteId = session('cliente_id');

        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                    ->orWhere('Descripcion', 'like', "%{$search}%");
            });
        }

        $productos = $query->orderBy('Codigo')
            ->limit(50)
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);

        return response()->json($productos);
    }

    /**
     * Exportar listado de productos a PDF
     */
    public function exportarPdf(Request $request)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // ================== QUERY ==================
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle as p')
            ->leftJoin('inventario_productogrupoanalisis as g', 'p.IdGrupoAnalisis', '=', 'g.IdGrupoAnalisis')
            ->leftJoin('inventario_producto_linea as l', 'p.IdLineaProducto', '=', 'l.IdLinea')
            ->leftJoin('inventario_producto_estado as e', 'p.IdEstadoProducto', '=', 'e.IdEstado')
            ->leftJoin('inventario_unidadmedida as u', 'p.IdUnidadMedida', '=', 'u.IdUnidadMedida')
            ->where('p.IdCliente', $clienteId)
            ->select(
                'p.IdProducto',
                'p.Codigo',
                'p.Descripcion',
                'p.ActivoInactivo',
                'p.OrdenInformes',
                'g.Grupo as grupo_nombre',
                'l.Linea as linea_nombre',
                'e.Estado as estado_nombre',
                'u.UnidadMedida as unidad_nombre'
            );

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('p.Codigo', 'like', "%{$search}%")
                    ->orWhere('p.Descripcion', 'like', "%{$search}%");
            });
        }
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('p.ActivoInactivo', $request->estado);
        }
        if ($request->filled('linea')) {
            $query->where('p.IdLineaProducto', $request->linea);
        }
        if ($request->filled('estadoProducto') && $request->estadoProducto !== '') {
            $query->where('p.IdEstadoProducto', $request->estadoProducto);
        }
        if ($request->filled('grupo')) {
            $query->where('p.IdGrupoAnalisis', $request->grupo);
        }

        $productos = $query->orderBy('p.Codigo')->get();

        // ================== INFO EMPRESA / OPERADOR ==================
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);

        $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

        // ================== FILTROS APLICADOS ==================
        $filtroGrupoNombre = $request->filled('grupo')
            ? DB::connection('mysql_gestion_comercial_alimentos')->table('inventario_productogrupoanalisis')->where('IdGrupoAnalisis', $request->grupo)->value('Grupo')
            : null;

        $filtroLineaNombre = $request->filled('linea')
            ? DB::connection('mysql_gestion_comercial_alimentos')->table('inventario_producto_linea')->where('IdLinea', $request->linea)->value('Linea')
            : null;

        $filtroEstadoNombre = $request->filled('estadoProducto')
            ? DB::connection('mysql_gestion_comercial_alimentos')->table('inventario_producto_estado')->where('IdEstado', $request->estadoProducto)->value('Estado')
            : null;

        $filtroActivoTexto = 'Todos';
        if ($request->estado === '0') $filtroActivoTexto = 'Activos';
        if ($request->estado === '1') $filtroActivoTexto = 'Inactivos';

        // ================== PDF ==================
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont('helvetica', '', 8);

        // Anchos (total 281 mm = A4 landscape - márgenes 8+8)
        $anchos = [
            'num'         => 8,
            'codigo'      => 22,
            'descripcion' => 80,
            'grupo'       => 40,
            'linea'       => 45,
            'tipo'        => 25,
            'unidad'      => 22,
            'orden'       => 14,
            'estado'      => 25,
        ];

        // ===== FUNCIÓN: dibujar cabecera empresa =====
        $dibujarCabecera = function () use ($pdf, $empresa, $operador, $fechaImpresion) {
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetTextColor(26, 35, 126);
            $pdf->SetXY(8, 8);
            $pdf->Cell(281, 7, 'LISTADO DE PRODUCTOS', 0, 1, 'C');

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetX(8);
            $pdf->Cell(281, 5, mb_strtoupper($empresa->Nombre ?? '', 'UTF-8'), 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetX(8);
            $pdf->Cell(281, 4, 'Fecha impresión: ' . $fechaImpresion . '   |   Operador: ' . ($operador->nombre ?? '-'), 0, 1, 'C');

            $pdf->SetDrawColor(26, 35, 126);
            $pdf->SetLineWidth(0.5);
            $pdf->Line(8, $pdf->GetY() + 1, 289, $pdf->GetY() + 1);
            $pdf->Ln(3);
        };

        // ===== FUNCIÓN: dibujar cabecera de tabla =====
        $dibujarCabeceraTabla = function () use ($pdf, $anchos) {
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(217, 225, 242);
            $pdf->SetTextColor(26, 35, 126);
            $pdf->SetDrawColor(120, 120, 120);
            $pdf->SetLineWidth(0.2);

            $pdf->SetX(8);
            $pdf->Cell($anchos['num'],         7, '#',           1, 0, 'C', 1);
            $pdf->Cell($anchos['codigo'],      7, 'Código',      1, 0, 'C', 1);
            $pdf->Cell($anchos['descripcion'], 7, 'Descripción', 1, 0, 'C', 1);
            $pdf->Cell($anchos['grupo'],       7, 'Grupo',       1, 0, 'C', 1);
            $pdf->Cell($anchos['linea'],       7, 'Línea',       1, 0, 'C', 1);
            $pdf->Cell($anchos['tipo'],        7, 'Tipo',        1, 0, 'C', 1);
            $pdf->Cell($anchos['unidad'],      7, 'Unidad',      1, 0, 'C', 1);
            $pdf->Cell($anchos['orden'],       7, 'Orden',       1, 0, 'C', 1);
            $pdf->Cell($anchos['estado'],      7, 'Estado',      1, 1, 'C', 1);
        };

        // ===== PRIMERA PÁGINA =====
        $pdf->AddPage();
        $dibujarCabecera();

        // Filtros aplicados
        $filtrosAplicados = [];
        if ($request->filled('search')) $filtrosAplicados[] = 'Búsqueda: "' . $request->search . '"';
        $filtrosAplicados[] = 'Estado: ' . $filtroActivoTexto;
        if ($filtroGrupoNombre) $filtrosAplicados[] = 'Grupo: ' . $filtroGrupoNombre;
        if ($filtroLineaNombre) $filtrosAplicados[] = 'Línea: ' . $filtroLineaNombre;
        if ($filtroEstadoNombre) $filtrosAplicados[] = 'Tipo: ' . $filtroEstadoNombre;

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetX(8);
        $pdf->Cell(281, 5, 'Filtros aplicados: ' . implode('  |  ', $filtrosAplicados), 0, 1, 'L');
        $pdf->SetX(8);
        $pdf->Cell(281, 5, 'Total de productos: ' . count($productos), 0, 1, 'L');
        $pdf->Ln(1);

        $dibujarCabeceraTabla();

        // ===== FILAS =====
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->SetLineWidth(0.15);

        $alturaFila = 5.5;
        $alturaMaxPagina = 195; // A4 landscape útil

        $contador = 0;
        $fill = false;

        foreach ($productos as $p) {
            // Salto de página
            if (($pdf->GetY() + $alturaFila) > $alturaMaxPagina) {
                $pdf->AddPage();
                $dibujarCabecera();
                $dibujarCabeceraTabla();
                $pdf->SetFont('helvetica', '', 6.5);
                $pdf->SetTextColor(40, 40, 40);
                $pdf->SetDrawColor(160, 160, 160);
                $pdf->SetLineWidth(0.15);
                $fill = false;
            }

            $contador++;

            // Truncar descripción
            $descripcion = $p->Descripcion ?? '-';
            if (mb_strlen($descripcion, 'UTF-8') > 55) {
                $descripcion = mb_substr($descripcion, 0, 52, 'UTF-8') . '...';
            }

            $estado = ($p->ActivoInactivo == 0) ? 'Activo' : 'Inactivo';
            $fillColor = $fill ? [250, 250, 250] : [255, 255, 255];
            $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

            $pdf->SetX(8);
            $pdf->Cell($anchos['num'],         $alturaFila, $contador,                   1, 0, 'C', true);
            $pdf->Cell($anchos['codigo'],      $alturaFila, $p->Codigo ?? '-',            1, 0, 'L', true);
            $pdf->Cell($anchos['descripcion'], $alturaFila, $descripcion,                 1, 0, 'L', true);
            $pdf->Cell($anchos['grupo'],       $alturaFila, $p->grupo_nombre ?? '-',      1, 0, 'L', true);
            $pdf->Cell($anchos['linea'],       $alturaFila, $p->linea_nombre ?? '-',      1, 0, 'L', true);
            $pdf->Cell($anchos['tipo'],        $alturaFila, $p->estado_nombre ?? '-',     1, 0, 'L', true);
            $pdf->Cell($anchos['unidad'],      $alturaFila, $p->unidad_nombre ?? '-',     1, 0, 'L', true);
            $pdf->Cell($anchos['orden'],       $alturaFila, $p->OrdenInformes ?? 0,       1, 0, 'C', true);
            $pdf->Cell($anchos['estado'],      $alturaFila, $estado,                      1, 1, 'C', true);

            $fill = !$fill;
        }

        // ===== TOTALES =====
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(26, 35, 126);
        $pdf->SetX(8);

        $totalActivos = collect($productos)->where('ActivoInactivo', 0)->count();
        $totalInactivos = collect($productos)->where('ActivoInactivo', 1)->count();

        $pdf->Cell(281, 6, 'Total: ' . count($productos) . ' productos   |   Activos: ' . $totalActivos . '   |   Inactivos: ' . $totalInactivos, 0, 1, 'R');

        // ===== PIE DE PÁGINA =====
        $pdf->SetY(-12);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(281, 5, 'Documento generado automáticamente - ' . $fechaImpresion, 0, 0, 'C');

        // ===== OUTPUT =====
        if (ob_get_length()) {
            ob_end_clean();
        }

        $nombreArchivo = 'Productos_Inventario_' . date('Ymd_His') . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit;
    }
}