<?php

namespace App\Http\Controllers\Gestion\Banco;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Banco\BancoPagoQR;
use App\Models\Gestion\Banco\BancoCredencial;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClientePago;
use App\Services\Gestion\Banco\BancoFactory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class HistorialPagosQRController extends Controller
{
    /**
     * Historial LOCAL + BANCO (con huérfanos detectados)
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
        $origen = $request->input('origen', 'TODOS');
        $incluirBanco = $request->input('incluir_banco', '1') === '1';

        // ============================================================
        // QUERY VENTAS (BancoPagoQR)
        // ============================================================
        $qrsVentas = collect();
        if (in_array($origen, ['TODOS', 'VENTA'])) {
            $queryVentas = BancoPagoQR::where('IdCliente', $idCliente)
                ->with(['credencial' => function ($q) {
                    $q->select('IdCredencial', 'Alias', 'CodigoBanco', 'Ambiente');
                }]);

            if ($fechaDesde) $queryVentas->whereDate('FechaCreacion', '>=', $fechaDesde);
            if ($fechaHasta) $queryVentas->whereDate('FechaCreacion', '<=', $fechaHasta);
            if ($estado) $queryVentas->where('Estado', $estado);
            if ($idCredencial) $queryVentas->where('IdCredencial', $idCredencial);
            if ($buscar) {
                $queryVentas->where(function ($q) use ($buscar) {
                    $q->where('QrId', 'LIKE', "%{$buscar}%")
                      ->orWhere('TransactionId', 'LIKE', "%{$buscar}%")
                      ->orWhere('Descripcion', 'LIKE', "%{$buscar}%");
                });
            }

            $qrsVentas = $queryVentas->get()->map(fn($qr) => $this->formatearQR($qr, 'VENTA'));
        }

        // ============================================================
        // QUERY PEDIDOS (PedidoClientePago)
        // ============================================================
        $qrsPedidos = collect();
        if (in_array($origen, ['TODOS', 'PEDIDO'])) {
            $queryPedidos = PedidoClientePago::where('IdCliente', $idCliente)
                ->with(['credencial' => function ($q) {
                    $q->select('IdCredencial', 'Alias', 'CodigoBanco', 'Ambiente');
                }]);

            if ($fechaDesde) $queryPedidos->whereDate('FechaCreacion', '>=', $fechaDesde);
            if ($fechaHasta) $queryPedidos->whereDate('FechaCreacion', '<=', $fechaHasta);
            if ($estado) $queryPedidos->where('Estado', $estado);
            if ($idCredencial) $queryPedidos->where('IdCredencial', $idCredencial);
            if ($buscar) {
                $queryPedidos->where(function ($q) use ($buscar) {
                    $q->where('QrId', 'LIKE', "%{$buscar}%")
                      ->orWhere('TransactionId', 'LIKE', "%{$buscar}%")
                      ->orWhere('Descripcion', 'LIKE', "%{$buscar}%");
                });
            }

            $qrsPedidos = $queryPedidos->get()->map(fn($qr) => $this->formatearQR($qr, 'PEDIDO'));
        }

        // ============================================================
        // 🔥 HUÉRFANOS: Pagos del banco que NO están en BD local
        // ============================================================
        $huerfanos = collect();
        if ($incluirBanco && in_array($origen, ['TODOS', 'BANCO'])) {
            try {
                $huerfanos = $this->detectarHuerfanos($idCliente, $fechaDesde, $idCredencial);
            } catch (\Exception $e) {
                Log::error('Error detectando huérfanos', ['error' => $e->getMessage()]);
            }
        }

        // ============================================================
        // UNIFICAR Y ORDENAR
        // ============================================================
        $todos = $qrsVentas
            ->concat($qrsPedidos)
            ->concat($huerfanos)
            ->sortByDesc('FechaCreacion')
            ->values();

        // ============================================================
        // PAGINACIÓN MANUAL
        // ============================================================
        $perPage = 50;
        $page = (int) $request->input('page', 1);
        $total = $todos->count();
        $items = $todos->slice(($page - 1) * $perPage, $perPage)->values();

        $qrs = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // ============================================================
        // ESTADÍSTICAS
        // ============================================================
        $stats = [
            'total' => $total,
            'activos' => $todos->where('Estado', 'ACTIVO')->count(),
            'pagados' => $todos->where('Estado', 'PAGADO')->count(),
            'anulados' => $todos->where('Estado', 'ANULADO')->count(),
            'expirados' => $todos->where('Estado', 'EXPIRADO')->count(),
            'monto_total' => (float) $todos->where('Estado', 'PAGADO')->sum('MontoPagado'),
            'huerfanos' => $huerfanos->count(),
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
                'CodigoBanco' => $c->CodigoBanco,
                'Ambiente' => $c->Ambiente,
            ]);

        return Inertia::render('Gestion/Banco/HistorialPagosQR/Index', [
            'qrs' => $qrs,
            'stats' => $stats,
            'credenciales' => $credenciales,
            'filtros' => [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'estado' => $estado,
                'buscar' => $buscar,
                'id_credencial' => $idCredencial,
                'origen' => $origen,
                'incluir_banco' => $incluirBanco,
            ],
            'estadosDisponibles' => [
                ['valor' => 'ACTIVO', 'nombre' => 'Activo'],
                ['valor' => 'PAGADO', 'nombre' => 'Pagado'],
                ['valor' => 'ANULADO', 'nombre' => 'Anulado'],
                ['valor' => 'EXPIRADO', 'nombre' => 'Expirado'],
                ['valor' => 'ERROR', 'nombre' => 'Error'],
            ],
            'origenesDisponibles' => [
                ['valor' => 'TODOS', 'nombre' => 'Todos'],
                ['valor' => 'VENTA', 'nombre' => 'Ventas'],
                ['valor' => 'PEDIDO', 'nombre' => 'Pedidos'],
                ['valor' => 'BANCO', 'nombre' => 'Solo huérfanos ⚠️'],
            ],
        ]);
    }

    /**
     * Detecta pagos del banco que NO están en tu BD local
     */
    private function detectarHuerfanos(int $idCliente, string $fechaDesde, ?int $idCredencial = null): \Illuminate\Support\Collection
    {
        $huerfanos = collect();

        $credenciales = BancoCredencial::porCliente($idCliente)
            ->where('ActivoInactivo', 1)
            ->when($idCredencial, fn($q) => $q->where('IdCredencial', $idCredencial))
            ->get();

        foreach ($credenciales as $credencial) {
            try {
                $service = BancoFactory::desdeCredencial($credencial);
                $pagosBanco = $service->listarQRPagados($fechaDesde);

                foreach ($pagosBanco as $pagoBanco) {
                    $qrId = $pagoBanco['qrId'] ?? null;
                    if (!$qrId) continue;

                    // Verificar si existe en alguna de las dos tablas
                    $existeVenta = BancoPagoQR::where('QrId', $qrId)->exists();
                    $existePedido = PedidoClientePago::where('QrId', $qrId)->exists();

                    if (!$existeVenta && !$existePedido) {
                        // Es huérfano
                        $huerfanos->push([
                            'IdPagosQr' => 'BANCO-' . $qrId,
                            'Origen' => 'BANCO',
                            'QrId' => $qrId,
                            'TransactionId' => $pagoBanco['transactionId'] ?? null,
                            'Monto' => (float) ($pagoBanco['amount'] ?? 0),
                            'MontoPagado' => (float) ($pagoBanco['amount'] ?? 0),
                            'Moneda' => $pagoBanco['currency'] ?? 'BOB',
                            'Descripcion' => $pagoBanco['description'] ?? 'Pago del banco',
                            'Estado' => 'PAGADO',
                            'FechaCreacion' => $pagoBanco['paymentDate'] ?? null,
                            'FechaPago' => $pagoBanco['paymentDate'] ?? null,
                            'FechaAnulacion' => null,
                            'Credencial' => [
                                'IdCredencial' => $credencial->IdCredencial,
                                'Alias' => $credencial->Alias ?? $credencial->CodigoBanco,
                                'CodigoBanco' => $credencial->CodigoBanco,
                                'Ambiente' => $credencial->Ambiente,
                            ],
                            'DatosPago' => $pagoBanco,
                            'EsHuerfano' => true,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error consultando banco para huérfanos', [
                    'IdCredencial' => $credencial->IdCredencial,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $huerfanos;
    }

    /**
     * Formatear un QR para el frontend
     */
    private function formatearQR($qr, string $origen): array
    {
        return [
            'IdPagosQr' => $qr->IdPagosQr ?? $qr->IdPagoPedido ?? null,
            'Origen' => $origen,
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
                'CodigoBanco' => $qr->credencial->CodigoBanco,
                'Ambiente' => $qr->credencial->Ambiente,
            ] : null,
            'DatosPago' => $qr->DatosPago,
            'EsHuerfano' => false,
        ];
    }

    /**
     * Consultar el historial DEL BANCO (en vivo)
     */
    public function consultarBanco(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'id_credencial' => 'required|integer|exists:impuestos_banco_credenciales,IdCredencial',
        ]);

        try {
            $idCliente = session('cliente_id');

            $credencial = BancoCredencial::porCliente($idCliente)
                ->findOrFail($request->id_credencial);

            $service = BancoFactory::desdeCredencial($credencial);
            $pagos = $service->listarQRPagados($request->fecha);

            return response()->json([
                'success' => true,
                'pagos' => $pagos,
                'total' => count($pagos),
                'fecha' => $request->fecha,
                'banco' => $credencial->CodigoBanco,
            ]);

        } catch (\Exception $e) {
            Log::error('Error consultando historial del banco', [
                'error' => $e->getMessage(),
                'fecha' => $request->fecha,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el banco: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Conciliar: comparar historial local vs banco
     */
    public function conciliar(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'id_credencial' => 'required|integer|exists:impuestos_banco_credenciales,IdCredencial',
        ]);

        try {
            $idCliente = session('cliente_id');
            $credencial = BancoCredencial::porCliente($idCliente)->findOrFail($request->id_credencial);

            $service = BancoFactory::desdeCredencial($credencial);
            $pagosBanco = $service->listarQRPagados($request->fecha);

            $qrsVentas = BancoPagoQR::where('IdCliente', $idCliente)
                ->where('IdCredencial', $credencial->IdCredencial)
                ->whereDate('FechaCreacion', $request->fecha)
                ->get()
                ->keyBy('QrId');

            $qrsPedidos = PedidoClientePago::where('IdCliente', $idCliente)
                ->where('IdCredencial', $credencial->IdCredencial)
                ->whereDate('FechaCreacion', $request->fecha)
                ->get()
                ->keyBy('QrId');

            $conciliados = 0;
            $noEncontradosLocal = 0;
            $noMarcadosPagados = 0;
            $diferencias = [];

            foreach ($pagosBanco as $pagoBanco) {
                $qrId = $pagoBanco['qrId'] ?? null;
                if (!$qrId) continue;

                $pagoLocal = $qrsVentas->get($qrId) ?? $qrsPedidos->get($qrId);

                if (!$pagoLocal) {
                    $noEncontradosLocal++;
                    $diferencias[] = [
                        'tipo' => 'NO_ENCONTRADO_LOCAL',
                        'qrId' => $qrId,
                        'monto' => $pagoBanco['amount'] ?? null,
                        'fecha' => $pagoBanco['paymentDate'] ?? null,
                    ];
                    continue;
                }

                if ($pagoLocal->Estado === 'PAGADO' && $pagoLocal->Conciliado) {
                    $conciliados++;
                    continue;
                }

                if ($pagoLocal->Estado !== 'PAGADO') {
                    $pagoLocal->update([
                        'Estado' => 'PAGADO',
                        'MontoPagado' => $pagoBanco['amount'] ?? $pagoLocal->Monto,
                        'FechaPago' => $pagoBanco['paymentDate'] ?? now(),
                        'DatosPago' => $pagoBanco,
                        'Conciliado' => 1,
                        'FechaConciliacion' => now(),
                        'StatusQRCodeBanco' => 1,
                    ]);
                    $noMarcadosPagados++;
                } else {
                    $pagoLocal->update([
                        'Conciliado' => 1,
                        'FechaConciliacion' => now(),
                    ]);
                    $conciliados++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Conciliación completada",
                'resumen' => [
                    'pagos_banco' => count($pagosBanco),
                    'conciliados' => $conciliados,
                    'no_encontrados_local' => $noEncontradosLocal,
                    'marcados_pagados' => $noMarcadosPagados,
                ],
                'diferencias' => $diferencias,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en conciliación', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al conciliar: ' . $e->getMessage(),
            ], 500);
        }
    }
}