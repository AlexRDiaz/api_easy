<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class ExchangeRateAPIController extends Controller
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

    public function byCountry(string $id)
    {
        $id = 2;
        $exchangeRates = ExchangeRate::where('country_id', $id)
            ->get();

        return response()->json(['rates' => $exchangeRates]);
    }

    public function updateData(Request $request)
    {
        //
        error_log("ExchangeRate upt");
        try {

            $data = $request->json()->all();
            $sources = ['BCV', 'Paralelo', 'Promedio'];

            foreach ($sources as $source) {
                if (isset($data[$source])) {
                    ExchangeRate::where('country_id', 2)
                        ->where('source', $source)
                        ->update(['rate' => $data[$source]]);
                }
            }

            return response()->json(['message' => 'updated successfully.'], 200);
        } catch (\Exception $e) {
            error_log("updateExchangeRate_error: " . $e->getMessage());
            return response()->json(['Error' => $e->getMessage()], 500);
        }
    }
}
