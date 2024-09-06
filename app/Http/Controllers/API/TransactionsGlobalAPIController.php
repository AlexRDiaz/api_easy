<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\TransactionsGlobal;



use Carbon\Carbon;
use DateTime;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Stmt\TryCatch;

use function Laravel\Prompts\error;

class TransactionsGlobalAPIController extends Controller
{
    public function index()
    {
        $transactions = TransactionsGlobal::all();
        return response()->json($transactions);
    }

}
