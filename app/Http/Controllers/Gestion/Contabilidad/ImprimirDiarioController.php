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
        $pdf->SetMargins(14, 20, 60);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 8);
        
        // Encabezado
        $pdf->SetXY(6, 5);
        $pdf->Cell(10, 3, $empresa->Nombre ?? '', 0, 1, 'L');
        
        $pdf->SetXY(6, 8);
        $pdf->Cell(10, 3, $diario->sucursal->Nombre ?? '', 0, 1, 'L');
        
        $pdf->SetXY(195, 5);
        $pdf->Cell(10, 3, 'Pg. ' . $pdf->PageNo(), 0, 0, 'L');
        
        $pdf->SetXY(6, 11);
        $pdf->Cell(18, 3, 'NUMERO DIARIO:', 0, 1, 'L');
        $pdf->SetXY(30, 11);
        $pdf->Cell(15, 3, $diario->NumeroDiario, 0, 1, 'L');
        
        $pdf->SetXY(6, 15);
        $pdf->Cell(15, 3, 'FECHA DIARIO:', 0, 1, 'L');
        $pdf->SetXY(30, 15);
        $pdf->Cell(15, 3, date('d/m/Y', strtotime($diario->fecha->Fecha ?? '')), 0, 1, 'L');
        
        $pdf->SetXY(170, 11);
        $pdf->Cell(15, 3, 'TIPO DIARIO:', 0, 1, 'C');
        $pdf->SetXY(190, 11);
        $pdf->Cell(15, 3, $diario->tipoDiario->TipoDiario ?? '', 0, 1, 'C');
        
        $pdf->SetXY(80, 11);
        $pdf->Cell(15, 3, 'Origen Diario:', 0, 1, 'C');
        $pdf->SetXY(100, 11);
        $pdf->Cell(15, 3, $operador->Iniciales ?? ($operador->Nombre ?? ''), 0, 1, 'C');
        
        // Líneas de cabecera
        $pdf->SetXY(5, 17);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');
        
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
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');
        
        // Asientos - con espacio Y+2 en lugar de Y+1
        $y = 30; // Empezar un poco más abajo
        foreach ($diario->asientos as $asiento) {
            // Verificar espacio en página
            if ($y > 245) {
                $pdf->AddPage();
                $y = 28;
                // Reimprimir cabecera en nueva página
                $pdf->SetXY(5, 17);
                $pdf->Cell(15, 3, '----------------------------------------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');
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
                $pdf->Cell(15, 3, '----------------------------------------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');
                $y = 30;
            }
            
            // Cuenta (línea 1)
            $pdf->SetXY(8, $y);
            $pdf->Cell(14, 3, $asiento->cuenta->Cuenta ?? '', 0, 0, 'L');
            $pdf->Cell(14, 3, $asiento->cuenta->Descripcion ?? '', 0, 0, 'L');
            
            // Tipo Cambio
            $pdf->SetXY(105, $y);
            $pdf->Cell(14, 3, number_format($asiento->TipoCambio, 4, ',', '.'), 0, 0, 'R');
            
            // Debe o Haber
            if ($asiento->D_H == 'D') {
                $pdf->SetXY(160, $y);
                $pdf->Cell(14, 3, number_format($asiento->MontoBolivianos, 2, ',', '.'), 0, 0, 'R');
            } else {
                $pdf->SetXY(190, $y);
                $pdf->Cell(14, 3, number_format($asiento->MontoBolivianos, 2, ',', '.'), 0, 0, 'R');
            }
            
            // Glosa (línea 2 - más abajo)
            $y += 4;
            $pdf->SetXY(20, $y);
            $pdf->MultiCell(80, 3, $asiento->Glosa, 0, 'L');
            
            // Identificador (línea 3)
            $y = $pdf->GetY() + 2;
            $pdf->SetXY(20, $y);
            $pdf->Cell(18, 3, $asiento->identificador->CI_NIT ?? '', 0, 0, 'L');
            $pdf->Cell(78, 3, $asiento->identificador->Nombre ?? '', 0, 0, 'L');
            
            // Espacio entre asientos (Y+2)
            $y = $pdf->GetY() + 5;
        }
        
        // Totales
        $pdf->SetXY(112, $y + 6);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
        
        $pdf->SetXY(115, $y + 8);
        $pdf->Cell(15, 3, 'TOTALES EN BOLIVIANOS ', 0, 1, 'L');
        $pdf->SetXY(128, $y + 8);
        $pdf->MultiCell(45, 3, number_format($totalDebe, 2, ',', '.'), 0, 'R');
        $pdf->SetXY(153, $y + 8);
        $pdf->MultiCell(45, 3, number_format($totalHaber, 2, ',', '.'), 0, 'R');
        
        $pdf->SetXY(112, $y + 10);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
        $pdf->SetXY(112, $y + 11);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
        
        // Firmas
        $yFinal = $y + 11;
        
        // Realizado por (operador)
        $pdf->SetXY(40, $yFinal + 30);
        $pdf->Cell(15, 3, $operador->Nombre ?? '', 0, 1, 'C');
        $pdf->SetXY(40, $yFinal + 33);
        $pdf->Cell(15, 3, 'Realizado', 0, 1, 'C');
        
        // Revisado por
        if ($nombreRevisa) {
            $pdf->SetXY(100, $yFinal + 30);
            $pdf->Cell(15, 3, $nombreRevisa, 0, 1, 'C');
            $pdf->SetXY(100, $yFinal + 33);
            $pdf->Cell(15, 3, $cargoRevisa ?? 'Revisado', 0, 1, 'C');
            $pdf->SetXY(100, $yFinal + 36);
            $pdf->Cell(15, 3, 'Revisado', 0, 1, 'C');
        }
        
        // Aprobado por
        if ($nombreAprueba) {
            $pdf->SetXY(160, $yFinal + 30);
            $pdf->Cell(15, 3, $nombreAprueba, 0, 1, 'C');
            $pdf->SetXY(160, $yFinal + 33);
            $pdf->Cell(15, 3, $cargoAprueba ?? 'Aprobado', 0, 1, 'C');
            $pdf->SetXY(160, $yFinal + 36);
            $pdf->Cell(15, 3, 'Aprobado', 0, 1, 'C');
        }
        
        $pdf->Output("diario_{$diario->NumeroDiario}.pdf", 'I');
        exit;
    }
}