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
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $tipoOperador = session('operador_tipo_id');
        
        $esSupervisor = in_array($tipoOperador, [1, 2, 11]);
        
        // Obtener sucursales (para supervisores)
        $sucursales = [];
        if ($esSupervisor) {
            $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
                ->orderBy('Nombre')
                ->get(['IdClienteSucursal as id', 'Nombre as nombre']);
        }
        
        // Obtener operadores (para supervisores)
        $operadores = [];
        if ($esSupervisor) {
            $operadores = Operador::whereHas('empresas', function($q) use ($clienteId) {
                    $q->where('todos_cliente.IdCliente', $clienteId);
                })
                ->with('identificador')
                ->get()
                ->map(fn($op) => [
                    'id' => $op->IdOperador,
                    'nombre' => $op->identificador?->Nombre ?? 'Sin nombre',
                ]);
        }
        
        return Inertia::render('Gestion/Contabilidad/ImprimirDiario/Index', [
            'sucursales' => $sucursales,
            'operadores' => $operadores,
            'sucursalId' => $sucursalId,
            'operadorId' => $operadorId,
            'esSupervisor' => $esSupervisor,
        ]);
    }

    /**
     * Buscar diarios por número (autocompletado)
     */
    public function buscar(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|min:1'
        ]);
        
        $q = $request->get('q', '');
        
        // Si no hay término de búsqueda, devolver vacío
        if (empty($q)) {
            return response()->json([
                'success' => true,
                'diarios' => []
            ]);
        }
        
        $clienteId = session('cliente_id');
        $sucursalId = $request->sucursal_id ?? session('cliente_sucursal_id');
        $operadorId = $request->operador_id;
        $tipoOperador = session('operador_tipo_id');
        $esSupervisor = in_array($tipoOperador, [1, 2, 11]);
        
        $query = Diario::porContexto()
            ->with('tipoDiario')
            ->where('Contabilizado', 1)
            ->where('NumeroDiario', '>', 0);
        
        // Buscar por número (como string para que funcione con LIKE)
        $query->where('NumeroDiario', 'LIKE', $q . '%');
        
        // Filtrar por sucursal (solo supervisores)
        if ($esSupervisor && $sucursalId) {
            $query->where('IdSucursal', $sucursalId);
        }
        
        // Filtrar por operador
        if ($operadorId) {
            $query->where('IdOperadorIngreso', $operadorId);
        } elseif (!$esSupervisor) {
            $query->where('IdOperadorIngreso', session('operador_id'));
        }
        
        $diarios = $query
            ->orderBy('NumeroDiario')
            ->limit(10)
            ->get(['IdDiario', 'NumeroDiario', 'IdTipoDiario', 'IdFecha']);
        
        // Formatear resultado
        $resultados = $diarios->map(function($diario) {
            return [
                'id' => $diario->IdDiario,
                'numero' => $diario->NumeroDiario,
                'tipo' => $diario->tipoDiario->TipoDiario ?? 'Diario',
                'fecha' => $diario->fecha ? date('d/m/Y', strtotime($diario->fecha->Fecha)) : null,
            ];
        });
        
        return response()->json([
            'success' => true,
            'diarios' => $resultados
        ]);
    }
    
    /**
     * Obtener operadores por sucursal (AJAX)
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
        $diario = Diario::porContexto()
            ->with(['asientos.cuenta', 'asientos.identificador', 'fecha', 'sucursal', 'tipoDiario'])
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
        
        // Calcular totales
        $totalDebe = $diario->asientos->where('D_H', 'D')->sum('MontoBolivianos');
        $totalHaber = $diario->asientos->where('D_H', 'H')->sum('MontoBolivianos');
        
        // Crear PDF
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 15, 10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 8);
        
        // ==================== ENCABEZADO ====================
        $y = 10;
        
        // Empresa
        $pdf->SetXY(10, $y);
        $pdf->SetFont('courier', 'B', 10);
        $pdf->Cell(0, 4, $empresa->Nombre ?? '', 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(10, $y-1);
        $pdf->SetFont('courier', '', 9);
        $pdf->Cell(0, 4, $diario->sucursal->Nombre ?? '', 0, 1, 'L');
        
        // Número de página
        $pdf->SetXY(170, 10);
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(30, 4, 'Pag. ' . $pdf->PageNo(), 0, 0, 'R');
        
        // Número y fecha de diario
        $y = $pdf->GetY() + 6;
        $pdf->SetXY(10, $y);
        $pdf->SetFont('courier', 'B', 8);
        $pdf->Cell(25, 5, 'NUMERO DIARIO:', 0, 0, 'L');
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(30, 5, $diario->NumeroDiario, 0, 0, 'L');
        
        $pdf->SetXY(120, $y);
        $pdf->SetFont('courier', 'B', 8);
        $pdf->Cell(20, 4, 'TIPO DIARIO: ', 0, 0, 'L');
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(40, 4, $diario->tipoDiario->TipoDiario ?? '', 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(10, $y);
        $pdf->SetFont('courier', 'B', 8);
        $pdf->Cell(25, 4, 'FECHA DIARIO:', 0, 0, 'L');
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(30, 4, date('d/m/Y', strtotime($diario->fecha->Fecha ?? '')), 0, 0, 'L');
        
        $pdf->SetXY(120, $y);
        $pdf->SetFont('courier', 'B', 8);
        $pdf->Cell(20, 4, 'ORIGEN:', 0, 0, 'L');
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(40, 4, $operador->Iniciales ?? ($operador->Nombre ?? ''), 0, 1, 'L');
        
        // ==================== CABECERA DE TABLA ====================
        $y = $pdf->GetY() + 8;
        
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(10, $y, 200, $y);
        
        //$y += 1;
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetXY(10, $y);
        $pdf->Cell(35, 5, 'Cuenta', 0, 0, 'L');
        $pdf->SetXY(47, $y);
        $pdf->Cell(70, 5, 'Glosa', 0, 0, 'L');
        $pdf->SetXY(119, $y);
        $pdf->Cell(20, 5, 'Tipo Cambio', 0, 0, 'R');
        $pdf->SetXY(145, $y);
        $pdf->Cell(25, 5, 'Debe (Bs.)', 0, 0, 'R');
        $pdf->SetXY(175, $y);
        $pdf->Cell(25, 5, 'Haber (Bs.)', 0, 1, 'R');
        
        $y += 5;
        $pdf->SetXY(10, $y);
        $pdf->SetFont('courier', 'B', 8);
        $pdf->Cell(35, 2, 'Identificador', 0, 1, 'L');
        
        $y += 3;
        $pdf->Line(10, $y, 200, $y);
        $y += 4;
        
        // ==================== ASIENTOS ====================
        $pdf->SetFont('courier', '', 8);

        foreach ($diario->asientos as $asiento) {
            // Verificar espacio en página
            if ($y > 250) {
                $pdf->AddPage();
                $y = 20;
                
                // Reimprimir cabecera
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->Line(10, $y, 200, $y);
                $y += 3;
                $pdf->SetFont('courier', 'B', 8);
                $pdf->SetXY(10, $y);
                $pdf->Cell(35, 5, 'Cuenta', 0, 0, 'L');
                $pdf->SetXY(25, $y);  // ← GLOSA más a la izquierda
                $pdf->Cell(80, 5, 'Glosa', 0, 0, 'L');
                $pdf->SetXY(119, $y);
                $pdf->Cell(20, 5, 'Tipo Cambio', 0, 0, 'R');
                $pdf->SetXY(145, $y);
                $pdf->Cell(25, 5, 'Debe (Bs.)', 0, 0, 'R');
                $pdf->SetXY(175, $y);
                $pdf->Cell(25, 5, 'Haber (Bs.)', 0, 1, 'R');
                $y += 5;
                $pdf->SetXY(10, $y);
                $pdf->SetFont('courier', 'B', 8);
                $pdf->Cell(35, 5, 'Identificador', 0, 1, 'L');
                $y += 3;
                $pdf->Line(10, $y, 200, $y);
                $y += 4;
                $pdf->SetFont('courier', '', 8);
            }
            
            // Calcular altura necesaria para glosa e identificador
            $glosa = $asiento->Glosa ?? '';
            $identificador = ($asiento->identificador->CI_NIT ?? '') . ' - ' . ($asiento->identificador->Nombre ?? '');
            
            // Altura de glosa (MultiCell) - ANCHO REDUCIDO a 55mm para no chocar con Tipo Cambio
            $glosaHeight = $pdf->getStringHeight(55, $glosa);
            $identHeight = $pdf->getStringHeight(150, $identificador);
            $maxExtraHeight = max($glosaHeight, $identHeight);
            
            // Guardar posición Y actual
            $currentY = $y;
            
            // ===== LÍNEA 1: Cuenta, Tipo Cambio, Debe/Haber =====
            $cuentaStr = ($asiento->cuenta->Cuenta ?? '') . ' - ' . ($asiento->cuenta->Descripcion ?? '');
            
            $pdf->SetXY(10, $y);
            $pdf->Cell(35, 4, $cuentaStr, 0, 0, 'L');
            
            $pdf->SetXY(119, $y);
            $pdf->Cell(20, 4, number_format($asiento->TipoCambio, 4, ',', '.'), 0, 0, 'R');
            
            if ($asiento->D_H == 'D') {
                $pdf->SetXY(145, $y);
                $pdf->Cell(25, 4, number_format($asiento->MontoBolivianos, 2, ',', '.'), 0, 0, 'R');
                $pdf->SetXY(175, $y);
                $pdf->Cell(25, 4, '', 0, 0, 'R');
            } else {
                $pdf->SetXY(145, $y);
                $pdf->Cell(25, 4, '', 0, 0, 'R');
                $pdf->SetXY(175, $y);
                $pdf->Cell(25, 4, number_format($asiento->MontoBolivianos, 2, ',', '.'), 0, 0, 'R');
            }
            
            // ===== LÍNEA 2: Glosa (MultiLine) - AHORA EN X=25, ancho 55mm =====
            $y += 5;
            $pdf->SetXY(25, $y);  // ← MOVIDO DE 47 a 25 (más a la izquierda)
            $pdf->MultiCell(55, 4, $glosa, 0, 'L');  // ← Ancho reducido de 70 a 55
            $newY = $pdf->GetY();
            
            // ===== LÍNEA 3: Identificador (MultiLine) - AHORA EN X=25 =====
            $pdf->SetXY(25, $newY);  // ← MOVIDO DE 47 a 25
            $pdf->MultiCell(150, 4, $identificador, 0, 'L');
            $newY2 = $pdf->GetY();
            
            // Avanzar Y según la línea más alta
            $y = max($newY, $newY2) + 2;
            
            // Línea separadora entre asientos
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Line(10, $y, 200, $y);
            $y += 3;
        }
        
        // ==================== TOTALES ====================
        $y += 6;
        
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(10, $y, 200, $y);
        $y += 4;
        
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetXY(110, $y);
        $pdf->Cell(35, 5, 'TOTALES EN BOLIVIANOS:', 0, 0, 'R');
        $pdf->SetXY(145, $y);
        $pdf->Cell(25, 5, number_format($totalDebe, 2, ',', '.'), 0, 0, 'R');
        $pdf->SetXY(175, $y);
        $pdf->Cell(25, 5, number_format($totalHaber, 2, ',', '.'), 0, 1, 'R');
        
        $y += 6;
        $pdf->Line(10, $y, 200, $y);
        $y += 4;
        $pdf->Line(10, $y, 200, $y);
        
        // ==================== FIRMAS ====================
        $y = $y + 25;
        
        $pdf->SetFont('courier', '', 8);
        
        // Realizado por
        $pdf->SetXY(25, $y);
        $pdf->Cell(60, 4, $operador->Nombre ?? '', 0, 1, 'C');
        $pdf->SetXY(25, $y + 5);
        $pdf->Cell(60, 4, 'Realizado', 0, 1, 'C');
        
        // Revisado por
        if ($nombreRevisa) {
            $pdf->SetXY(95, $y);
            $pdf->Cell(60, 4, $nombreRevisa, 0, 1, 'C');
            $pdf->SetXY(95, $y + 5);
            $pdf->Cell(60, 4, $cargoRevisa ?? 'Revisado', 0, 1, 'C');
            $pdf->SetXY(95, $y + 10);
            $pdf->Cell(60, 4, 'Revisado', 0, 1, 'C');
        }
        
        // Aprobado por
        if ($nombreAprueba) {
            $pdf->SetXY(165, $y);
            $pdf->Cell(40, 4, $nombreAprueba, 0, 1, 'C');
            $pdf->SetXY(165, $y + 5);
            $pdf->Cell(40, 4, $cargoAprueba ?? 'Aprobado', 0, 1, 'C');
            $pdf->SetXY(165, $y + 10);
            $pdf->Cell(40, 4, 'Aprobado', 0, 1, 'C');
        }
        
        $pdf->Output("diario_{$diario->NumeroDiario}.pdf", 'I');
        exit;
    }
}