<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CoverageExternal;
use Carbon\Carbon;
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

            $coverage = CoverageExternal::with($populate)
                ->whereRaw('LOWER(ciudad) LIKE ?', ['%' . strtolower($cityNormal) . '%'])
                ->first();

            // $coverage = CoverageExternal::with('dpa_provincia')
            //     ->whereRaw('LOWER(ciudad) LIKE ?', ['%quito%']) // Usar un valor literal para probar
            //     ->first();


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

    public function byProvincia(Request $request)
    {
        error_log("cities_byProvincia");
        $data = $request->json()->all();
        $idProv = $data['idProv'];
        $sinIds = $data['noIdsProd'];

        try {
            if ($sinIds == 1) {
                // error_log("solo Logec");
                $cities = CoverageExternal::with('carrier_coverages')
                    ->where('id_provincia', $idProv)
                    ->whereHas('carrier_coverages', function ($query) {
                        $query->where('id_carrier', 6)->where('active', 1);
                    })
                    ->get();
            } else {
                $cities = CoverageExternal::with('carrier_coverages')
                    ->where('id_provincia', $idProv)
                    ->whereHas('carrier_coverages', function ($query) {
                        $query->where('active', 1);
                    })
                    ->get();
            }


            if (!$cities) {
                error_log("cites no encontrada para la prov especificada");
                return response()->json(['error' => 'Cites no encontrada para la provincia especificada.'], 400);
            }

            return response()->json([
                'data' => $cities,
                'total' => $cities->count(),
            ], 200);
        } catch (\Exception $e) {
            error_log("searchCityByProv_ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al consultar Ciudades: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generalDataOptimizedv2(Request $request)
    {
        error_log("generalDataOptimizedv2");
        $data = $request->json()->all();

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        // $dateFilter = $data["date_filter"];
        $populate = $data["populate"];
        $modelName = $data['model'];
        $Map = $data['and'];
        $not = $data['not'];

        $relationsToInclude = $data['include'];
        $relationsToExclude = $data['exclude'];

        try {

            $fullModelName = "App\\Models\\" . $modelName;

            // Verificar si la clase del modelo existe y es válida
            if (!class_exists($fullModelName)) {
                return response()->json(['error' => 'Modelo no encontrado'], 404);
            }

            // Opcional: Verificar si el modelo es uno de los permitidos
            $allowedModels = ['Transportadora', 'UpUser', 'Vendedore', 'UpUsersVendedoresLink', 'UpUsersRolesFrontLink', 'OrdenesRetiro', 'PedidosShopify', 'Provider', 'TransaccionPedidoTransportadora', "pedidoCarrier"];

            if (!in_array($modelName, $allowedModels)) {
                return response()->json(['error' => 'Acceso al modelo no permitido'], 403);
            }

            if (isset($data['date_filter'])) {
                $dateFilter = $data["date_filter"];
                $selectedFilter = "fecha_entrega";
                if ($dateFilter == "MARCA ENVIO") {
                    $selectedFilter = "marca_tiempo_envio";
                } else if ($dateFilter == "MARCA INGRESO") {
                    $selectedFilter = "marca_t_i";
                }
            }

            if ($searchTerm != "") {
                $filteFields = $data['or'];
            } else {
                $filteFields = [];
            }


            $orderBy = null;
            if (isset($data['sort'])) {
                $sort = $data['sort'];
                $sortParts = explode(':', $sort);
                if (count($sortParts) === 2) {
                    $field = $sortParts[0];
                    $direction = strtoupper($sortParts[1]) === 'DESC' ? 'DESC' : 'ASC';
                    $orderBy = [$field => $direction];
                }
            }
            $databackend = $fullModelName::with($populate);
            if (isset($data['start']) && isset($data['end'])) {
                $startDateFormatted = Carbon::createFromFormat('j/n/Y', $data['start'])->format('Y-m-d');
                $endDateFormatted = Carbon::createFromFormat('j/n/Y', $data['end'])->format('Y-m-d');
                $databackend->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted]);
            }

            $databackend
                // ->whereDoesntHave(['pedidoCarrier',''])
                ->where(function ($databackend) use ($searchTerm, $filteFields) {
                    // foreach ($filteFields as $field) {
                    //     if (strpos($field, '.') !== false) {
                    //         $relacion = substr($field, 0, strpos($field, '.'));
                    //         $propiedad = substr($field, strpos($field, '.') + 1);
                    //         $this->recursiveWhereHasLike($databackend, $relacion, $propiedad, $searchTerm);
                    //     } else {
                    //         $databackend->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    //     }
                    // }
                    foreach ($filteFields as $field) {
                        if (strpos($field, '.') !== false) {
                            $segments = explode('.', $field);
                            $lastSegment = array_pop($segments);
                            $relation = implode('.', $segments);

                            $databackend->orWhereHas($relation, function ($query) use ($lastSegment, $searchTerm) {
                                $query->where($lastSegment, 'LIKE', '%' . $searchTerm . '%');
                            });
                        } else {
                            $databackend->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    }
                })
                ->where((function ($databackend) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            $parts = explode("/", $key);
                            $type = $parts[0];
                            $filter = $parts[1];
                            if ($valor === null) {
                                $databackend->whereNull($filter);
                            } else {
                                if (strpos($filter, '.') !== false) {
                                    $relacion = substr($filter, 0, strpos($filter, '.'));
                                    $propiedad = substr($filter, strpos($filter, '.') + 1);
                                    $this->recursiveWhereHas($databackend, $relacion, $propiedad, $valor);
                                } else {
                                    if ($type == "equals") {
                                        $databackend->where($filter, '=', $valor);
                                    } else {
                                        $databackend->where($filter, 'LIKE', '%' . $valor . '%');
                                    }
                                }
                            }
                        }
                    }
                }))
                // ->where((function ($databackend) use ($not) {
                //     foreach ($not as $condition) {
                //         foreach ($condition as $key => $valor) {
                //             if ($valor === '') {
                //                 // $databackend->whereRaw("$key <> ''");
                //                 $this->recursiveWhereHasNeg($databackend, $relacion, $propiedad, $valor);

                //             } else {
                //                 if ($valor === null) {
                //                     $databackend->whereNotNull($key);
                //                 } else {
                //                     if (strpos($key, '.') !== false) {
                //                         $relacion = substr($key, 0, strpos($key, '.'));
                //                         $propiedad = substr($key, strpos($key, '.') + 1);
                //                         $this->recursiveWhereHas($databackend, $relacion, $propiedad, $valor);
                //                     } else {
                //                         // $databackend->where($key, '!=', $valor);
                //                         $databackend->whereRaw("$key <> ''");
                //                     }
                //                 }
                //             }
                //         }
                //     }
                // }));
                ->where((function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                }));


            // $relationsToInclude = ['ruta', 'transportadora'];
            // $relationsToExclude = ['pedidoCarrier'];

            // $relationsToInclude = ['pedidoCarrier'];
            // $relationsToExclude = ['ruta', 'transportadora'];

            if (isset($relationsToInclude)) {
                // error_log("IS relationsToInclude");
                foreach ($relationsToInclude as $relation) {
                    // error_log("Include relation: $relation");
                    $databackend->whereHas($relation);
                }
            }

            if (isset($relationsToExclude)) {
                // error_log("IS relationsToInclude");
                foreach ($relationsToExclude as $relation) {
                    // error_log("Exclude relation: $relation");
                    $databackend->whereDoesntHave($relation);
                }
            }



            if ($orderBy !== null) {
                $databackend->orderBy(key($orderBy), reset($orderBy));
            }

            $databackend = $databackend->paginate($pageSize, ['*'], 'page', $pageNumber);

            return response()->json($databackend);
        } catch (\Exception $e) {
            error_log("error_generalDataOptimized: $e");
            return response()->json([
                'error' => "There was an error processing your request. " . $e->getMessage()
            ], 500);
        }
    }
}
