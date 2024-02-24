<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
// use App\Models\pedidos_shopifies;
use App\Models\Operadore;
use App\Models\PedidosShopify;
use App\Models\Ruta;
use App\Models\Transportadora;
use App\Models\TransportadorasShippingCost;
// use App\Models\Ruta;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use LengthException;

class TransportadorasAPIController extends Controller
{

    public function index()
    {
        $transportadoras = Transportadora::all();
        return response()->json($transportadoras);
    }
    public function show($id)
    {
        $trasnportadora = Transportadora::with(['admin_user', 'operadores', 'rutas'])
            ->findOrFail($id);

        return response()->json($trasnportadora);
    }

    public function getTransportadoras(Request $request)
    {
        // No necesitas $data en este caso, ya que no lo estás utilizando

        $transportadoras = Transportadora::select(DB::raw('CONCAT(nombre, "-", id ) as id_nombre'))->distinct()->get()->pluck('id_nombre');

        return response()->json(['transportadoras' => $transportadoras]);
    }

    public function getTransportadorasNovelties(Request $request)
    {
        $data = $request->json()->all();
        $idSupervisor = $data['id-supervisor'];

        $transportadoras = Transportadora::where('novelties_supervisor', $idSupervisor)
            ->select(DB::raw('CONCAT(nombre, "-", id) as id_nombre'))
            ->distinct()
            ->get()
            ->pluck('id_nombre');

        if ($transportadoras->isEmpty()) {
            $transportadoras = Transportadora::select(DB::raw('CONCAT(nombre, "-", id) as id_nombre'))
                ->distinct()
                ->get()
                ->pluck('id_nombre');
        }

        return response()->json(['transportadoras' => $transportadoras]);
    }

    public function getActiveTransportadoras(Request $request)
    {
        $transportadoras = Transportadora::where('active', 1)->get();
    
        $formattedTransportadoras = $transportadoras->map(function ($transportadora) {
            return $transportadora->nombre . ' - ' . $transportadora->id;
        })->toArray();
    
        return response()->json( $formattedTransportadoras);
    }

    public function getSpecificDataGeneral(Request $request)
    {
        try {
            $data = $request->json()->all();
            $modelName = $data['model'];
            $populate = $data["populate"];
            $Map = $data['and'];

            $fullModelName = "App\\Models\\" . $modelName;

            // Verificar si la clase del modelo existe y es válida
            if (!class_exists($fullModelName)) {
                return response()->json(['error' => 'Modelo no encontrado'], 404);
            }

            // Opcional: Verificar si el modelo es uno de los permitidos
            $allowedModels = ['Transportadora'];
            if (!in_array($modelName, $allowedModels)) {
                return response()->json(['error' => 'Acceso al modelo no permitido'], 403);
            }

            $databackend = $fullModelName::with($populate)
                ->where((function ($databackend) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            $parts = explode("/", $key);
                            $type = $parts[0];
                            $filter = $parts[1];
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
                }))->get();

            return response()->json($databackend);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'No se encontro la data Solicitada.']);

        }
    }
    public function generalData(Request $request)
    {
        $data = $request->json()->all();

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        // $dateFilter = $data["date_filter"];
        $populate = $data["populate"];
        $modelName = $data['model'];
        $Map = $data['and'];
        $not = $data['not'];

        $fullModelName = "App\\Models\\" . $modelName;

        // Verificar si la clase del modelo existe y es válida
        if (!class_exists($fullModelName)) {
            return response()->json(['error' => 'Modelo no encontrado'], 404);
        }

        // Opcional: Verificar si el modelo es uno de los permitidos
        $allowedModels = ['Transportadora','UpUser','Vendedore','UpUsersVendedoresLink'];
        if (!in_array($modelName, $allowedModels)) {
            return response()->json(['error' => 'Acceso al modelo no permitido'], 403);
        }

        if (isset($data['data_filter'])) {
            $dateFilter = $data["date_filter"];
            $selectedFilter = "fecha_entrega";
            if ($dateFilter != "FECHA ENTREGA") {
                $selectedFilter = "marca_tiempo_envio";
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

        $databackend->where(function ($databackend) use ($searchTerm, $filteFields) {
            foreach ($filteFields as $field) {
                if (strpos($field, '.') !== false) {
                    $relacion = substr($field, 0, strpos($field, '.'));
                    $propiedad = substr($field, strpos($field, '.') + 1);
                    $this->recursiveWhereHasLike($databackend, $relacion, $propiedad, $searchTerm);
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
            }))->where((function ($databackend) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($databackend, $relacion, $propiedad, $valor);
                        } else {
                            $databackend->where($key, '!=', $valor);
                        }
                    }
                }
            }));

        if ($orderBy !== null) {
            $databackend->orderBy(key($orderBy), reset($orderBy));
        }

        $databackend = $databackend->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($databackend);
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



    public function getRutasByCarrier($transportadoraId)
    {
        // Obtener la transportadora con sus rutas relacionadas
        $transportadora = Transportadora::with(['rutas'])->find($transportadoraId);

        if (!$transportadora) {
            return response()->json(['error' => 'Transportadora no encontrada'], 404);
        }

        //all
        // $rutas = $transportadora->rutas->map(function ($ruta) {
        //     return $ruta->titulo . '-' . $ruta->id;
        // });
        $rutasActivas = $transportadora->rutas->filter(function ($ruta) {
            return $ruta->active == 1;
        })->map(function ($ruta) {
            return $ruta->titulo . '-' . $ruta->id;
        });

        return response()->json(['rutas' => $rutasActivas]);
    }

    public function getTransportadorasOfRuta($rutaId)
    {
        // Obtener la ruta con sus transportadoras relacionadas
        $ruta = Ruta::with(['transportadoras'])->find($rutaId);

        // Verificar si la ruta existe
        if (!$ruta) {
            return response()->json(['error' => 'Ruta no encontrada'], 404);
        }

        // Obtener las transportadoras de la ruta y formatear los resultados
        $transportadoras = $ruta->transportadoras->map(function ($transportadora) {
            return $transportadora->nombre . ' - ' . $transportadora->id;
        });

        // Hacer algo con las transportadoras formateadas (por ejemplo, devolverlas en una respuesta JSON)
        return response()->json(['transportadoras' => $transportadoras]);
    }

    public function getOperatoresbyTransport(Request $request, $id)
    {
        $result = Transportadora::with(['operadores.up_users'])
            ->where('id', $id)
            ->get();
        if ($result->isEmpty()) {
            return response()->json(['message' => 'No se encontraron transportadoras con el ID especificado'], 404);
        }

        // Obtener todos los IDs y usernames de up_users
        $usersData = [];
        $datoparcil = "";
        foreach ($result as $transportadora) {
            foreach ($transportadora->operadores as $operador) {
                $datoparcil = $operador->id;
                foreach ($operador->up_users as $user) {
                    $usersData[] = $user->username . '-' . $datoparcil;
                }
            }
        }

        if (empty($usersData)) {
            $resultData = 'No se encontraron usuarios en up_users.';
        } else {
            $resultData = $usersData;
        }

        return response()->json(['operadores' => $resultData]);
        //      // Extraer y formatear los usuarios (operadores)
        // $usersData = $result->flatMap(function ($transportadora) {
        //     return $transportadora->operadores->flatMap(function ($operador) {
        //         return $operador->up_users->map(function ($user) {
        //             return $user->username. '-' . $user->id ;
        //         });
        //     });
        // });

        // return response()->json(['operadores' => $usersData]);
    }

    public function getTransportsByRoute($idRoute)
    {
        $transportadoras = Transportadora::whereHas('rutas', function ($query) use ($idRoute) {
            $query->where('rutas.id', '=', $idRoute);
        })->where('active', 1)->get();

        if ($transportadoras->isEmpty()) {
            return response()->json([], 200);
        }

        return response()->json($transportadoras);
    }

    public function updateSupervisor(Request $request)
    {
        try {
            $id = $request->input('id');
            $noveltiesSupervisor = $request->input('novelties_supervisor');

            $transportadora = Transportadora::find($id);

            if ($transportadora) {
                $transportadora->novelties_supervisor = $noveltiesSupervisor;
                $transportadora->save();
            } else {
                return response()->json(['message' => 'Transportadora no encontrada.'], 404);
            }
            return response()->json(['message' => 'Supervisor actualizado correctamente.'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error en la actualización.'], 200);
        }
    }

    public function getSupervisors(Request $request)
    {
        try {
            // Obtiene valores únicos de la columna 'novelties_supervisor'
            $supervisors = Transportadora::query()
                ->whereNotNull('novelties_supervisor') // Asegúrate de que no se incluyan valores nulos
                ->distinct('novelties_supervisor')
                ->pluck('novelties_supervisor');

            // Devuelve los supervisores como un arreglo de strings
            return response()->json(['supervisors' => $supervisors]);
        } catch (\Throwable $th) {
            // En caso de error, devuelve un mensaje con el error
            return response()->json(['message' => 'Error en la consulta.'], 500);
        }
    }
}
