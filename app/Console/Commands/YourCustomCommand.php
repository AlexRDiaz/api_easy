<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\PedidosShopifyAPIController;
use App\Http\Controllers\API\TransportadorasAPIController;
use App\Http\Controllers\API\TransportadorasShippingCostAPIController;
use Illuminate\Console\Command;

class YourCustomCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:your-custom-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
    //     error_log("ha funcionado");
    //   DB::table('test')->insert([
    //     'counter' => 1,
    // ]);
        // app(PedidosShopifyAPIController::class)->generateTransportCosts();

        app(TransportadorasShippingCostAPIController::class)->getShippingCostPerDay();

    }
}
