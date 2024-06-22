<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderTransaction;
use App\Models\UpUser;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderTransactionsAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getAll(Request $request)
    {
        //
        $data = $request->json()->all();

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $populate = $data['populate'];

        $startDate = Carbon::parse($data['start'] . " 00:00:00");
        $endDate = Carbon::parse($data['end'] . " 23:59:59");

        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        $andMap = $data['and'];

        $provTransactions = ProviderTransaction::with($populate)
            ->whereRaw("timestamp BETWEEN ? AND ?", [$startDate, $endDate])
            ->where(function ($provTransactions) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($provTransactions, $relacion, $propiedad, $searchTerm);
                    } else {
                        $provTransactions->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($provTransactions) use ($andMap) {
                foreach ($andMap as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($provTransactions, $relacion, $propiedad, $valor);
                        } else {
                            if ($type == "equals") {
                                $provTransactions->where($filter, '=', $valor);
                            } else {
                                $provTransactions->where($filter, 'LIKE', '%' . $valor . '%');
                            }
                        }
                    }
                }
            }));

        // ! sort
        $orderByText = null;
        $orderByDate = null;
        $sort = $data['sort'];
        $sortParts = explode(':', $sort);

        $pt1 = $sortParts[0];

        $type = (stripos($pt1, 'fecha') !== false || stripos($pt1, 'marca') !== false) ? 'date' : 'text';

        $dataSort = [
            [
                'field' => $sortParts[0],
                'type' => $type,
                'direction' => $sortParts[1],
            ],
        ];

        foreach ($dataSort as $value) {
            $field = $value['field'];
            $direction = $value['direction'];
            $type = $value['type'];

            if ($type === "text") {
                $orderByText = [$field => $direction];
            } else {
                $orderByDate = [$field => $direction];
            }
        }

        if ($orderByText !== null) {
            $provTransactions->orderBy(key($orderByText), reset($orderByText));
        } else {
            $provTransactions->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $provTransactions = $provTransactions->paginate($pageSize, ['*'], 'page', $pageNumber);
        return response()->json($provTransactions);
    }

    public function getTotalRetiros($id)
    {
        // error_log(" getTotalRetiros from $id");

        try {
            $totalAmount = 0.0;
            $provRetiros = ProviderTransaction::where("provider_id", $id)
                ->where("transaction_type", "Retiro")
                ->whereIn("status", ["APROBADO", "REALIZADO"])
                ->get();

            foreach ($provRetiros as $retiro) {
                $totalAmount += floatval($retiro->amount);
            }

            return response()->json(['total_amount' => $totalAmount], 200);
        } catch (\Exception $e) {
            error_log("error: $e");
            return response()->json([
                'error' => "There was an error processing your request. " . $e->getMessage()
            ], 500);
        }
    }

    public function CreditProvider(Request $request)
    {
        //
        error_log("CreditProvider");
        $data = $request->json()->all();

        $providerUserId = $data['user_id'];
        error_log("$providerUserId");

        $transaction_type = $data['transaction_type'];
        $amount = $data['amount'];
        $originId = $data['origin_id'];
        $originCode = $data['origin_code'];
        $comment = $data['comment'];
        $generated_by = $data['generated_by'];
        $status = $data['status'];
        $description = $data['description'];
        $sku_product_reference = $data['sku_product_reference'];


        $startDateFormatted = new DateTime();
        $user = UpUser::where("id", $providerUserId)->with('providers')->first();
        $provider = $user['providers'][0];
        $saldo = $provider->saldo;

        $nuevoSaldo = $saldo + $amount;
        // $provider->saldo = $nuevoSaldo;


        // $newTrans = new Transaccion();
        $newTrans = new ProviderTransaction();

        $newTrans->transaction_type = $transaction_type;
        $newTrans->amount = $amount;
        $newTrans->previous_value = $saldo;
        $newTrans->current_value = $nuevoSaldo;
        $newTrans->timestamp = now();
        $newTrans->origin_id = $originId;
        $newTrans->origin_code = $originCode;
        $newTrans->provider_id = $user['providers'][0]['id'];
        $newTrans->comment = $comment;
        $newTrans->generated_by = $generated_by;
        $newTrans->status = $status;
        $newTrans->description = $description;
        $newTrans->sku_product_reference = $sku_product_reference;
        $newTrans->save();

        $providerencontrado = Provider::findOrFail($user['providers'][0]['id']);
        $providerencontrado->saldo = $nuevoSaldo;
        $providerencontrado->save();

        return response()->json("Monto acreditado");
    }

    public function DebitProvider(Request $request)
    {
        //
        $data = $request->json()->all();

        $providerUserId = $data['user_id'];
        $transaction_type = $data['transaction_type'];
        $amount = $data['amount'];
        $originId = $data['origin_id'];
        $originCode = $data['origin_code'];
        $comment = $data['comment'];
        $generated_by = $data['generated_by'];
        $status = $data['status'];
        $description = $data['description'];
        $sku_product_reference = $data['sku_product_reference'];

        $startDateFormatted = new DateTime();
        $user = UpUser::where("id", $providerUserId)->with('providers')->first();

        $provider = $user['providers'][0];
        $saldo = $provider->saldo;
        $nuevoSaldo = $saldo - $amount;
        // $user->saldo = $nuevoSaldo;

        $newTrans = new ProviderTransaction();
        $newTrans->transaction_type = $transaction_type;
        $newTrans->amount = $amount;
        $newTrans->previous_value = $saldo;
        $newTrans->current_value = $nuevoSaldo;
        $newTrans->timestamp = now();
        $newTrans->origin_id = $originId;
        $newTrans->origin_code = $originCode;
        $newTrans->provider_id = $user['providers'][0]['id'];
        $newTrans->comment = $comment;
        $newTrans->generated_by = $generated_by;
        $newTrans->status = $status;
        $newTrans->description = $description;
        $newTrans->description = $description;
        $newTrans->sku_product_reference = $sku_product_reference;
        $newTrans->save();

        $providerencontrado = Provider::findOrFail($user['providers'][0]['id']);
        $providerencontrado->saldo = $nuevoSaldo;
        $providerencontrado->save();

        return response()->json("Monto debitado");
    }

    public function recalculateSaldos(Request $request)
    {
        //
        error_log("recalculateSaldos");
        $providers = Provider::where('active', 1)->get();

        $providers->each(function ($provider) {
            $provId = $provider->id;
            $provName = $provider->name;
            // error_log("$provId: $provName ");
            $transactions = ProviderTransaction::where('provider_id', $provId)->get();
            if ($transactions->isNotEmpty()) {
                error_log("$provId: $provName ");

                // Sumar "Pago Producto" con state 1
                $totalPagoProducto = $transactions->where('transaction_type', 'Pago Producto')
                    ->where('state', 1)
                    ->sum('amount');

                // Sumar "Pago Producto" con state 0 //rollbacks
                $totalPagoProductoRoll = $transactions->where('transaction_type', 'Pago Producto')
                    ->where('state', 0)
                    ->sum('amount');

                // Sumar "Retiro"
                $totalRetiro = $transactions->where('transaction_type', 'Retiro')
                    ->sum('amount');

                error_log("totalPagoProducto: $totalPagoProducto");
                error_log("totalPagoProductoRoll: $totalPagoProductoRoll");
                error_log("totalRetiro: $totalRetiro");

                $saldoActual = $totalPagoProducto - ($totalRetiro + $totalPagoProductoRoll);
                error_log("saldo: $saldoActual");

                $providerFound = Provider::findOrFail($provId);
                $providerFound->saldo = $saldoActual;
                $providerFound->save();
            }
            error_log("*********************");
        });
    }
}
