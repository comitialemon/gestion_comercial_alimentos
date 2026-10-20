<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:limpiar {--dias=30}';
    protected $description = 'Borra keys de idempotencia más antiguas que N días';

    public function handle()
    {
        $dias = (int) $this->option('dias');
        $fechaLimite = now()->subDays($dias);

        $borrados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('pedidos_clientes_operaciones_idempotentes')
            ->where('CreatedAt', '<', $fechaLimite)
            ->delete();

        $this->info("✅ Se borraron {$borrados} keys más viejas que {$dias} días.");
    }
}