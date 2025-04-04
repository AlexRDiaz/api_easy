<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CarrierCoverage;
use App\Models\CarriersExternal;
use App\Models\CoverageExternal;
use App\Models\dpaProvincia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\error;

class CarrierExternalAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            error_log("CarrierExternalAPIController-index");
            // error_log("$request");
            $data = $request->json()->all();
            $searchTerm = $data['search'];

            if ($searchTerm != "") {
                $filteFields = $data['or'];
            } else {
                $filteFields = [];
            }

            $carriers = CarriersExternal::where('active', 1)
                ->where(function ($coverages) use ($searchTerm, $filteFields) {
                    foreach ($filteFields as $field) {
                        if (strpos($field, '.') !== false) {
                            $segments = explode('.', $field);
                            $lastSegment = array_pop($segments);
                            $relation = implode('.', $segments);

                            $coverages->orWhereHas($relation, function ($query) use ($lastSegment, $searchTerm) {
                                $query->where($lastSegment, 'LIKE', '%' . $searchTerm . '%');
                            });
                        } else {
                            $coverages->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    }
                })
                ->get();
            // $carriers = CarriersExternal::with('carrier_coverages')
            // ->where('active', 1)->get();
            return response()->json($carriers, 200);
        } catch (\Exception $e) {
            error_log("Error: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
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
        error_log("CarrierExternalAPIController-store");
        // error_log("$request");

        DB::beginTransaction();

        try {
            // ini_set('memory_limit', '256M'); // Puedes ajustar el valor según tus necesidades
            //code...
            $data = $request->json()->all();
            $name = $data['name'];
            $phone = $data['phone'];
            $email = $data['email'];
            $address = $data['address'];
            $status = $data['status'];
            $type_coverage = $data['type_coverage'];
            $costs = $data['costs'];
            $coverage = $data['coverage'];
            // error_log("$coverage");
            //logo ???
            $newCarrier = new CarriersExternal();
            $newCarrier->name = $name;
            $newCarrier->phone = $phone;
            $newCarrier->email = $email;
            $newCarrier->address = $address;
            $newCarrier->status = $status;
            $newCarrier->type_coverage = $type_coverage;
            $newCarrier->costs = $costs;
            $newCarrier->save();

            $getProvincias = dpaProvincia::all();
            // error_log("$provincias");
            $provinciaslist = json_decode($getProvincias, true);

            $getCoberturas = CarrierCoverage::with('coverage_external')->get();

            $ciudades = json_decode($coverage, true);

            // Verificar si el JSON se decodificó correctamente
            if ($ciudades === null) {
                error_log("Error al decodificar el JSON");
            } else {
                $c = 1;
                // Recorrer el arreglo de ciudades con un bucle foreach
                foreach ($ciudades as $ciudad) {

                    $idRefCiudad = $ciudad["id_ciudad"];
                    $ciudadName = $ciudad["ciudad"];
                    $provinciaName = $ciudad["provincia"];
                    $tipo = $ciudad["tipo"];
                    $idRefProv = $ciudad["id_provincia"];

                    // error_log("$c-" . $idRefCiudad . ":" . $ciudadName . "prov: $idRefProv-$provinciaName");
                    $c++;

                    $idProv_local = 0;

                    $provinciaSinAcentos = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $provinciaName));
                    $provinciaSearch = preg_replace('/[^a-zA-Z0-9]/', '', $provinciaSinAcentos);
                    // error_log("provinciaSinAcentos: $provinciaSearch");


                    foreach ($provinciaslist as $provincia) {

                        $provinciaExist = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $provincia["provincia"]));
                        $provName = preg_replace('/[^a-zA-Z0-9]/', '', $provinciaExist);

                        if (strpos($provName, $provinciaSearch) !== false) {
                            $idProv_local = $provincia["id"];
                            // error_log("prov:". $provincia["id"]);
                            break;
                        }
                    }
                    // error_log("idProv_local: $idProv_local");

                    $idCoverage = 0;
                    // error_log("getCoberturas: $getCoberturas");

                    if ($getCoberturas->isNotEmpty()) {
                        $coberturas_exist = json_decode($getCoberturas, true);
                        // error_log("cobertura_exist: $cobertura_exist");
                        $ciudadSinAcentos = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $ciudadName));
                        $ciudadSearch = preg_replace('/[^a-zA-Z0-9]/', '', $ciudadSinAcentos);

                        foreach ($coberturas_exist as $cobertura) {
                            $ciudadExist = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $cobertura["coverage_external"]["ciudad"]));
                            $ciudName = preg_replace('/[^a-zA-Z0-9]/', '', $ciudadExist);
                            // error_log("ciudadSearch: $ciudadSearch");
                            // error_log("ciudName: $ciudName");
                            // error_log("cobertura:".json_decode($cobertura));

                            if (strpos($ciudName, $ciudadSearch) !== false) {
                                $idCoverage = $cobertura["coverage_external"]["id"];
                                break;
                            }
                        }
                        # code...
                    }
                    // error_log("idCoverage: $idCoverage");


                    if ($idCoverage == 0) {
                        // error_log("creat ciudad-cobertura");

                        $newCoverageExt = new CoverageExternal();
                        $newCoverageExt->ciudad = $ciudadName;
                        $newCoverageExt->id_provincia = $idProv_local; //el id local
                        $newCoverageExt->save();
                        $idCoverage = $newCoverageExt->id;
                    }

                    $newCarrierCoverage = new CarrierCoverage();
                    $newCarrierCoverage->id_coverage =  $idCoverage;
                    $newCarrierCoverage->id_carrier = $newCarrier->id;
                    $newCarrierCoverage->type = $tipo;
                    $newCarrierCoverage->id_prov_ref = $idRefProv;
                    $newCarrierCoverage->id_ciudad_ref = $idRefCiudad;
                    $newCarrierCoverage->save();
                }
            }

            DB::commit();
            return response()->json(["message" => "Se creo con exito"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("$e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            error_log("CarrierExternalAPIController-show");
            error_log("$id");
            // return response()->json(['CarrierExternalAPIController-show']);

            // $carriers = CarriersExternal::with('carrier_coverages')
            //     ->where('id', $id)
            //     ->get();
            // return response()->json($carriers, 200);
            $carriers = null;
            try {
                $carriers = CarriersExternal::with(['carrier_coverages' => function ($query) {
                    $query->where('active', 1);
                }])
                    ->where('id', $id)
                    ->get();

                // ->first();
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Ocurrió un error al consultar: ' . $e->getMessage()
                ], 505);
            }

            // if ($carriers == null) {
            if ($carriers->isEmpty()) {
                return response()->json(['message' => 'Not Found!'], 404);
            }

            return response()->json(['data' => $carriers]);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al consultar: ' . $e->getMessage()
            ], 500);
        }
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
        error_log("CarrierExternalAPIController-update");
        error_log("$id");
        // error_log("$request");

        $carrier = CarriersExternal::find($id); // Encuentra al usuario por su ID

        if ($carrier) {
            try {
                $carrier->update($request->all());
                return response()->json(['message' => 'Transportadora actualizada con éxito', "transportadora" => $carrier], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getCoverageByProvincia(Request $request)
    {
        //
        $data = $request->json()->all();
        $idProv = $data['id_provincia'];

        if ($idProv != "") {
            $coverage = CoverageExternal::with('dpa_provincia')
                // ->where('cobertura_gintra', !null)
                ->where('id_provincia', $idProv)
                ->get();
        } else {
            $coverage = CoverageExternal::with('dpa_provincia')
                // ->where('cobertura_gintra', !null)
                // ->where('id_provincia', $idProv)
                ->get();
        }


        return response()->json(['data' => $coverage], 200);
    }

    public function newCoverage(Request $request)
    {
        //
        DB::beginTransaction();

        try {

            error_log("CarrierExternalAPIController-newCoverage");
            //code...
            $data = $request->json()->all();
            $idCarrier = $data['id_carrier'];
            $idCiudad = $data['id_ciudad']; //ref
            $ciudadName = $data['ciudad'];
            $idProv = $data['id_prov']; //ref
            $idProvLocal = $data['id_prov_local']; //ref
            $provinciaName = $data['provincia'];
            $id_city_local = $data['id_city_local']; //local
            $tipo = $data['tipo'];

            if ($id_city_local != "0") {
                error_log("id_city_local: $id_city_local");

                $newCarrierCoverage = new CarrierCoverage();
                $newCarrierCoverage->id_coverage =  $id_city_local;
                $newCarrierCoverage->id_carrier = $idCarrier;
                $newCarrierCoverage->type = $tipo;
                $newCarrierCoverage->id_prov_ref = $idProv;
                $newCarrierCoverage->id_ciudad_ref = $idCiudad;
                $newCarrierCoverage->save();
            } else {
                //
                error_log("Ciudad nueva");
                ////???

                $getCoberturas = CarrierCoverage::with('coverage_external')->get();

                $idCoverage = 0;
                // error_log("getCoberturas: $getCoberturas");

                if ($getCoberturas->isNotEmpty()) {
                    $cobertura_exist = json_decode($getCoberturas, true);
                    // error_log("cobertura_exist: $cobertura_exist");
                    $ciudadSinAcentos = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $ciudadName));
                    $ciudadSearch = preg_replace('/[^a-zA-Z0-9]/', '', $ciudadSinAcentos);

                    foreach ($cobertura_exist as $cobertura) {
                        $ciudadExist = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $cobertura["coverage_external"]["ciudad"]));
                        $ciudName = preg_replace('/[^a-zA-Z0-9]/', '', $ciudadExist);

                        if (strpos($ciudName, $ciudadSearch) !== false) {
                            $idCoverage = $cobertura["coverage_external"]["id"];
                            break;
                        }
                    }
                }
                error_log("idCoverage: $idCoverage");
                ////

                if ($idCoverage == 0) {
                    error_log("creat ciudad-cobertura");

                    $newCoverageExt = new CoverageExternal();
                    $newCoverageExt->ciudad = $ciudadName;
                    $newCoverageExt->id_provincia = $idProvLocal;
                    $newCoverageExt->save();
                    $idCoverage = $newCoverageExt->id;
                }

                $newCarrierCoverage = new CarrierCoverage();
                $newCarrierCoverage->id_coverage =  $idCoverage;
                $newCarrierCoverage->id_carrier = $idCarrier;
                $newCarrierCoverage->type = $tipo;
                $newCarrierCoverage->id_prov_ref = $idProv;
                $newCarrierCoverage->id_ciudad_ref = $idCiudad;
                $newCarrierCoverage->save();
            }

            DB::commit();
            return response()->json(["message" => "Se creo con exito"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("carrierExternalAPI_newCoverage: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }


    public function showById(string $id)
    {
        //
        ini_set('memory_limit', '512M');
        try {
            error_log("CarrierExternalAPIController-showById");
            error_log("$id");

            $carriers = null;
            $carriers = CarriersExternal::with(['carrier_coverages_simple' => function ($query) {
                $query->where('active', 1);
            }])
                ->where('id', $id)
                ->get();

            //this works
            // $carriers = CarriersExternal::where('id', $id)
            //     ->get();

            if ($carriers->isEmpty()) {
                return response()->json(['message' => 'Not Found!'], 404);
            }

            return response()->json(['data' => $carriers]);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al consultar carriers externals: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCarriersExternal(Request $request)
    {

        $transportadoras = CarriersExternal::where('active', 1)
            ->select(DB::raw('CONCAT(name, "-", id) as id_nombre'))
            ->distinct()
            ->get()
            ->pluck('id_nombre');

        return response()->json(['transportadoras' => $transportadoras]);
    }

    public function multiNewCoverage(Request $request)
    {
        //
        error_log("CarrierExternalAPIController-multiNewCoverage");
        // error_log("$request");
        ini_set('max_execution_time', 300); // 5 minutos

        DB::beginTransaction();

        try {
            // ini_set('memory_limit', '256M'); // Puedes ajustar el valor según tus necesidades
            //code...
            $data = $request->json()->all();
            $carrierId = $data['carrier_id'];

            $coverage = $data['coverage'];

            $getProvincias = dpaProvincia::all();
            // error_log("$provincias");
            $provinciaslist = json_decode($getProvincias, true);

            $getCoberturas = CarrierCoverage::with('coverage_external')->get();

            $ciudades = json_decode($coverage, true);

            // Verificar si el JSON se decodificó correctamente
            if ($ciudades === null) {
                error_log("Error al decodificar el JSON");
            } else {
                $c = 1;
                // Recorrer el arreglo de ciudades con un bucle foreach
                foreach ($ciudades as $ciudad) {

                    $idRefCiudad = $ciudad["id_ciudad"];
                    $ciudadName = $ciudad["ciudad"];
                    $provinciaName = $ciudad["provincia"];
                    $tipo = $ciudad["tipo"];
                    $idRefProv = $ciudad["id_provincia"];

                    $provinciaSinAcentos = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $provinciaName));
                    $provinciaSearch = preg_replace('/[^a-zA-Z0-9]/', '', $provinciaSinAcentos);

                    $provincia = dpaProvincia::whereRaw("LOWER(REPLACE(provincia, ' ', '')) LIKE ?", ["%$provinciaSearch%"])
                        ->first();

                    $idProv_local = 0;

                    if ($provincia) {
                        $idProv_local = $provincia->id;
                    }

                    if ($idProv_local != 0) {
                        $ciudadSinAcentos = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT',  $ciudadName));
                        $ciudadSearch = preg_replace('/[^a-zA-Z0-9]/', '', $ciudadSinAcentos);

                        $cityFound = CoverageExternal::where('id_provincia', $idProv_local)
                            ->whereRaw("CONVERT(REPLACE(ciudad, ' ', '') USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(REPLACE(?, ' ', '') USING utf8mb4) COLLATE utf8mb4_unicode_ci", [$ciudadSearch])
                            ->first();

                        $idCoverage = 0;

                        if ($cityFound) {
                            $idCoverage = $cityFound->id;
                        }

                        if ($idCoverage == 0) {
                            // error_log("create ciudad-cobertura");

                            $newCoverageExt = new CoverageExternal();
                            $newCoverageExt->ciudad = $ciudadName;
                            $newCoverageExt->id_provincia = $idProv_local; //el id local
                            $newCoverageExt->save();
                            $idCoverage = $newCoverageExt->id;
                        }

                        $newCarrierCoverage = new CarrierCoverage();
                        $newCarrierCoverage->id_coverage =  $idCoverage;
                        $newCarrierCoverage->id_carrier = $carrierId;
                        $newCarrierCoverage->type = $tipo;
                        $newCarrierCoverage->id_prov_ref = $idRefProv;
                        $newCarrierCoverage->id_ciudad_ref = $idRefCiudad;
                        $newCarrierCoverage->save();
                    } else {
                        error_log("provinciaNotFound");
                    }
                    
                }
            }

            DB::commit();
            return response()->json(["message" => "Se creo con exito"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("$e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
}
