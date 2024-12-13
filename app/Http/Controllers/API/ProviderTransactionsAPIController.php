<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProviderTransaction;
use Carbon\Carbon;
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

    //*
    public function calculateValuesPendingExternalCarrier(Request $request)
    {
        error_log("calculateValuesPendingExternalCarrier");
        $data = $request->json()->all();
        $idProvider = $data['id_provider'];

        $totalTransactionSum = ProviderTransaction::query()
            ->where('provider_id', $idProvider)
            ->where('payment_status', 'PENDIENTE')
            ->sum('amount');

        return response()->json(
            $totalTransactionSum,
        );
    }
}
