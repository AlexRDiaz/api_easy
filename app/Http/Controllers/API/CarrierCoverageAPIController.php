<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CarrierCoverage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarrierCoverageAPIController extends Controller
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
        $cobertura = CarrierCoverage::find($id); // Encuentra al usuario por su ID

        if ($cobertura) {
            try {
                $cobertura->update($request->all());
                return response()->json(['message' => 'Cobertura actualizado con éxito', "cobertura" => $cobertura], 200);
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

    public function getAll(Request $request)
    {
        $data = $request->json()->all();


        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $andMap = $data['and'];

        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        $coverages = CarrierCoverage::with(['carriers_external_simple', 'coverage_external'])
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
            ->where((function ($coverages) use ($andMap) {
                foreach ($andMap as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($coverages, $relacion, $propiedad, $valor);
                        } else {
                            if ($type == "equals") {
                                $coverages->where($filter, '=', $valor);
                            } else {
                                $coverages->where($filter, 'LIKE', '%' . $valor . '%');
                            }
                        }
                    }
                }
            }))
            ->where('active', 1);

        // ! Ordena
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
            $coverages->orderBy(key($orderByText), reset($orderByText));
        } else {
            $coverages->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }

        $coverages = $coverages->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($coverages, 200);

        // $coverages= CarrierCoverage::with('carriers_external_simple','coverage_external')
        // ->get();

        // return response()->json($coverages, 200);
    }

    private function recursiveWhereHas($query, $relation, $property, $searchTerm)
    {
        if ($searchTerm == "null") {
            $searchTerm = null;
        }
        if (strpos($property, '.') !== false) {

            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm) {
                $this->recursiveWhereHas($q, $nestedRelation, $nestedProperty, $searchTerm);
            });
        } else {
            $query->whereHas($relation, function ($q) use ($property, $searchTerm) {
                $q->where($property, '=', $searchTerm);
            });
        }
    }

    private function recursiveWhereHasLike($query, $relation, $property, $searchTerm)
    {
        if ($searchTerm == "null") {
            $searchTerm = null;
        }
        if (strpos($property, '.') !== false) {

            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm) {
                $this->recursiveWhereHasLike($q, $nestedRelation, $nestedProperty, $searchTerm);
            });
        } else {
            $query->whereHas($relation, function ($q) use ($property, $searchTerm) {
                $q->where($property,  'LIKE', '%' . $searchTerm . '%');
            });
        }
    }
}
