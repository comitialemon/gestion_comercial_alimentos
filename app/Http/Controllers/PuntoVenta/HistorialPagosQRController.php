<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\BancoPagoQR;
use App\Models\Gestion\Impuestos\BancoCredencial;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class HistorialPagosQRController extends Controller
{
    /**
     * Historial de QRs generados
     */
    public function index(Request $request)
    {
        $idCliente = session('cliente_id');

        // ============================================================
        // FILTROS
        // ============================================================
        $fechaDesde = $request->input('fecha_desde', Carbon::now()->subDays(7)->format('Y-m-d'));
        $fechaHasta = $request->input('fecha_hasta', Carbon::now()->format('Y-m-d'));
        $estado = $request->input('estado');
        $buscar = $request->input('buscar');
        $idCredencial = $request->input('id_credencial');

        // ============================================================
        // QUERY BASE
        // ============================================================
        $query = BancoPagoQR::where('IdCliente', $idCliente)
            ->with(['credencial' => function ($q) {
                $q->select('IdCredencial', 'Alias', 'CodigoBanco', 'Ambiente');
            }]);

        if ($fechaDesde) {
            $query->whereDate('FechaCreacion', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->whereDate('FechaCreacion', '<=', $fechaHasta);
        }
        if ($estado) {
            $query->where('Estado', $estado);
        }
        if ($idCredencial) {
            $query->where('IdCredencial', $idCredencial);
        }
        if ($buscar) {
            $query->where(function ($q) use ($buscar) {
                $q->where('QrId', 'LIKE', "%{$buscar}%")
                  ->orWhere('TransactionId', 'LIKE', "%{$buscar}%")
                  ->orWhere('Descripcion', 'LIKE', "%{$buscar}%");
            });
        }

        // ============================================================
        // PAGINACIÓN
        // ============================================================
        $qrs = $query->orderBy('IdPagosQr', 'DESC')
            ->paginate(50)
            ->through(function ($qr) {
                return [
                    'IdPagosQr' => $qr->IdPagosQr,
                    'QrId' => $qr->QrId,
                    'TransactionId' => $qr->TransactionId,
                    'Monto' => (float) $qr->Monto,
                    'MontoPagado' => $qr->MontoPagado ? (float) $qr->MontoPagado : null,
                    'Moneda' => $qr->Moneda,
                    'Descripcion' => $qr->Descripcion,
                    'Estado' => $qr->Estado,
                    'FechaCreacion' => $qr->FechaCreacion?->format('Y-m-d H:i:s'),
                    'FechaPago' => $qr->FechaPago?->format('Y-m-d H:i:s'),
                    'FechaAnulacion' => $qr->FechaAnulacion?->format('Y-m-d H:i:s'),
                    'Credencial' => $qr->credencial ? [
                        'IdCredencial' => $qr->credencial->IdCredencial,
                        'Alias' => $qr->credencial->Alias ?? $qr->credencial->CodigoBanco,
                        'Ambiente' => $qr->credencial->Ambiente,
                    ] : null,
                    'DatosPago' => $qr->DatosPago,
                ];
            });

        // ============================================================
        // ESTADÍSTICAS
        // ============================================================
        $statsQuery = BancoPagoQR::where('IdCliente', $idCliente)
            ->whereDate('FechaCreacion', '>=', $fechaDesde)
            ->whereDate('FechaCreacion', '<=', $fechaHasta);

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'activos' => (clone $statsQuery)->where('Estado', 'ACTIVO')->count(),
            'pagados' => (clone $statsQuery)->where('Estado', 'PAGADO')->count(),
            'anulados' => (clone $statsQuery)->where('Estado', 'ANULADO')->count(),
            'expirados' => (clone $statsQuery)->where('Estado', 'EXPIRADO')->count(),
            'monto_total' => (float) (clone $statsQuery)->where('Estado', 'PAGADO')->sum('MontoPagado'),
        ];

        // ============================================================
        // CREDENCIALES DISPONIBLES
        // ============================================================
        $credenciales = BancoCredencial::porCliente($idCliente)
            ->orderBy('CodigoBanco')
            ->get()
            ->map(fn($c) => [
                'IdCredencial' => $c->IdCredencial,
                'Alias' => $c->Alias ?? $c->CodigoBanco,
                'Ambiente' => $c->Ambiente,
            ]);

        return Inertia::render('PuntoVenta/HistorialPagosQR/Index', [
            'qrs' => $qrs,
            'stats' => $stats,
            'credenciales' => $credenciales,
            'filtros' => [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'estado' => $estado,
                'buscar' => $buscar,
                'id_credencial' => $idCredencial,
            ],
            'estadosDisponibles' => [
                ['valor' => 'ACTIVO', 'nombre' => 'Activo'],
                ['valor' => 'PAGADO', 'nombre' => 'Pagado'],
                ['valor' => 'ANULADO', 'nombre' => 'Anulado'],
                ['valor' => 'EXPIRADO', 'nombre' => 'Expirado'],
                ['valor' => 'ERROR', 'nombre' => 'Error'],
            ],
        ]);
    }
}