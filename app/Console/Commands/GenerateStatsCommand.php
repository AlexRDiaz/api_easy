<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Stats of Month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            Log::info('Iniciando el comando app:generate-stats');
            app(\App\Http\Controllers\API\PedidosShopifyAPIController::class)->generateTransportStatsTR();
            Log::info('Comando app:generate-stats ejecutado con éxito');
        } catch (Exception $e) {
            Log::error('Error en el comando app:generate-stats: ' . $e->getMessage());
        }
    }
}