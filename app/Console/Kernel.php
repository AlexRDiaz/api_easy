<?php

namespace App\Console;

use App\Http\Controllers\API\UpUserAPIController;
use App\Models\UpUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('app:your-custom-command')->everyMinute(); // Cambia 'your:custom-command' al nombre de tu comando personalizado
        // $schedule->command('app:your-custom-command')->dailyAt('16:33');
        // date_default_timezone_set('Etc/GMT+5');
        // $schedule->command('app:your-custom-command')->everyThirtySeconds();
        // $schedule->command('app:your-custom-command')->daily();

        $schedule->command('app:your-custom-command')->dailyAt('23:59');


        // monthly cutoff
        $schedule->command('app:generate-stats')->cron('59 23 28-31 * *');

        // (daily cutoff) -> TEST
        // $schedule->command('app:generate-stats')->everyMinute();
	//$schedule->command('app:generate-stats')->dailyAt('14:37');
        //  error_log("usuario logueado");
    }


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
