<?php

namespace App\Console\Commands;

use App\Models\Gestion\Banco\BancoCredencial;
use App\Models\Gestion\Banco\BancoPagoQR;
use App\Services\Gestion\Banco\BancoFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConciliarQRsCommand extends Command
{
    protected $signature = 'banco:conciliar-qrs {fecha?}';
    protected $description = 'Concilia los QRs pagados con el banco (por defecto ayer)';

    public function handle(): int
    {
        $fecha = $this->argument('fecha') ?? Carbon::yesterday()->format('Y-m-d');

        $this->info("🔍 Iniciando conciliación de QRs para: {$fecha}");

        $credenciales = BancoCredencial::where('ActivoInactivo', 1)->get();

        if ($credenciales->isEmpty()) {
            $this->warn('⚠️ No hay credenciales activas.');
            return Command::SUCCESS;
        }

        $totalConciliados = 0;
        $totalNoEncontrados = 0;
        $totalMarcados = 0;

        foreach ($credenciales as $credencial) {
            $this->info("\n🏦 Procesando: {$credencial->CodigoBanco} - {$credencial->Usuario}");

            try {
                $service = BancoFactory::desdeCredencial($credencial);
                $pagosBanco = $service->listarQRPagados($fecha);

                $this->info("   Pagos reportados por el banco: " . count($pagosBanco));

                if (empty($pagosBanco)) {
                    continue;
                }

                $qrsLocal = BancoPagoQR::where('IdCliente', $credencial->IdCliente)
                    ->where('IdCredencial', $credencial->IdCredencial)
                    ->whereDate('FechaCreacion', $fecha)
                    ->get()
                    ->keyBy('QrId');

                foreach ($pagosBanco as $pagoBanco) {
                    $qrId = $pagoBanco['qrId'] ?? null;
                    if (!$qrId) continue;

                    $pagoLocal = $qrsLocal->get($qrId);

                    if (!$pagoLocal) {
                        $totalNoEncontrados++;
                        Log::warning('QR del banco no encontrado localmente', [
                            'qrId' => $qrId,
                            'monto' => $pagoBanco['amount'] ?? null,
                            'IdCredencial' => $credencial->IdCredencial,
                        ]);
                        continue;
                    }

                    if ($pagoLocal->Estado === 'PAGADO' && $pagoLocal->Conciliado) {
                        $totalConciliados++;
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
                        ]);
                        $totalMarcados++;
                    } else {
                        $pagoLocal->update([
                            'Conciliado' => 1,
                            'FechaConciliacion' => now(),
                        ]);
                        $totalConciliados++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error: " . $e->getMessage());
                Log::error('Error conciliando credencial', [
                    'IdCredencial' => $credencial->IdCredencial,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('📊 RESUMEN DE CONCILIACIÓN');
        $this->info('═══════════════════════════════════════');
        $this->info("✅ Conciliados:       {$totalConciliados}");
        $this->info("🔄 Marcados pagados:  {$totalMarcados}");
        $this->warn("⚠️ No encontrados:    {$totalNoEncontrados}");
        $this->info('═══════════════════════════════════════');

        return Command::SUCCESS;
    }
}