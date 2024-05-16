<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDBBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-dbbackup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate DB Backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            Log::info('Iniciando el comando app:generate-dbbackup');
            app(\App\Http\Controllers\API\DBBackUpAPIController::class)->db_backup();
            Log::info('Comando app:generate-dbbackup ejecutado con éxito');
        } catch (Exception $e) {
            Log::error('Error en el comando app:generate-dbbackup: ' . $e->getMessage());
        }
    }
}