<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\Diario;
use App\Models\Gestion\Contabilidad\DiarioPropiamente;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Operador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImprimirDiarioController extends Controller
{
    /**
     * Mostrar formulario de selección de diario
     * Ahora muestra TODOS los diarios de la sucursal logueada
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $sucursalNombre = session('cliente_sucursal_nombre');
        $operadorId = session('operador_id');
        $tipoOperador = session('operador_tipo_id');
        
        $esSupervisor = in_array($tipoOperador, [1, 2, 11]);
        
        $sucursales = [];
        if ($esSupervisor) {
            $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
                ->orderBy('Nombre')
                ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        }
        
        if (!$sucursalNombre && $sucursalId) {
            $sucursal = ClienteSucursal::find($sucursalId);
            $sucursalNombre = $sucursal->Nombre ?? null;
        }
        
        // 🔥 PAGINACIÓN: 20 por página
        $diariosRecientes = Diario::porContexto()
            ->with(['tipoDiario', 'sucursal'])
            ->where('Contabilizado', 1)
            ->where('NumeroDiario', '>', 0)
            ->where('IdSucursal', $sucursalId)
            ->orderBy('NumeroDiario', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function($diario) {
                return [
                    'id' => $diario->IdDiario,
                    'numero' => $diario->NumeroDiario,
                    'tipo' => $diario->tipoDiario->TipoDiario ?? 'Diario',
                    'fecha' => $diario->fecha ? date('d/m/Y', strtotime($diario->fecha->Fecha)) : null,
                    'sucursal' => $diario->sucursal->Nombre ?? null,
                ];
            });
        
        return Inertia::render('Gestion/Contabilidad/ImprimirDiario/Index', [
            'sucursales' => $sucursales,
            'sucursalId' => $sucursalId,
            'sucursalNombre' => $sucursalNombre,
            'esSupervisor' => $esSupervisor,
            'diariosRecientes' => $diariosRecientes, // 🔥 Ahora es un paginador
        ]);
    }

    /**
     * NUEVA VISTA: Seleccionar sucursal y ver sus diarios
     */
    public function porSucursal()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $tipoOperador = session('operador_tipo_id');
        
        $esSupervisor = in_array($tipoOperador, [1, 2, 11]);
        
        // Obtener todas las sucursales del cliente
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // Si hay sucursal en sesión, cargar sus diarios
        $diarios = [];
        if ($sucursalId) {
            $diarios = $this->obtenerDiariosPorSucursal($sucursalId);
        }
        
        return Inertia::render('Gestion/Contabilidad/ImprimirDiario/PorSucursal', [
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId,
            'diarios' => $diarios,
            'esSupervisor' => $esSupervisor,
        ]);
    }
    
    /**
     * Obtener diarios de una sucursal específica (AJAX)
     */
    public function getDiariosPorSucursal(Request $request)
    {
        try {
            $request->validate([
                'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal'
            ]);
            
            $sucursalId = $request->sucursal_id;
            $clienteId = session('cliente_id');
            
            $diarios = Diario::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('Contabilizado', 1)
                ->where('NumeroDiario', '>', 0)
                ->orderBy('NumeroDiario', 'desc')
                ->limit(100)
                ->get();
            
            $resultados = [];
            foreach ($diarios as $diario) {
                $tipoDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_tipodiario')
                    ->where('IdTipoDiario', $diario->IdTipoDiario)
                    ->value('TipoDiario');
                
                $fecha = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->where('IdFecha', $diario->IdFecha)
                    ->value('Fecha');
                
                $operador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_operador as o')
                    ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                    ->where('o.IdOperador', $diario->IdOperadorIngreso)
                    ->first();
                
                $resultados[] = [
                    'id' => $diario->IdDiario,
                    'numero' => $diario->NumeroDiario,
                    'tipo' => $tipoDiario ?? 'Diario',
                    'fecha' => $fecha ? date('d/m/Y', strtotime($fecha)) : null,
                    'operador' => $operador->Nombre ?? 'Desconocido',
                ];
            }
            
            return response()->json([
                'success' => true,
                'diarios' => $resultados
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error en getDiariosPorSucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'diarios' => []
            ]);
        }
    }
    /**
     * Método auxiliar para obtener diarios de una sucursal
     */
    private function obtenerDiariosPorSucursal($sucursalId)
    {
        return Diario::porContexto()
            ->with(['tipoDiario'])
            ->where('Contabilizado', 1)
            ->where('NumeroDiario', '>', 0)
            ->where('IdSucursal', $sucursalId)
            ->orderBy('NumeroDiario', 'desc')
            ->limit(100)
            ->get()
            ->map(function($diario) {
                return [
                    'id' => $diario->IdDiario,
                    'numero' => $diario->NumeroDiario,
                    'tipo' => $diario->tipoDiario->TipoDiario ?? 'Diario',
                    'fecha' => $diario->fecha ? date('d/m/Y', strtotime($diario->fecha->Fecha)) : null,
                    'operador' => $diario->operadorIngreso?->identificador?->Nombre ?? 'Desconocido',
                ];
            });
    }

    /**
     * Buscar diarios por número (autocompletado) - MODIFICADO
     * Ahora muestra TODOS los diarios de la sucursal (sin filtrar por operador)
    */
    public function buscar(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|min:1'
        ]);
        
        $q = $request->get('q', '');
        $sucursalId = $request->sucursal_id ?? session('cliente_sucursal_id');
        $clienteId = session('cliente_id');
        
        if (empty($q) || !is_numeric($q)) {
            return response()->json([
                'success' => true,
                'diarios' => []
            ]);
        }
        
        try {
            $diarios = Diario::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('Contabilizado', 1)
                ->where('NumeroDiario', (int) $q)
                ->with('tipoDiario')
                ->limit(10)
                ->get(['IdDiario', 'NumeroDiario', 'IdTipoDiario', 'IdFecha']);
            
            $resultados = $diarios->map(function($diario) {
                $fecha = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->where('IdFecha', $diario->IdFecha)
                    ->value('Fecha');
                
                return [
                    'id' => $diario->IdDiario,
                    'numero' => $diario->NumeroDiario,
                    'tipo' => $diario->tipoDiario->TipoDiario ?? 'Diario',
                    'fecha' => $fecha ? date('d/m/Y', strtotime($fecha)) : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'diarios' => $resultados
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error en buscar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'diarios' => []
            ], 500);
        }
    }
    
    /**
     * Obtener operadores por sucursal (AJAX) - se mantiene igual
     */
    public function getOperadoresPorSucursal($sucursalId)
    {
        $clienteId = session('cliente_id');
        
        $operadores = Operador::whereHas('sucursales', function($q) use ($clienteId, $sucursalId) {
                $q->where('todos_cliente_sucursal.IdCliente', $clienteId)
                  ->where('todos_cliente_sucursal.IdClienteSucursal', $sucursalId);
            })
            ->with('identificador')
            ->get()
            ->map(fn($op) => [
                'id' => $op->IdOperador,
                'nombre' => $op->identificador?->Nombre ?? 'Sin nombre',
            ]);
        
        return response()->json([
            'success' => true,
            'operadores' => $operadores
        ]);
    }
    /**
     * Generar PDF del diario
     */
    public function pdf($id)
    {
        // 🔥 CARGAR DIARIO CON TODAS LAS RELACIONES
        $diario = Diario::porContexto()
            ->with([
                'fecha', 
                'sucursal', 
                'tipoDiario',
                'asientos' => function($query) {
                    // 🔥 ORDEN EXACTO DEL SCRIPT: solo por IdContaPropiamente
                    $query->orderBy('IdContaPropiamente', 'asc');
                },
                'asientos.cuenta', 
                'asientos.identificador'
            ])
            ->findOrFail($id);
        
        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first();
        
        // Operador que ingresó
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $diario->IdOperadorIngreso)
            ->first();
        
        // Firmas autorizadas
        $firmaRevisa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_firmasautorizadas')
            ->where('Nivel', 'Revisado')
            ->where('IdCliente', session('cliente_id'))
            ->first();
        
        $firmaAprueba = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_firmasautorizadas')
            ->where('Nivel', 'Aprobado')
            ->where('IdCliente', session('cliente_id'))
            ->first();
        
        $nombreRevisa = null;
        $cargoRevisa = null;
        if ($firmaRevisa) {
            $identificador = Identificador::find($firmaRevisa->IdIdentificador);
            $nombreRevisa = $identificador ? $identificador->Nombre : null;
            $cargoRevisa = $firmaRevisa->Cargo;
        }
        
        $nombreAprueba = null;
        $cargoAprueba = null;
        if ($firmaAprueba) {
            $identificador = Identificador::find($firmaAprueba->IdIdentificador);
            $nombreAprueba = $identificador ? $identificador->Nombre : null;
            $cargoAprueba = $firmaAprueba->Cargo;
        }
        
        // Totales
        $totalDebe = 0;
        $totalHaber = 0;
        
        // ==================== CREAR PDF ====================
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60); // 🔥 IGUAL QUE SCRIPTCASE
        $pdf->SetAutoPageBreak(false); // 🔥 DESACTIVAR AUTO PAGE BREAK (lo manejamos manual)
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 8);
        
        // ==================== ENCABEZADO ====================
        $nombreEmpresa = $empresa->Nombre ?? '';
        $sucursalCabecera = $diario->sucursal->Nombre ?? '';
        $numeroDiario = $diario->NumeroDiario ?? '';
        $fechaDiario = $diario->fecha ? date('d/m/Y', strtotime($diario->fecha->Fecha)) : '';
        $tipoDiario = $diario->tipoDiario->TipoDiario ?? '';
        $operadorIngreso = $operador->Iniciales ?? ($operador->Nombre ?? '');
        
        // Logo (espacio vacío en script original)
        $pdf->SetFont('Courier', '', 8);
        $pdf->SetXY(80, 5);
        $pdf->Cell(30, 5, '', 0, 1, 'C');
        
        // Nombre empresa
        $pdf->SetXY(6, 5);
        $pdf->Cell(10, 3, $nombreEmpresa, 0, 1, 'L');
        
        // Sucursal
        $pdf->SetXY(6, 8);
        $pdf->Cell(10, 3, $sucursalCabecera, 0, 1, 'L');
        
        // Número diario
        $pdf->SetXY(6, 11);
        $pdf->Cell(18, 3, 'NUMERO DIARIO:', 0, 1, 'L');
        $pdf->SetXY(30, 11);
        $pdf->Cell(15, 3, $numeroDiario, 0, 1, 'L');
        
        // Fecha diario
        $pdf->SetXY(6, 15);
        $pdf->Cell(15, 3, 'FECHA DIARIO:', 0, 1, 'L');
        $pdf->SetXY(30, 15);
        $pdf->Cell(15, 3, $fechaDiario, 0, 1, 'L');
        
        // Número de página
        $pdf->SetXY(195, 5);
        $pdf->Cell(10, 3, 'Pg. ' . $pdf->PageNo(), 0, 0, 'L');
        
        // Tipo diario
        $pdf->SetXY(170, 11);
        $pdf->Cell(15, 3, 'TIPO DIARIO:', 0, 1, 'C');
        $pdf->SetXY(190, 11);
        $pdf->Cell(15, 3, $tipoDiario, 0, 1, 'C');
        
        // Origen diario
        $pdf->SetXY(80, 11);
        $pdf->Cell(15, 3, 'Origen Diario:', 0, 1, 'C');
        $pdf->SetXY(100, 11);
        $pdf->Cell(15, 3, $operadorIngreso, 0, 1, 'C');
        
        // 🔥 LÍNEAS PUNTEADAS DIVISORAS INICIALES
        $lineaPunteada = '----------------------------------------------------------------------------------------------------------------------------------------------------------------';
        $pdf->SetXY(5, 17);
        $pdf->Cell(15, 3, $lineaPunteada, 0, 1, 'L');
        $pdf->SetXY(8, 20);
        $pdf->Cell(15, 3, 'Cuenta', 0, 1, 'L');
        $pdf->SetXY(10, 22);
        $pdf->Cell(15, 3, 'Glosa', 0, 1, 'L');
        $pdf->SetXY(105, 22);
        $pdf->Cell(15, 3, 'Tipo Cambio', 0, 1, 'L');
        $pdf->SetXY(160, 22);
        $pdf->Cell(15, 3, 'Debe', 0, 1, 'L');
        $pdf->SetXY(190, 22);
        $pdf->Cell(15, 3, 'Haber', 0, 1, 'L');
        $pdf->SetXY(12, 24);
        $pdf->Cell(15, 3, 'Identificador', 0, 1, 'L');
        $pdf->SetXY(5, 26);
        $pdf->Cell(15, 3, $lineaPunteada, 0, 1, 'L');
        
        // ==================== ASIENTOS ====================
        // Posición Y inicial (después del encabezado)
        $y = 28;
        
        foreach ($diario->asientos as $asiento) {
            // Datos del asiento
            $cuentaNumero = $asiento->cuenta->Cuenta ?? '';
            $cuentaDescripcion = $asiento->cuenta->Descripcion ?? '';
            $glosa = $asiento->Glosa ?? '';
            $d_h = $asiento->D_H ?? '';
            $montoBolivianos = $asiento->MontoBolivianos ?? 0;
            $tipoCambio = $asiento->TipoCambio ?? 0;
            $montoOtraMoneda = $asiento->MontoOtraMoneda ?? 0;
            $deducible = $asiento->Deducible ?? '';
            
            // Identificador
            $identificadorCI = '';
            $identificadorNombre = '';
            if ($asiento->IdIdentificador && $asiento->IdIdentificador > 0) {
                if ($asiento->identificador) {
                    $identificadorCI = $asiento->identificador->CI_NIT ?? '';
                    $identificadorNombre = $asiento->identificador->Nombre ?? '';
                } else {
                    $idDB = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('todos_identificador')
                        ->where('IdIdentificador', $asiento->IdIdentificador)
                        ->first();
                    if ($idDB) {
                        $identificadorCI = $idDB->CI_NIT ?? '';
                        $identificadorNombre = $idDB->Nombre ?? '';
                    }
                }
            }
            
            // 🔥 CALCULAR ALTO DE LA GLOSA (multicell)
            $largo = $pdf->GetStringWidth($glosa);
            
            // ===== FILA 1: Cuenta + Descripción =====
            $pdf->SetXY(10, $y);
            $pdf->Cell(14, 2, $cuentaNumero, 0, 0, 'L');
            $pdf->Cell(14, 2, $cuentaDescripcion, 0, 0, 'L');
            
            // Guardar Y actual para posicionar Debe/Haber
            $yAuxiliar = $y;
            
            // ===== FILA 2: Glosa (multicell) =====
            $pdf->SetXY(20, $y + 3);
            $pdf->MultiCell(120, 2, $glosa, 0, 'L');
            $yGlosa = $pdf->GetY();
            
            // ===== FILA 3: Identificador =====
            $pdf->SetXY(20, $yGlosa + 1);
            $pdf->Cell(18, 2, $identificadorCI, 0, 0, 'L');
            $pdf->Cell(78, 2, $identificadorNombre, 0, 0, 'L');
            $pdf->Cell(4, 2, $deducible, 0, 0, 'R');
            
            // Y final después de glosa + identificador
            $yFinal = $pdf->GetY();
            
            // ===== TIPO DE CAMBIO (alineado con primera fila) =====
            $pdf->SetXY(105, $yAuxiliar);
            $pdf->Cell(14, 2, number_format($tipoCambio, 4), 0, 0, 'R');
            
            // ===== DEBE / HABER =====
            $xDebe = $pdf->GetX();
            $yDebe = $pdf->GetY();
            
            if ($d_h == 'D') {
                // DEBE
                $pdf->SetXY($xDebe + 40, $yDebe);
                $pdf->Cell(14, 2, number_format($montoBolivianos, 2), 0, 0, 'R');
                
                // Monto otra moneda (debajo)
                $xOtraMoneda = $pdf->GetX();
                $yOtraMoneda = $pdf->GetY();
                $pdf->SetXY($xOtraMoneda - 18, $yOtraMoneda + 4);
                $pdf->Cell(14, 2, number_format($montoOtraMoneda, 2), 0, 1, 'R');
                
                $totalDebe += $montoBolivianos;
            } else {
                // HABER
                $pdf->SetXY($xDebe + 65, $yDebe);
                $pdf->Cell(14, 2, number_format($montoBolivianos, 2), 0, 1, 'R');
                
                // Monto otra moneda
                $xOtraMoneda = $pdf->GetX();
                $yOtraMoneda = $pdf->GetY();
                $pdf->SetXY($xOtraMoneda - 44, $yOtraMoneda + 2);
                $pdf->Cell(14, 2, number_format($montoOtraMoneda, 2), 0, 1, 'R');
                
                $totalHaber += $montoBolivianos;
            }
            
            // ===== AVANZAR Y =====
            $y = $yFinal + 4;
            
            // ===== SALTO DE PÁGINA =====
            if ($largo > 0 && $y > 245) {
                // Pie de página con totales
                $pdf->SetFont('courier', '', 8);
                $pdf->SetXY(112, 262);
                $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
                $pdf->SetXY(115, 264);
                $pdf->Cell(15, 3, 'TOTALES EN BOLIVIANOS ', 0, 1, 'L');
                $pdf->SetXY(128, 264);
                $pdf->MultiCell(45, 3, number_format($totalDebe, 2, '.', ''), 0, 'R');
                $pdf->SetXY(153, 264);
                $pdf->MultiCell(45, 3, number_format($totalHaber, 2, '.', ''), 0, 'R');
                $pdf->SetXY(112, 266);
                $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
                $pdf->SetXY(112, 267);
                $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
                
                // Nueva página
                $pdf->AddPage();
                
                // Reimprimir encabezado
                $pdf->SetFont('Courier', '', 8);
                $pdf->SetXY(80, 5);
                $pdf->Cell(30, 5, '', 0, 1, 'C');
                
                $pdf->SetXY(6, 5);
                $pdf->Cell(10, 3, $nombreEmpresa, 0, 1, 'L');
                $pdf->SetXY(6, 8);
                $pdf->Cell(10, 3, $sucursalCabecera, 0, 1, 'L');
                $pdf->SetXY(6, 11);
                $pdf->Cell(18, 3, 'NUMERO DIARIO:', 0, 1, 'L');
                $pdf->SetXY(30, 11);
                $pdf->Cell(15, 3, $numeroDiario, 0, 1, 'L');
                $pdf->SetXY(6, 15);
                $pdf->Cell(15, 3, 'FECHA DIARIO:', 0, 1, 'L');
                $pdf->SetXY(30, 15);
                $pdf->Cell(15, 3, $fechaDiario, 0, 1, 'L');
                
                $pdf->SetXY(195, 5);
                $pdf->Cell(10, 3, 'Pg. ' . $pdf->PageNo(), 0, 0, 'L');
                
                $pdf->SetXY(170, 11);
                $pdf->Cell(15, 3, 'TIPO DIARIO:', 0, 1, 'C');
                $pdf->SetXY(190, 11);
                $pdf->Cell(15, 3, $tipoDiario, 0, 1, 'C');
                
                $pdf->SetXY(80, 11);
                $pdf->Cell(15, 3, 'Origen Diario:', 0, 1, 'C');
                $pdf->SetXY(100, 11);
                $pdf->Cell(15, 3, $operadorIngreso, 0, 1, 'C');
                
                $pdf->SetXY(5, 17);
                $pdf->Cell(15, 3, $lineaPunteada, 0, 1, 'L');
                $pdf->SetXY(8, 20);
                $pdf->Cell(15, 3, 'Cuenta', 0, 1, 'L');
                $pdf->SetXY(10, 22);
                $pdf->Cell(15, 3, 'Glosa', 0, 1, 'L');
                $pdf->SetXY(105, 22);
                $pdf->Cell(15, 3, 'Tipo Cambio', 0, 1, 'L');
                $pdf->SetXY(160, 22);
                $pdf->Cell(15, 3, 'Debe', 0, 1, 'L');
                $pdf->SetXY(190, 22);
                $pdf->Cell(15, 3, 'Haber', 0, 1, 'L');
                $pdf->SetXY(12, 24);
                $pdf->Cell(15, 3, 'Identificador', 0, 1, 'L');
                $pdf->SetXY(5, 26);
                $pdf->Cell(15, 3, $lineaPunteada, 0, 1, 'L');
                
                $y = 28; // Reiniciar Y
            }
        }
        
        // ==================== TOTALES FINALES ====================
        $YTotalesFinales = $y;
        
        $pdf->SetFont('courier', '', 8);
        $pdf->SetXY(112, $YTotalesFinales + 4);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
        $pdf->SetXY(115, $YTotalesFinales + 6);
        $pdf->Cell(15, 3, 'TOTALES EN BOLIVIANOS ', 0, 1, 'L');
        $pdf->SetXY(128, $YTotalesFinales + 6);
        $pdf->MultiCell(45, 3, number_format($totalDebe, 2, '.', ''), 0, 'R');
        $pdf->SetXY(153, $YTotalesFinales + 6);
        $pdf->MultiCell(45, 3, number_format($totalHaber, 2, '.', ''), 0, 'R');
        $pdf->SetXY(112, $YTotalesFinales + 8);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
        $pdf->SetXY(112, $YTotalesFinales + 9);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
        
        // ==================== FIRMAS ====================
        // Si las firmas no caben, nueva página
        if (($YTotalesFinales + 9) > 245) {
            $pdf->AddPage();
            $YTotalesFinales = 0;
        }
        
        // Nombre operador
        $nombreOperador = $operador->Nombre ?? '';
        $pdf->SetXY(40, $YTotalesFinales + 30);
        $pdf->Cell(15, 3, $nombreOperador, 0, 1, 'C');
        $pdf->SetXY(40, $YTotalesFinales + 33);
        $pdf->Cell(15, 3, 'Realizado', 0, 1, 'C');
        
        // Revisado
        if ($nombreRevisa) {
            $pdf->SetXY(100, $YTotalesFinales + 30);
            $pdf->Cell(15, 3, $nombreRevisa, 0, 1, 'C');
            $pdf->SetXY(100, $YTotalesFinales + 33);
            $pdf->Cell(15, 3, $cargoRevisa ?? 'Revisado', 0, 1, 'C');
            $pdf->SetXY(100, $YTotalesFinales + 36);
            $pdf->Cell(15, 3, 'Revisado', 0, 1, 'C');
        }
        
        // Aprobado
        if ($nombreAprueba) {
            $pdf->SetXY(160, $YTotalesFinales + 30);
            $pdf->Cell(15, 3, $nombreAprueba, 0, 1, 'C');
            $pdf->SetXY(160, $YTotalesFinales + 33);
            $pdf->Cell(15, 3, $cargoAprueba ?? 'Aprobado', 0, 1, 'C');
            $pdf->SetXY(160, $YTotalesFinales + 36);
            $pdf->Cell(15, 3, 'Aprobado', 0, 1, 'C');
        }
        
        // Salida
        $pdf->Output("diario_{$numeroDiario}.pdf", 'I');
        exit;
    }
}