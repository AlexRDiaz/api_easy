<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CoverageExternal;
use Illuminate\Http\Request;

class CoverageExternalAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        error_log("CoverageExternalAPIController-index");
        // error_log("$request");

        $carriers = CoverageExternal::all();
        // $carriers = CarriersExternal::with('carrier_coverages')
        // ->where('active', 1)->get();
        return response()->json($carriers, 200);
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

    public function search(Request $request)
    {
        $data = $request->json()->all();
        error_log("cities_search");
        try {
            $city = $data['city'];
            $populate = $data['populate'];

            $cityNormal = $this->normalizeString($city);

            // $coverage = CoverageExternal::with($populate)
            //     ->whereRaw('LOWER(ciudad) LIKE ?', ['%' . strtolower($cityNormal) . '%'])
            //     ->first();

            $coverage = CoverageExternal::with('dpa_provincia')
                ->whereRaw('LOWER(ciudad) LIKE ?', ['%quito%']) // Usar un valor literal para probar
                ->first();


            // ->where(function ($coverages) use ($andMap) {
            //     foreach ($andMap as $condition) {
            //         foreach ($condition as $key => $valor) {
            //             $parts = explode("/", $key);
            //             $type = $parts[0];
            //             $filter = $parts[1];
            //             if (strpos($filter, '.') !== false) {
            //                 $relacion = substr($filter, 0, strpos($filter, '.'));
            //                 $propiedad = substr($filter, strpos($filter, '.') + 1);
            //                 $this->recursiveWhereHas($coverages, $relacion, $propiedad, $valor);
            //             } else {
            //                 if ($type == "equals") {
            //                     $coverages->where($filter, '=', $valor);
            //                 } else {
            //                     $coverages->where($filter, 'LIKE', '%' . $valor . '%');
            //                 }
            //             }
            //         }
            //     }
            // })
            // ->first();

            if (!$coverage) {
                error_log("searchCity_Cobertura no encontrada para la ciudad especificada");
                return response()->json(['error' => 'Cobertura no encontrada para la ciudad especificada.'], 400);
            }

            return response()->json($coverage, 200);
        } catch (\Exception $e) {
            error_log("searchCity_ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al consultar Ciudades: ' . $e->getMessage()
            ], 500);
        }
    }

    function normalizeString($string)
    {
        $string = strtolower($string);
        return preg_replace(
            '/[áàäâã]/u',
            'a',
            preg_replace(
                '/[éèëê]/u',
                'e',
                preg_replace(
                    '/[íìïî]/u',
                    'i',
                    preg_replace(
                        '/[óòöôõ]/u',
                        'o',
                        preg_replace('/[úùüû]/u', 'u', $string)
                    )
                )
            )
        );
    }
}
