<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente\BilleteraOperador;
use Illuminate\Support\Facades\DB;

class ReconciliarBilleteras extends Command
{
    protected $signature = 'billetera:reconciliar';
    protected $description = 'Verifica que el SaldoActual coincida con la suma de movimientos';

    public function handle()
    {
        $this->info('Iniciando reconciliación de billeteras...');

        $billeteras = BilleteraOperador::all();
        $errores = 0;

        foreach ($billeteras as $b) {
            $sumaMovimientos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_operadores_billeteras_movimientos')
                ->where('IdBilletera', $b->IdBilletera)
                ->sum('Monto');

            $sumaReal = round((float) $sumaMovimientos, 3);
            $saldoApp = round((float) $b->SaldoActual, 3);

            if ($sumaReal !== $saldoApp) {
                $errores++;
                $this->error("❌ Descuadre en Billetera ID {$b->IdBilletera} (Operador {$b->IdOperador})");
                $this->line("   Saldo en App: Bs. {$saldoApp}");
                $this->line("   Suma Movs:   Bs. {$sumaReal}");
                $this->line("   Diferencia:  Bs. " . ($saldoApp - $sumaReal));
            }
        }

        if ($errores === 0) {
            $this->info("✅ Todo OK. Las {$billeteras->count()} billeteras están cuadradas.");
        } else {
            $this->error("⚠️ Se encontraron {$errores} billeteras descuadradas.");
        }
    }
}