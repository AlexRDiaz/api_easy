<?php

namespace App\Http\Controllers\API;

use App\Jobs\ExportOrdersJob;
use App\Http\Controllers\Controller;
use App\Models\CarrierCoverage;
use App\Models\CarriersExternal;
use App\Models\CoverageExternal;
use App\Models\dpaProvincia;
use App\Models\OrdenesRetiro;
use App\Models\PedidoFecha;
use App\Models\pedidos_shopifies;
use App\Models\PedidosShopifiesPedidoFechaLink;
use App\Models\PedidosShopifiesRutaLink;
use App\Models\PedidosShopifiesSubRutaLink;
use App\Models\PedidosShopifiesOperadoreLink;
use App\Models\PedidosShopifiesTransportadoraLink;
use App\Models\PedidosShopify;
use App\Models\Provider;
use App\Models\Warehouse;
use App\Models\Transportadora;
use App\Models\ProductoShopifiesPedidosShopifyLink;
use App\Models\Ruta;
use App\Models\Operadore;
use App\Models\PedidosProductLink;
use App\Models\PedidosShopifiesCarrierExternalLink;
use App\Models\Product;
use App\Models\ProviderTransaction;
use App\Models\TransaccionGlobal;
use App\Models\TransaccionPedidoTransportadora;
use App\Models\TransactionsGlobal;
use App\Models\TransportStats;
use App\Models\UpUser;
use App\Models\UpUsersPedidosShopifiesLink;
use App\Models\Vendedore;
use Carbon\Carbon;
use DateTime;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Normalizer;
use PhpParser\Node\Stmt\TryCatch;

use function Laravel\Prompts\error;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\IOFactory;
// use PhpOffice\PhpSpreadsheet\CachedObjectStorage\MemoryGZip;

class PedidosShopifyAPIController extends Controller
{
    public function index()
    {
        $pedidos = PedidosShopify::all();
        return response()->json($pedidos);
    }



    public function updateCampo(Request $request, $id)
    {
        // Recuperar los datos del formulario
        $data = $request->all();

        // Encuentra el registro en base al ID
        $pedido = PedidosShopify::findOrFail($id);

        // Actualiza los campos específicos en base a los datos del formulario
        $pedido->fill($data);
        $pedido->save();

        // Respuesta de éxito
        return response()->json(['message' => 'Registro actualizado con éxito', "res" => $pedido], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        // Recuperar los datos del formulario

        // Recuperar el nuevo estado del pedido
        $data = $request->all();
        $newStatus = $data['status'];

        // Encuentra el registro en base al ID
        $pedido = PedidosShopify::findOrFail($id);



        // Actualiza el estado del pedido
        $pedido->status = $newStatus;

        $pedido->confirmed_at = new DateTime();
        $pedido->confirmed_by = 999;
        $pedido->save();

        $user = UpUser::findOrFail($pedido->id_comercial);
        $config_autome = $user->config_autome;
        $configs = json_decode($config_autome, true);
        $pedidoRuta = new PedidosShopifiesRutaLink();
        $pedidoRuta->pedidos_shopify_id = $pedido->id;
        $pedidoRuta->ruta_id = $configs["ruta"];
        $pedidoRuta->save();

        $pedidoTransportadora = new PedidosShopifiesTransportadoraLink();
        $pedidoTransportadora->pedidos_shopify_id = $pedido->id;
        $pedidoTransportadora->transportadora_id = $configs["transportadora"];
        $pedidoTransportadora->save();


        // Respuesta de éxito
        return response()->json(['message' => 'Registro actualizado con éxito', 'id' => $pedido->id, 'status' => $pedido->status, "config autome" => $configs], 200);
    }

    public function show($id)
    {
        error_log("show");

        $pedido = PedidosShopify::with([
            'operadore.up_users',
            'transportadora',
            'users.vendedores',
            'novedades',
            'pedidoFecha',
            'ruta',
            'subRuta',
            "statusLastModifiedBy",
            "carrierExternal",
            "ciudadExternal",
            "pedidoCarrier"
        ])
            ->findOrFail($id);

        return response()->json($pedido);
    }


    public function getDevolucionesOperator(Request $request)
    {
        $data = $request->json()->all();
        $Map = $data['and'];
        $not = $data['not'];
        $searchTerm = $data['search'];
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $orConditions = $data['multifilter'];

        if ($searchTerm != "") {
            $filteFields = $data['or']; // && SOLO QUITO  ((||)&&())
        } else {
            $filteFields = [];
        }

        $pedidos = PedidosShopify::with(['operadore.up_users'])
            ->with('transportadora')
            ->with('users.vendedores')
            ->with('novedades')
            ->with('pedidoFecha')
            ->with('ruta')
            ->with('subRuta')
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->orWhere(function ($pedidos) use ($orConditions) {
                //condiciones multifilter
                foreach ($orConditions as $condition) {
                    $pedidos->orWhere(function ($subquery) use ($condition) {
                        foreach ($condition as $field => $value) {
                            $subquery->orWhere($field, $value);
                        }
                    });
                }
            })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
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
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }

    public function getByDateRangeLogistic(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];

        if ($searchTerm != "") {
            $filteFields = $data['or']; // && SOLO QUITO  ((||)&&())
        } else {
            $filteFields = [];
        }

        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************
        // ! ordenamiento ↓
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

        // ! *************************************

        $pedidos = PedidosShopify::with(['operadore.up_users'])
            ->with('transportadora')
            ->with('users.vendedores')
            ->with('novedades')
            ->with('pedidoFecha')
            ->with('ruta')
            ->with('subRuta')
            ->with('statusLastModifiedBy')
            ->with('vendor')
            ->with('pedidoCarrier')
            ->whereRaw("STR_TO_DATE(marca_t_i, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            if ($type == "equals") {
                                $pedidos->where($filter, '=', $valor);
                            } else {
                                $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
                            }
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
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
        // ! Ordena
        if ($orderBy !== null) {
            $pedidos->orderBy(key($orderBy), reset($orderBy));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }

    public function getByDateRangeAuditAndResolvedNovelties(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $dateFilter = $data["date_filter"];


        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }


        if ($searchTerm != "") {
            $filteFields = $data['or']; // && SOLO QUITO  ((||)&&())
        } else {
            $filteFields = [];
        }

        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************
        // ! ordenamiento ↓
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

        // ! *************************************
        $pedidos = PedidosShopify::with([
            'operadore.up_users',
            'novedades',
            'confirmedBy',
            'statusLastModifiedBy',
            'transportadora',
            'users.vendedores',
            'pedidoCarrier',
            "vendor",
        ])
            ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            if ($type == "equals") {
                                $pedidos->where($filter, '=', $valor);
                            } else {
                                $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
                            }
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
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
        // ! Ordena
        if ($orderBy !== null) {
            $pedidos->orderBy(key($orderBy), reset($orderBy));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }

    // ! functional novelties
    public function getByDateRangeLogisticNovelties(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $dateFilter = $data["date_filter"];


        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }


        if ($searchTerm != "") {
            $filteFields = $data['or']; // && SOLO QUITO  ((||)&&())
        } else {
            $filteFields = [];
        }

        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************
        // ! ordenamiento ↓
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

        // ! *************************************
        $pedidos = PedidosShopify::with([
            'operadore.up_users',
            'novedades',
            'confirmedBy',
            'statusLastModifiedBy',
            'transportadora',
            'users.vendedores',
            'pedidoCarrier',
            "vendor",
        ])
            ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            if ($type == "equals") {
                                $pedidos->where($filter, '=', $valor);
                            } else {
                                $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
                            }
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
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
        // ! Ordena
        if ($orderBy !== null) {
            $pedidos->orderBy(key($orderBy), reset($orderBy));
        }

        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }

    // ! test new version for novelties
    // public function getByDateRangeLogisticNovelties(Request $request)
    // {

    //     // Extraer y formatear las fechas, tamaño de página, número de página, términos de búsqueda, etc.
    //     $data = $request->json()->all();
    //     $startDate = Carbon::createFromFormat('j/n/Y', $data['start'])->format('Y-m-d');
    //     $endDate = Carbon::createFromFormat('j/n/Y', $data['end'])->format('Y-m-d');
    //     $pageSize = $data['page_size'];
    //     $pageNumber = $data['page_number'];
    //     $searchTerm = $data['search'];
    //     $dateFilter = $data["date_filter"];
    //     $selectedFilter = $dateFilter != "FECHA ENTREGA" ? "marca_tiempo_envio" : "fecha_entrega";
    //     $filteFields = $searchTerm ? $data['or'] : [];
    //     $Map = $data['and'];
    //     $not = $data['not'];
    //     $orderBy = $this->getOrderBy($data);

    //     // Construir consulta para `pedidos`
    //     $pedidos = $this->applyFiltersNV(PedidosShopify::with([
    //         'operadore.up_users',
    //         'novedades',
    //         'confirmedBy',
    //         'statusLastModifiedBy',
    //         'transportadora',
    //         'users.vendedores',
    //         'pedidoCarrier'
    //     ]), $selectedFilter, $startDate, $endDate, $searchTerm, $filteFields, $Map, $not);

    //     // Ordenar si es necesario
    //     if ($orderBy !== null) {
    //         $pedidos->orderBy(key($orderBy), reset($orderBy));
    //     }

    //     // Paginación
    //     $pedidosPaginated = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

    //     return response()->json($pedidosPaginated);
    // }

    public function getVendedoresByDateRange(Request $request)
    {
        // Reutilizar los mismos filtros para obtener los `vendedores` únicos
        $data = $request->json()->all();
        $startDate = Carbon::createFromFormat('j/n/Y', $data['start'])->format('Y-m-d');
        $endDate = Carbon::createFromFormat('j/n/Y', $data['end'])->format('Y-m-d');
        $searchTerm = $data['search'];
        $dateFilter = $data["date_filter"];
        $selectedFilter = $dateFilter != "FECHA ENTREGA" ? "marca_tiempo_envio" : "fecha_entrega";
        $filteFields = $searchTerm ? $data['or'] : [];
        $Map = $data['and'];
        $not = $data['not'];

        // Aplicar los filtros para `vendedores`
        $pedidos = $this->applyFiltersNVR(PedidosShopify::with('users.vendedores'), $selectedFilter, $startDate, $endDate);

        $vendedores = $pedidos->get()
            ->pluck('users.*.vendedores.*')
            ->flatten(1)
            ->unique('id')
            ->map(function ($vendedor) {
                return [
                    'id' => $vendedor->id_master,
                    'nombre_comercial' => $vendedor->nombre_comercial
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $vendedores
        ]);
    }

    private function getOrderBy($data)
    {
        if (isset($data['sort'])) {
            $sort = $data['sort'];
            $sortParts = explode(':', $sort);
            if (count($sortParts) === 2) {
                $field = $sortParts[0];
                $direction = strtoupper($sortParts[1]) === 'DESC' ? 'DESC' : 'ASC';
                return [$field => $direction];
            }
        }
        return null;
    }

    private function applyFiltersNV($query, $selectedFilter, $startDate, $endDate, $searchTerm, $filteFields, $Map, $not)
    {
        return $query->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
            ->where(function ($query) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($query, $relacion, $propiedad, $searchTerm);
                    } else {
                        $query->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where(function ($query) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($query, $relacion, $propiedad, $valor);
                        } else {
                            $query->where($filter, $type == "equals" ? '=' : 'LIKE', $type == "equals" ? $valor : '%' . $valor . '%');
                        }
                    }
                }
            })
            ->where(function ($query) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($query, $relacion, $propiedad, $valor);
                        } else {
                            $query->where($key, '!=', $valor);
                        }
                    }
                }
            });
    }

    private function applyFiltersNVR($query, $selectedFilter, $startDate, $endDate)
    {
        return $query->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate]);
    }

    // ! **********************



    // ! for generate pdfs without pagination 
    public function getByDateRangeOrdersforAudit(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $searchTerm = $data['search'];

        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        $Map = $data['and'];
        $not = $data['not'];

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

        $pedidos = PedidosShopify::with(['operadore.up_users'])
            ->with('transportadora')
            ->with('users.vendedores')
            ->with('novedades')
            ->with('pedidoFecha')
            ->with('ruta')
            ->with('subRuta')
            ->with('confirmedBy')
            ->with('pedidoCarrier')
            ->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where(function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $this->applyCondition($pedidos, $key, $valor);
                    }
                }
            })
            ->where(function ($pedidos) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        $this->applyCondition($pedidos, $key, $valor, '!=');
                    }
                }
            });

        if ($orderBy !== null) {
            $pedidos->orderBy(key($orderBy), reset($orderBy));
        }

        $pedidos = $pedidos->get();
        // // Antes de devolver la respuesta, carga los nombres de los usuarios correspondientes a 'order_by'
        // $pedidos->each(function ($pedido) {
        //     if ($pedido->confirmedBy) {
        //         $pedido->confirmed_by_user = $pedido->confirmedBy->username; // Ajusta según tu estructura real
        //     }
        // });

        return response()->json([
            'data' => $pedidos,
            'total' => $pedidos->count(),
        ]);
    }

    // public function exportOrdersToExcel(Request $request)
    // {
    //     $data = $request->json()->all();
    //     $startDate = $data['start'];
    //     $endDate = $data['end'];
    //     $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
    //     $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
    //     $searchTerm = $data['search'];
    //     $filteFields = $searchTerm != "" ? $data['or'] : [];
    //     $Map = $data['and'];
    //     $not = $data['not'];
    //     $orderBy = isset($data['sort']) ? explode(':', $data['sort']) : null;

    //     // Fetch orders with filters applied
    //     $pedidos = PedidosShopify::with([
    //         'operadore.up_users',
    //         'transportadora',
    //         'users.vendedores',
    //         'novedades',
    //         'pedidoFecha',
    //         'ruta',
    //         'subRuta',
    //         'confirmedBy',
    //         'pedidoCarrier'
    //     ])
    //         ->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
    //         ->when($searchTerm, function ($query) use ($filteFields, $searchTerm) {
    //             $query->where(function ($query) use ($filteFields, $searchTerm) {
    //                 foreach ($filteFields as $field) {
    //                     $query->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
    //                 }
    //             });
    //         })
    //         ->when($Map, function ($query) use ($Map) {
    //             foreach ($Map as $condition) {
    //                 foreach ($condition as $key => $valor) {
    //                     $this->applyCondition($query, $key, $valor);
    //                 }
    //             }
    //         })
    //         ->when($not, function ($query) use ($not) {
    //             foreach ($not as $condition) {
    //                 foreach ($condition as $key => $valor) {
    //                     $this->applyCondition($query, $key, $valor, '!=');
    //                 }
    //             }
    //         });

    //     // Apply sorting if specified
    //     if ($orderBy) {
    //         $field = $orderBy[0];
    //         $direction = strtoupper($orderBy[1]) === 'DESC' ? 'DESC' : 'ASC';
    //         $pedidos->orderBy($field, $direction);
    //     }

    //     $pedidos = $pedidos->get();

    //     // Create Excel document
    //     $spreadsheet = new Spreadsheet();
    //     $sheet = $spreadsheet->getActiveSheet();

    //     // Add title row
    //     $sheet->setCellValue('A1', 'EASY ECOMMERCE - REPORTE AUDITORIA');
    //     $sheet->mergeCells('A1:U1');
    //     $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    //     $sheet->getRowDimension('1')->setRowHeight(25);

    //     // Leave a blank row for spacing
    //     $sheet->setCellValue('A2', '');

    //     // Define headers
    //     $headers = [
    //         'ID Pedido',
    //         'Tienda',
    //         'Fecha Ingreso Pedido',
    //         'Fecha de Confirmación',
    //         'Fecha Entrega',
    //         'Marca Tiempo Envio',
    //         'Código',
    //         'Nombre Cliente',
    //         'Ciudad',
    //         'Status',
    //         'Transportadora',
    //         'Ruta',
    //         'Subruta',
    //         'Operador',
    //         'Observación',
    //         'Comentario',
    //         'Estado Interno',
    //         'Estado Logístico',
    //         'Estado Devolución',
    //         'Costo Transportadora',
    //         'Costo EasyEcommerce'
    //     ];

    //     $columnWidths = [12, 15, 20, 20, 20, 18, 15, 25, 15, 12, 20, 15, 15, 15, 25, 25, 18, 18, 18, 20, 20];

    //     foreach ($headers as $key => $header) {
    //         $column = chr(65 + $key); 
    //         $sheet->setCellValue($column . '3', $header);
    //         $sheet->getColumnDimension($column)->setWidth($columnWidths[$key]); 
    //     }
    //     $sheet->getStyle('A3:U3')->getFont()->setBold(true);

    //     // Start filling data from row 4
    //     $row = 4;
    //     foreach ($pedidos as $pedido) {
    //         $numeroOrden = $pedido->numero_orden;

    //         if (isset($pedido['Users']) && count($pedido['Users']) > 0 && isset($pedido['Users'][0]["vendedores"]) && count($pedido['Users'][0]["vendedores"]) > 0) {
    //             $nombreComercial = $pedido["Users"][0]["vendedores"][0]["nombre_comercial"];
    //         } else {
    //             $nombreComercial = $pedido->tienda_temporal;
    //         }

    //         $codigoPedido = "{$nombreComercial}-{$numeroOrden}";

    //         if (isset($pedido['subRuta']) && count($pedido['subRuta']) > 0) {
    //             $subRutaTitulo = $pedido['subRuta'][0]['titulo'];
    //         } else {
    //             $subRutaTitulo = 'No disponible';
    //         }


    //         if (isset($pedido['transportadora']) && count($pedido['transportadora']) > 0) {
    //             $transportadora = $pedido['transportadora'][0]['nombre'];
    //         }
    //         if (isset($pedido['pedidoCarrier']) && count($pedido['pedidoCarrier']) > 0) {
    //             $transportadora = $pedido['pedidoCarrier'][0]["Carrier"]["name"];
    //         }

    //         if (isset($pedido['operadore']) && count($pedido['operadore']) > 0 && isset($pedido['operadore'][0]["up_users"]) && count($pedido['operadore'][0]["up_users"]) > 0) {
    //             $operador = $pedido['operadore'][0]['up_users'][0]['username'];
    //         } else {
    //             $operador = 'No disponible';
    //         }

    //         if (isset($pedido['Ruta']) && count($pedido['Ruta']) > 0) {
    //             $rutaTitulo = $pedido['Ruta'][0]['titulo'];
    //         } else {
    //             $rutaTitulo = 'No disponible';
    //         }

    //         // Fill each row with data
    //         $sheet->fromArray([
    //             $pedido->id,
    //             $nombreComercial,
    //             $pedido->marca_t_i,
    //             $pedido->fecha_confirmacion,
    //             $pedido->fecha_entrega,
    //             $pedido->marca_tiempo_envio,
    //             $codigoPedido,
    //             $pedido->nombre_shipping,
    //             $pedido->ciudad_shipping,
    //             $pedido->status,
    //             $transportadora,
    //             $rutaTitulo,
    //             $subRutaTitulo,
    //             $operador,
    //             $pedido->observacion,
    //             $pedido->comentario,
    //             $pedido->estado_interno,
    //             $pedido->estado_logistico,
    //             $pedido->estado_devolucion,
    //             $pedido->costo_transportadora,
    //             $pedido->costo_envio
    //         ], null, 'A' . $row);

    //         $row++;
    //     }

    //     // Set filename
    //     $filename = "test.xlsx";
    //     $writer = new Xlsx($spreadsheet);

    //     // Configure response for download
    //     return response()->streamDownload(function () use ($writer) {
    //         $writer->save('php://output');
    //     }, $filename, [
    //         'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    //         'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    //     ]);
    // }

    public function exportOrdersToExcel(Request $request)
    {
        // Configura el límite de tiempo y memoria
        set_time_limit(300); // Aumenta el tiempo límite a 5 minutos
        ini_set('memory_limit', '512M'); // Ajusta el límite de memoria si es necesario

        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $searchTerm = $data['search'];
        $filteFields = $searchTerm != "" ? $data['or'] : [];
        $Map = $data['and'];
        $not = $data['not'];
        $orderBy = isset($data['sort']) ? explode(':', $data['sort']) : null;

        // Configura el archivo de Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'EASY ECOMMERCE - REPORTE AUDITORIA');
        $sheet->mergeCells('A1:U1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getRowDimension('1')->setRowHeight(25);
        $sheet->setCellValue('A2', '');

        // Define los encabezados
        $headers = [
            'ID Pedido',
            'Tienda',
            'Fecha Ingreso Pedido',
            'Fecha de Confirmación',
            'Fecha Entrega',
            'Marca Tiempo Envio',
            'Código',
            'Nombre Cliente',
            'Ciudad',
            'Status',
            'Transportadora',
            'Ruta',
            'Subruta',
            'Operador',
            'Observación',
            'Comentario',
            'Estado Interno',
            'Estado Logístico',
            'Estado Devolución',
            'Costo Transportadora',
            'Costo EasyEcommerce'
        ];
        $columnWidths = [12, 15, 20, 20, 20, 18, 15, 25, 15, 12, 20, 15, 15, 15, 25, 25, 18, 18, 18, 20, 20];
        foreach ($headers as $key => $header) {
            $column = chr(65 + $key);
            $sheet->setCellValue($column . '3', $header);
            $sheet->getColumnDimension($column)->setWidth($columnWidths[$key]);
        }
        $sheet->getStyle('A3:U3')->getFont()->setBold(true);

        // Inicializa variables para la paginación
        $page = 1;
        $pageSize = 500; // Ajusta el tamaño de cada lote
        $row = 4; // Comienza la inserción de datos desde la fila 4

        do {
            // Carga un lote de pedidos
            $pedidos = PedidosShopify::with([
                'operadore.up_users',
                'transportadora',
                'users.vendedores',
                'novedades',
                'pedidoFecha',
                'ruta',
                'subRuta',
                'confirmedBy',
                'pedidoCarrier'
            ])
                ->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
                ->when($searchTerm, function ($query) use ($filteFields, $searchTerm) {
                    $query->where(function ($query) use ($filteFields, $searchTerm) {
                        foreach ($filteFields as $field) {
                            $query->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    });
                })
                ->when($Map, function ($query) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            $this->applyCondition($query, $key, $valor);
                        }
                    }
                })
                ->when($not, function ($query) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            $this->applyCondition($query, $key, $valor, '!=');
                        }
                    }
                })
                ->skip(($page - 1) * $pageSize)
                ->take($pageSize)
                ->get();

            // Procesa cada pedido del lote
            foreach ($pedidos as $pedido) {
                $nombreComercial = isset($pedido->users[0]->vendedores[0]->nombre_comercial)
                    ? $pedido->users[0]->vendedores[0]->nombre_comercial
                    : $pedido->tienda_temporal;

                $codigoPedido = "{$nombreComercial}-{$pedido->numero_orden}";
                $subRutaTitulo = isset($pedido->subRuta[0]->titulo) ? $pedido->subRuta[0]->titulo : 'No disponible';
                $transportadora = isset($pedido->pedidoCarrier[0]->Carrier->name)
                    ? $pedido->pedidoCarrier[0]->Carrier->name
                    : (isset($pedido->transportadora[0]->nombre) ? $pedido->transportadora[0]->nombre : 'No disponible');
                $operador = isset($pedido->operadore[0]->up_users[0]->username)
                    ? $pedido->operadore[0]->up_users[0]->username
                    : 'No disponible';
                $rutaTitulo = isset($pedido->Ruta[0]->titulo) ? $pedido->Ruta[0]->titulo : 'No disponible';

                $sheet->fromArray([
                    $pedido->id,
                    $nombreComercial,
                    $pedido->marca_t_i,
                    $pedido->fecha_confirmacion,
                    $pedido->fecha_entrega,
                    $pedido->marca_tiempo_envio,
                    $codigoPedido,
                    $pedido->nombre_shipping,
                    $pedido->ciudad_shipping,
                    $pedido->status,
                    $transportadora,
                    $rutaTitulo,
                    $subRutaTitulo,
                    $operador,
                    $pedido->observacion,
                    $pedido->comentario,
                    $pedido->estado_interno,
                    $pedido->estado_logistico,
                    $pedido->estado_devolucion,
                    $pedido->costo_transportadora,
                    $pedido->costo_envio
                ], null, 'A' . $row);

                $row++;
            }

            $page++; // Avanza a la siguiente página de registros

        } while ($pedidos->isNotEmpty()); // Continua hasta que no haya más registros

        // Establece el nombre del archivo y configura la respuesta para la descarga
        $filename = "reporte_pedidos.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // public function exportOrdersToExcel(Request $request)
    // {
    //     // Recibir los datos del request
    //     $data = $request->json()->all();

    //     ExportOrdersJob::dispatch($data, "jeipige@gmail.com");  
    //     return response()->json([
    //         'message' => 'Report generation started. You will receive an email with the download link once its ready.'
    //     ]);
    // }



    private function applyCondition($pedidos, $key, $valor, $operator = '=')
    {
        $parts = explode("/", $key);
        $type = $parts[0];
        $filter = $parts[1];

        if (strpos($filter, '.') !== false) {
            $relacion = substr($filter, 0, strpos($filter, '.'));
            $propiedad = substr($filter, strpos($filter, '.') + 1);
            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
        } else {
            if ($type == "equals") {
                $pedidos->where($filter, $operator, $valor);
            } else {
                $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
            }
        }
    }

    // ! *********************************
    public function updateOrderStatusAndComment(Request $req)
    {
        $data = $req->json()->all();
        $iddepedido = $data['iddepedido'];
        $status = $data['status'];
        $comentario = $data['comentario'];

        $pedido = PedidosShopify::where('id', $iddepedido)->first();

        if (!$pedido) {
            return response()->json(['message' => 'No se encontró pedido con el ID especificado'], 404);
        }

        $pedido->status = $status;
        $pedido->comentario = $comentario;
        $pedido->save();

        return response()->json($pedido);
    }



    public function updateOrderInteralStatusLogisticLaravel(Request $req)
    {
        $data = $req->json()->all();
        $id = $data['id'];
        $estado_interno = $data['data'][0]['estado_interno'];
        $estado_logistico = $data['data'][1]['estado_logistico'];
        $pedido = PedidosShopify::with(['transportadora', 'users', 'users.vendedores', 'pedidoFecha', 'ruta'])
            ->where('id', $id)
            ->first();
        if (!$pedido) {
            return response()->json(['message' => 'No se encontraro pedido con el ID especificado'], 404);
        }
        $pedido->estado_interno = $estado_interno;
        $pedido->estado_logistico = $estado_logistico;
        $pedido->save();
        return response()->json($pedido);
    }
    public function updateOrderLogisticStatusPrintLaravel(Request $req)
    {
        $data = $req->json()->all();
        $id = $data['id'];
        $estado_interno = $data['data'][0]['estado_interno'];
        $estado_logistico = $data['data'][1]['estado_logistico'];
        $fecha_entega = $data['data'][2]['fecha_entrega'];
        $marca_tiempo_envio = $data['data'][3]['marca_tiempo_envio'];
        $pedido = PedidosShopify::with(['transportadora', 'users', 'users.vendedores', 'pedidoFecha', 'ruta'])
            ->where('id', $id)
            ->first();
        if (!$pedido) {
            return response()->json(['message' => 'No se encontraro pedido con el ID especificado'], 404);
        }
        $pedido->estado_interno = $estado_interno;
        $pedido->estado_logistico = $estado_logistico;
        $pedido->fecha_entega = $fecha_entega;
        $pedido->marca_tiempo_envio = $marca_tiempo_envio;
        $pedido->save();
        return response()->json($pedido);
    }

    public function getOrdersForPrintedGuidesLaravel(Request $request)
    {
        try {
            $data = $request->json()->all();
            $pageSize = $data['page_size'];
            $pageNumber = $data['page_number'];
            $searchTerm = $data['search'];
            if ($searchTerm != "") {
                $filteFields = $data['or'];
            } else {
                $filteFields = [];
            }
            // ! *************************************
            $Map = $data['and'];
            $not = $data['not'];
            //*
            $relationsToInclude = $data['include'];
            $relationsToExclude = $data['exclude'];
            // ! *************************************

            // $pedidos = PedidosShopify::with(['transportadora', 'users', 'users.vendedores', 'pedidoFecha', 'ruta', 'printedBy', 'sentBy', 'product.warehouse.provider'])
            $pedidos = PedidosShopify::with([
                'transportadora',
                'users',
                'users.vendedores',
                'pedidoFecha',
                'ruta',
                'printedBy',
                'sentBy',
                'product_s.warehouses.provider',
                'carrierExternal',
                'ciudadExternal',
                'pedidoCarrier',
                'vendor'
            ])
                ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                    foreach ($filteFields as $field) {
                        if (strpos($field, '.') !== false) {
                            $relacion = substr($field, 0, strpos($field, '.'));
                            $propiedad = substr($field, strpos($field, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                        } else {
                            $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    }
                })
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if ($valor === null) {
                                $pedidos->whereNull($key);
                            } else {
                                if (strpos($key, '.') !== false) {
                                    $relacion = substr($key, 0, strpos($key, '.'));
                                    $propiedad = substr($key, strpos($key, '.') + 1);
                                    $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                                } else {
                                    $pedidos->where($key, '=', $valor);
                                }
                            }
                        }
                    }


                    // foreach ($Map as $condition) {
                    //     foreach ($condition as $key => $valor) {
                    //         $parts = explode("/", $key);
                    //         $type = $parts[0];
                    //         $filter = $parts[1];
                    //         if (strpos($filter, '.') !== false) {
                    //             $relacion = substr($filter, 0, strpos($filter, '.'));
                    //             $propiedad = substr($filter, strpos($filter, '.') + 1);
                    //             $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                    //         } else {
                    //             if ($type == "equals") {
                    //                 $pedidos->where($filter, '=', $valor);
                    //             } else {
                    //                 $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
                    //             }
                    //         }
                    //     }
                    // }
                }))
                ->where((function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if ($valor === null) {
                                $pedidos->whereNotNull($key);
                            } else {
                                if (strpos($key, '.') !== false) {
                                    $relacion = substr($key, 0, strpos($key, '.'));
                                    $propiedad = substr($key, strpos($key, '.') + 1);
                                    $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                                } else {
                                    $pedidos->where($key, '!=', $valor);
                                }
                            }
                        }
                    }
                }));

            if (isset($relationsToInclude)) {
                // error_log("IS relationsToInclude");
                foreach ($relationsToInclude as $relation) {
                    // error_log("Include relation: $relation");
                    $pedidos->whereHas($relation);
                }
            }

            if (isset($relationsToExclude)) {
                // error_log("IS relationsToInclude");
                foreach ($relationsToExclude as $relation) {
                    // error_log("Exclude relation: $relation");
                    $pedidos->whereDoesntHave($relation);
                }
            }

            // ! Ordenamiento ********************************** 
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
                $pedidos->orderBy(key($orderByText), reset($orderByText));
            } else {
                $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
            }
            // ! **************************************************
            $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);
            return response()->json($pedidos);
        } catch (\Exception $e) {
            error_log("getOrdersForPrintedGuidesLaravel_error: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByID(Request $req)
    {
        error_log("getByID");

        $data = $req->json()->all();
        $id = $data['id'];
        $populate = $data['populate'];
        $pedido = PedidosShopify::with($populate)
            ->where('id', $id)
            ->first();
        if (!$pedido) {
            return response()->json(['message' => 'No se encontraro pedido con el ID especificado'], 404);
        }
        return response()->json($pedido);
    }

    // --------------------------------
    public function getPrincipalOrdersSellersFilterLaravel(Request $request)
    {
        $data = $request->json()->all();
        $populate = $data['populate'];
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        if ($searchTerm != "") {
            $filteFields = $data['or']; // && SOLO QUITO  ((||)&&())
        } else {
            $filteFields = [];
        }
        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************
        // 'users',

        // $pedidos = PedidosShopify::with(['operadore.up_users', 'transportadora', 'users', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta'])
        $pedidos = PedidosShopify::with($populate)
            // ->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))
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
        // ! Ordenamiento ********************************** 
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
            $pedidos->orderBy(key($orderByText), reset($orderByText));
        } else {
            $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }

    public function getByDateRange(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $populate = $data['populate'];
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $dateFilter = $data["date_filter"];



        // $selectedFilter = "fecha_entrega";
        // if ($dateFilter != "FECHA ENTREGA "
        // //   $dateFilter != "FECHA DEVOLUCION"
        //  ) {
        //     $selectedFilter = "marca_tiempo_envio";
        // }
        // //  else if($dateFilter == "FECHA DEVOLUCION"){
        //     // $selectedFilter = "marca_t_d_t";
        // // }

        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }

        if ($searchTerm != "") {
            $filteFields = $data['or']; // && SOLO QUITO  ((||)&&())
        } else {
            $filteFields = [];
        }

        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************


        if ($dateFilter == "FECHA PAGO RECIBIDO") {
            error_log("getByDateRange_FECHA_PAGO_RECIBIDO");

            $pedidos = PedidosShopify::with($populate)
                ->whereNotNull('gestioned_payment_cost_delivery')
                ->whereRaw(
                    "
                    DATE(JSON_UNQUOTE(JSON_EXTRACT(gestioned_payment_cost_delivery, '$.m_t_g'))) 
                    BETWEEN ? AND ?",
                    [$startDateFormatted, $endDateFormatted]
                )
                ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                    foreach ($filteFields as $field) {
                        if (strpos($field, '.') !== false) {
                            $relacion = substr($field, 0, strpos($field, '.'));
                            $propiedad = substr($field, strpos($field, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                        } else {
                            $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    }
                })
                ->where(function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                if ($key === 'gestioned_payment_cost_delivery') {
                                    $pedidos->whereJsonContains('gestioned_payment_cost_delivery->state', $valor);
                                } else {
                                    $pedidos->where($key, '=', $valor);
                                }
                            }
                        }
                    }
                })
                ->where(function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHasNeg($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                });
        } else {
            $pedidos = PedidosShopify::with($populate)
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
                ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                    foreach ($filteFields as $field) {
                        if (strpos($field, '.') !== false) {
                            $relacion = substr($field, 0, strpos($field, '.'));
                            $propiedad = substr($field, strpos($field, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                        } else {
                            $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    }
                })
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                if ($key === 'gestioned_payment_cost_delivery') {
                                    $pedidos->whereJsonContains('gestioned_payment_cost_delivery->state', $valor);
                                } else {
                                    $pedidos->where($key, '=', $valor);
                                }
                                // $pedidos->where($key, '=', $valor);
                            }
                        }
                    }
                }))->where((function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHasNeg($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                }));
        }
        // ! Ordenamiento ********************************** 
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
            $pedidos->orderBy(key($orderByText), reset($orderByText));
        } else {
            $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }

    public function getRefererTotalValue(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $populate = $data['populate'];
        $searchTerm = $data['search'];
        $dateFilter = $data["date_filter"];
        // $pageNumber = $data['page_number'];


        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }

        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        $Map = $data['and'];
        $not = $data['not'];

        $pedidos = PedidosShopify::with($populate)
            ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where(function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            if ($key === 'gestioned_payment_cost_delivery') {
                                $pedidos->whereJsonContains('gestioned_payment_cost_delivery->state', $valor);
                            } else {
                                $pedidos->where($key, '=', $valor);
                            }
                        }
                    }
                }
            })
            ->where(function ($pedidos) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        $pedidos->where($key, '!=', $valor);
                    }
                }
            });

        // Calcular la suma total de `value_referer`
        $totalValueReferer = $pedidos->sum('value_referer');

        // Retornar la suma total
        return response()->json(['total_value_referer' => $totalValueReferer]);
    }

    public function getOrderbyId(Request $req, $id)
    {
        error_log("getOrderbyId");
        $pedido = PedidosShopify::with([
            'operadore.up_users',
            'transportadora',
            'users.vendedores',
            'novedades',
            'pedidoFecha',
            'ruta',
            'subRuta',
            'carrierExternal',
            'ciudadExternal',
            "pedidoCarrier"
        ])
            ->where('id', $id)
            ->first();
        if (!$pedido) {
            return response()->json(['message' => 'No se encontraro pedido con el ID especificado'], 404);
        }
        // return response()->json(['data' => $pedido]);
        return response()->json($pedido);
    }


    //  TODO: en desarrollo ↓↓↓↓
    public function createDateOrderLaravel(Request $req)
    {
        $data = $req->json()->all();
        $fechaActual = $data['fecha'];
        $pedidoFecha = PedidoFecha::where('fecha', $fechaActual)->first();
        $newpedidoFecha = "";
        if (!$pedidoFecha) {
            $newpedidoFecha = new PedidoFecha();
            $newpedidoFecha->fecha = $fechaActual;
            $newpedidoFecha->save();
            return response()->json($newpedidoFecha);
        }
        return response()->json($pedidoFecha);
    }

    public function postOrdersPricipalOrders(Request $req)
    {
        DB::beginTransaction();

        // all data
        $data = $req->json()->all();
        $generatedBy = $data['generatedBy'];
        $IdComercial = $data['IdComercial'];
        $Name_Comercial = $data['Name_Comercial'];
        $NombreShipping = $data['NombreShipping'];
        $CiudadShipping = $data['CiudadShipping'];
        $DireccionShipping = $data['DireccionShipping'];
        $TelefonoShipping = $data['TelefonoShipping'];
        $PrecioTotal = $data['PrecioTotal'];
        $formattedPrice = str_replace(",", ".", str_replace(["$", " "], "", $PrecioTotal));
        $ProductoP = $data['ProductoP'];
        $ProductoExtra = $data['ProductoExtra'];
        $Cantidad_Total = $data['Cantidad_Total'];
        if ($data['Observacion'] != null) {
            $Observacion = $data['Observacion'];
        } else {
            $Observacion = "";
        }
        $Tienda_Temporal = $data['Name_Comercial'];
        $newrouteId = $data['ruta'];
        $newtransportadoraId = $data['transportadora'];

        $Marca_T_I = date("d/m/Y H:i");
        $Fecha_Confirmacion = date("d/m/Y H:i");


        try {
            //code...
            $numOrderstart = 1001; // Número inicial sin ceros a la izquierda
            $manualOrders = PedidosShopify::where('id_comercial', $IdComercial)
                ->where('numero_orden', 'like', 'E%')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($manualOrders->isNotEmpty()) {
                $encontrado = false;
                foreach ($manualOrders as $order) {
                    if ($order->numero_orden === "E001001") {
                        $encontrado = true;
                        break;
                    }
                }

                $lastOrder = $manualOrders->first();
                if ($encontrado) {
                    $lastOrderNumero = $lastOrder->numero_orden;
                    preg_match('/\d+/', $lastOrderNumero, $matches);
                    $numeroExtraido = $matches[0];
                    $nextOrderNumero = $numeroExtraido + 1;
                    $NumeroOrden = "E" . sprintf("%06d", $nextOrderNumero); // Aplicar ceros a la izquierda si es necesario
                } else {
                    $NumeroOrden = "E" . sprintf("%06d", $numOrderstart);
                }
            } else {
                $NumeroOrden = "E" . sprintf("%06d", $numOrderstart);
            }

            // error_log("numero_orden a crear: $NumeroOrden");


            $currentDateTime = date('Y-m-d H:i:s');

            $createOrder = new PedidosShopify();
            $createOrder->numero_orden = $NumeroOrden;
            $createOrder->direccion_shipping = $DireccionShipping;
            $createOrder->nombre_shipping = $NombreShipping;
            $createOrder->telefono_shipping = $TelefonoShipping;
            $createOrder->precio_total = $formattedPrice;
            $createOrder->observacion = $Observacion;
            $createOrder->ciudad_shipping = $CiudadShipping;
            $createOrder->id_comercial = $IdComercial;
            $createOrder->producto_p = $ProductoP;
            $createOrder->producto_extra = $ProductoExtra;
            $createOrder->cantidad_total = $Cantidad_Total;
            $createOrder->name_comercial = $Name_Comercial;
            $createOrder->tienda_temporal = $Tienda_Temporal;
            $createOrder->marca_t_i = $Marca_T_I;
            $createOrder->estado_interno = "CONFIRMADO";
            $createOrder->status = "PEDIDO PROGRAMADO";
            $createOrder->estado_logistico = 'PENDIENTE';
            $createOrder->estado_pagado = 'PENDIENTE';
            $createOrder->estado_pago_logistica = 'PENDIENTE';
            $createOrder->estado_devolucion = 'PENDIENTE';
            $createOrder->do = 'PENDIENTE';
            $createOrder->dt = 'PENDIENTE';
            $createOrder->dl = 'PENDIENTE';
            $createOrder->fecha_confirmacion = $Fecha_Confirmacion;
            $createOrder->confirmed_by = $generatedBy;
            $createOrder->confirmed_at = $currentDateTime;

            $createOrder->save();
            // error_log("**********process 1: created order**********");

            $searchDate = PedidoFecha::where('fecha', now()->format('d/m/Y'))->get();
            $pedidoShopifyOrder = 0;
            // IF DATE ORDER NOT EXIST CREATE ORDER AND ADD ID ELSE IF ONLY ADD DATE ORDER ID VALUE
            if ($searchDate->isEmpty()) {
                // Crea un nuevo registro de fecha
                $newDate = new PedidoFecha();
                $newDate->fecha = now()->format('d/m/Y');
                $newDate->save();

                // Obtén el ID del nuevo registro
                $dateOrder = $newDate->id;
            } else {
                // Si la fecha existe, obtén el ID del primer resultado
                $dateOrder = $searchDate[0]->id;

                $ultimoPedidoFechaLink = PedidosShopifiesPedidoFechaLink::where('pedido_fecha_id', $dateOrder)
                    ->orderBy('pedidos_shopify_order', 'desc')
                    ->first();

                if ($ultimoPedidoFechaLink) {
                    $pedidoShopifyOrder = $ultimoPedidoFechaLink->pedidos_shopify_order + 1;
                } else {
                    $pedidoShopifyOrder = 1;
                }
            }

            $createPedidoFecha = new PedidosShopifiesPedidoFechaLink();
            $createPedidoFecha->pedidos_shopify_id = $createOrder->id;
            $createPedidoFecha->pedido_fecha_id = $dateOrder;
            $createPedidoFecha->pedidos_shopify_order = $pedidoShopifyOrder;
            $createPedidoFecha->save();

            // error_log("**********process 2: created links_order with pedido_fecha**********");

            $createUserPedido = new UpUsersPedidosShopifiesLink();
            $createUserPedido->user_id = $IdComercial;
            $createUserPedido->pedidos_shopify_id = $createOrder->id;
            $createUserPedido->save();

            // error_log("**********process 3: created links_order with up_users**********");

            $createPedidoRuta = new PedidosShopifiesRutaLink();
            $createPedidoRuta->pedidos_shopify_id = $createOrder->id;
            $createPedidoRuta->ruta_id = $newrouteId;
            $createPedidoRuta->save();

            $createPedidoTransportadora = new PedidosShopifiesTransportadoraLink();
            $createPedidoTransportadora->pedidos_shopify_id = $createOrder->id;
            $createPedidoTransportadora->transportadora_id = $newtransportadoraId;
            $createPedidoTransportadora->save();

            // error_log("**********process 4: created order with route transpo**********");


            DB::commit();
            // return response()->json(['message' => 'Pedido creado exitosamente'], 201);
            return response()->json([
                "data" => $createOrder,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateOrderInfoSellerLaravel(Request $req)
    {
        $data = $req->json()->all();
        $id = $data['id'];
        $ciudad_shipping = $data["ciudad_shipping"];
        $nombre_shipping = $data["nombre_shipping"];
        $direccion_shipping = $data["direccion_shipping"];
        $telefono_shipping = $data["telefono_shipping"];
        $cantidad_total = $data["cantidad_total"];
        $producto_p = $data["producto_p"];
        $producto_extra = $data["producto_extra"];
        $precio_total = $data["precio_total"];
        $observacion = $data["observacion"];
        $provincia_shipping = $data["provincia_shipping"];

        $pedido = PedidosShopify::with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta'])
            ->where('id', $id)
            ->first();

        if (!$pedido) {
            return response()->json(['message' => 'No se pudo actualizar el pedido con el ID especificado'], 404);
        }

        $pedido->ciudad_shipping = $ciudad_shipping;
        $pedido->nombre_shipping = $nombre_shipping;
        $pedido->direccion_shipping = $direccion_shipping;
        $pedido->telefono_shipping = $telefono_shipping;
        $pedido->cantidad_total = $cantidad_total;
        $pedido->producto_p = $producto_p;
        $pedido->producto_extra = $producto_extra;
        $pedido->precio_total = $precio_total;
        $pedido->observacion = $observacion;
        $pedido->provincia_shipping = $provincia_shipping;
        $pedido->save();

        return response()->json($pedido);
    }
    public function updateOrderInternalStatus(Request $req)
    {
        $data = $req->json()->all();
        $id = $data['id'];
        $estadoInterno = $data['estado_interno'];
        $fechaConfirmacion = $data['fecha_confirmacion'];
        $nameComercial = $data['name_comercial'];
        $pedido = PedidosShopify::with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta'])
            ->where('id', $id)
            ->first();

        if (!$pedido) {
            return response()->json(['message' => 'No se pudo actualizar pedido con el ID especificado'], 404);
        }

        $pedido->fecha_confirmacion = $fechaConfirmacion;
        $pedido->estado_interno = $estadoInterno;
        $pedido->name_comercial = $nameComercial;
        $pedido->save();

        return response()->json($pedido);
    }
    public function updateDateandStatus(Request $req)
    {
        error_log("updateDateandStatus");
        $data = $req->json()->all();
        // $input = json_decode($req->getContent(), true);
        // error_log('allRequest: ' . json_encode($input));

        $id = $data['id'];
        $fecha_entrega = $data['data'][0]['fecha_Entrega']; // Accede al valor de fecha_Entrega
        $status = $data['data'][1]['status']; // Accede al valor de status

        $pedido = PedidosShopify::with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta'])
            ->where('id', $id)
            ->first();

        if (!$pedido) {
            return response()->json(['message' => 'No se encontraro pedido con el ID especificado'], 404);
        }

        DB::beginTransaction();
        try {

            $pedido->fecha_entrega = $fecha_entrega;
            $pedido->status = $status;

            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $commentHist = "";
            if ($status == "REAGENDADO") {
                $commentHist = "Nueva fecha entrega: " . $fecha_entrega;
            }
            error_log("commentHist $commentHist");
            $newHistory = [
                "area" => "status",
                "status" => $status,
                "timestap" => date('Y-m-d H:i:s'),
                "comment" =>  $commentHist,
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($pedido->status_history === null || $pedido->status_history === '[]') {
                $pedido->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($pedido->status_history, true);

                $existingHistory[] = $newHistory;

                $pedido->status_history = json_encode($existingHistory);
            }
            $pedido->save();

            DB::commit();

            // return response()->json(['data' => $pedido]);
            return response()->json($pedido);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("ERROR_updateDateandStatus: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getReturnSellers(Request $request)
    {
        $data = $request->json()->all();
        // $startDate = $data['start'];
        // $endDate = $data['end'];
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        // $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $populate = $data['populate'];
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];

        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        // ! *************
        $orConditions = $data['or_multiple'];
        $Map = $data['and'];
        $not = $data['not'];

        //*
        $relationsToInclude = $data['include'];
        $relationsToExclude = $data['exclude'];
        // ! *************

        $pedidos = PedidosShopify::with($populate)
            ->where((function ($pedidos) use ($orConditions) {
                foreach ($orConditions as $condition) {
                    foreach ($condition as $field => $values) {
                        $pedidos->whereIn($field, $values);
                    }
                }
            }))
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            //->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
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

        if (isset($relationsToInclude)) {
            foreach ($relationsToInclude as $relation) {
                $pedidos->whereHas($relation);
            }
        }

        if (isset($relationsToExclude)) {
            foreach ($relationsToExclude as $relation) {
                $pedidos->whereDoesntHave($relation);
            }
        }

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
            $pedidos->orderBy(key($orderByText), reset($orderByText));
        } else {
            $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! ******************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);
        return response()->json($pedidos);
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
    private function recursiveWhereHasNeg($query, $relation, $property, $searchTerm, $operator = '!=')
    {
        if ($searchTerm == "null") {
            $searchTerm = null;
        }
        if (strpos($property, '.') !== false) {
            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm, $operator) {
                $this->recursiveWhereHasNeg($q, $nestedRelation, $nestedProperty, $searchTerm, $operator);
            });
        } else {
            $query->whereHas($relation, function ($q) use ($property, $searchTerm, $operator) {
                $q->where($property, $operator, $searchTerm);
            });
        }
    }

    private function recursiveWhere($query, $key, $property, $valor)
    {
        if ($valor == "null") {
            $valor = null;
        }
        if (strpos($property, '.') !== false) {
            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            $query->whereHas($key, function ($query) use ($nestedRelation, $nestedProperty, $valor) {
                $this->recursiveWhereHas($query, $nestedRelation, $nestedProperty, $valor);
            });
        } else {
            $query->where($key, '=', $valor);
        }
    }


    public function store(Request $request)
    {
        $pedido = PedidosShopify::create($request->all());
        return response()->json($pedido, Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        $pedido = PedidosShopify::findOrFail($id);
        $pedido->update($request->all());
        return response()->json($pedido, Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $pedido = PedidosShopify::findOrFail($id);
        $pedido->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function getCountersLogistic(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $Map = $data['and'];
        $not = $data['not'];

        $result = PedidosShopify::with(['operadore.up_users'])

            ->with('transportadora')
            ->with('users.vendedores')
            ->with('novedades')
            ->with('pedidoFecha')
            ->with('ruta')
            ->with('subRuta')
            ->whereRaw("STR_TO_DATE(marca_t_i, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))->get();


        $stateTotals = [
            'ENTREGADO' => 0,
            'NO ENTREGADO' => 0,
            'NOVEDAD' => 0,
            'REAGENDADO' => 0,
            'EN RUTA' => 0,
            'EN OFICINA' => 0,
            'PEDIDO PROGRAMADO' => 0,
            'TOTAL' => 0
        ];
        $counter = 0;
        foreach ($result as $row) {
            $counter++;
            $estado = $row->status;
            $stateTotals[$estado] = $row->count;
            $stateTotals['TOTAL'] += $row->count;
        }

        return response()->json([
            'data' => $stateTotals,
        ]);
    }

    public function getCounters(Request $request)
    {
        error_log("getCounters.....");
        $data = $request->json()->all();
        $dateFilter = $data["date_filter"];
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $Map = $data['and'];
        $not = $data['not'];

        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }

        if ($dateFilter == "FECHA PAGO RECIBIDO") {
            error_log("getCounterse_FECHA_PAGO_RECIBIDO");
            $countProductWarehouseNotNull = PedidosShopify::with(['operadore.up_users', 'pedidoCarrier'])
                ->with('subRuta')
                ->whereNotNull('gestioned_payment_cost_delivery')
                ->whereRaw(
                    "
                    DATE(JSON_UNQUOTE(JSON_EXTRACT(gestioned_payment_cost_delivery, '$.m_t_g'))) 
                    BETWEEN ? AND ?",
                    [$startDateFormatted, $endDateFormatted]
                )
                ->where('value_product_warehouse', '>', 0)
                ->where("status", "ENTREGADO")
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '=', $valor);
                            }
                        }
                    }
                }))
                ->count();
        } else {
            $countProductWarehouseNotNull = PedidosShopify::with(['operadore.up_users', 'pedidoCarrier'])
                ->with('subRuta')
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
                ->where('value_product_warehouse', '>', 0)
                ->where("status", "ENTREGADO")
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '=', $valor);
                            }
                        }
                    }
                }))
                ->count();
        }

        if ($dateFilter == "FECHA PAGO RECIBIDO") {
            error_log("FECHA PAGO RECIBIDO");

            $result = PedidosShopify::with(['operadore.up_users'])
                ->with('transportadora')
                ->with('users.vendedores')
                ->with('novedades')
                ->with('pedidoFecha')
                ->with('ruta')
                ->with('pedidoCarrier')
                ->whereNotNull('gestioned_payment_cost_delivery')
                ->whereRaw(
                    "
                    DATE(JSON_UNQUOTE(JSON_EXTRACT(gestioned_payment_cost_delivery, '$.m_t_g'))) 
                    BETWEEN ? AND ?",
                    [$startDateFormatted, $endDateFormatted]
                )                // ->selectRaw('status, COUNT(*) as count')
                // ->groupBy('status')
                ->selectRaw('status, estado_devolucion, COUNT(*) as count') // Incluye estado_devolucion si es necesario
                ->groupBy('status', 'estado_devolucion')
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '=', $valor);
                            }
                        }
                    }
                }))
                ->where((function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHasNeg($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                }))
                ->get();
        } else {
            $result = PedidosShopify::with(['operadore.up_users'])
                ->with('transportadora')
                ->with('users.vendedores')
                ->with('novedades')
                ->with('pedidoFecha')
                ->with('ruta')
                ->with('pedidoCarrier')
                ->with('subRuta')->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
                // ->selectRaw('status, COUNT(*) as count')
                // ->groupBy('status')
                ->selectRaw('status, estado_devolucion, COUNT(*) as count') // Incluye estado_devolucion si es necesario
                ->groupBy('status', 'estado_devolucion')
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '=', $valor);
                            }
                        }
                    }
                }))
                ->where((function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHasNeg($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                }))
                ->get();
        }

        $stateTotals = [
            'ENTREGADO' => 0,
            'NO ENTREGADO' => 0,
            'NOVEDAD' => 0,
            'NOVEDAD RESUELTA' => 0,
            'REAGENDADO' => 0,
            'EN RUTA' => 0,
            'EN OFICINA' => 0,
            'PEDIDO PROGRAMADO' => 0,
            'TOTAL' => 0,
            'P. PROVEEDOR' => 0,
            'DEVOLUCION' => 0,
        ];
        $stateTotals['P. PROVEEDOR'] = $countProductWarehouseNotNull;

        $counter = 0;
        foreach ($result as $row) {
            $counter++;
            $estado = $row->status;
            // $stateTotals[$estado] = $row->count;
            if ($estado === 'NOVEDAD') {
                if ($row->estado_devolucion != 'PENDIENTE') {
                    $stateTotals['DEVOLUCION'] += $row->count;
                }
            } else if ($estado === 'NO ENTREGADO') {
                if ($row->estado_devolucion != 'PENDIENTE') {
                    $stateTotals['DEVOLUCION'] += $row->count;
                }
            }
            $stateTotals[$estado] += $row->count;


            $stateTotals['TOTAL'] += $row->count;
        }

        return response()->json([
            'data' => $stateTotals,
        ]);
    }

    public function getProductsDashboardRoutesCount(Request $request)
    {
        $data = $request->json()->all();
        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $Map = $data['and'];

        $searchTerm = $data['search'];
        if ($searchTerm != "") {
            $filteFields = $data['or'];
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        $routeId = $data['route_id'];
        $pedidos = PedidosShopify::with([
            'operadore.up_users:id',
            'transportadora',
            'pedidoFecha',
            'ruta',
            'subRuta'
        ])
            ->whereRaw("STR_TO_DATE(marca_t_i, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))
            ->whereHas('ruta', function ($query) use ($routeId) {
                $query->where('rutas.id', $routeId); // Califica 'id' con 'rutas'
            })
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();


        return response()->json([
            'data' => $pedidos
        ]);
    }


    public function CalculateValuesTransport(Request $request)
    {
        $data = $request->json()->all();
        $startDate = Carbon::createFromFormat('j/n/Y', $data['start'])->format('Y-m-d');
        $endDate = Carbon::createFromFormat('j/n/Y', $data['end'])->format('Y-m-d');
        $Map = $data['and'];
        $not = $data['not'];
        $dateFilter = $data["date_filter"];
        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }
        $from = $data["from"];


        $query = PedidosShopify::query()
            ->with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta'])
            ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
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
        //     ;

        // $this->applyConditions($query, $Map);
        // $this->applyConditions($query, $not, true);


        $query1 = clone $query;
        $query2 = clone $query;
        if ($from == "carrier") {
            $summary = [
                'totalValoresRecibidos' => $query1->whereIn('status', ['ENTREGADO'])->sum(DB::raw('REPLACE(precio_total, ",", "")')),

                //  este sirve para costo envio
                // 'totalShippingCost' => $query
                // ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
                // ->join('up_users_pedidos_shopifies_links', 'pedidos_shopifies.id', '=', 'up_users_pedidos_shopifies_links.pedidos_shopify_id')
                // ->join('up_users', 'up_users_pedidos_shopifies_links.user_id', '=', 'up_users.id')
                // ->join('up_users_vendedores_links', 'up_users.id', '=', 'up_users_vendedores_links.user_id')
                // ->join('vendedores', 'up_users_vendedores_links.vendedor_id', '=', 'vendedores.id')->get()
                //  ->sum(DB::raw('REPLACE(vendedores.costo_envio, ",", "")'))
                // for carrier
                'totalShippingCost' => $query2
                    ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
                    ->join('pedidos_shopifies_transportadora_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_transportadora_links.pedidos_shopify_id')
                    ->join('transportadoras', 'pedidos_shopifies_transportadora_links.transportadora_id', '=', 'transportadoras.id')
                    ->sum(DB::raw('REPLACE(transportadoras.costo_transportadora, ",", "")'))
            ];
        } else {
            $summary = [
                'totalValoresRecibidos' => $query1->whereIn('status', ['ENTREGADO'])->sum(DB::raw('REPLACE(precio_total, ",", "")')),

                //for operator
                'totalShippingCost' => $query2
                    ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
                    ->join('pedidos_shopifies_operadore_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_operadore_links.pedidos_shopify_id')
                    ->join('operadores', 'pedidos_shopifies_operadore_links.operadore_id', '=', 'operadores.id')
                    ->sum(DB::raw('REPLACE(operadores.costo_operador, ",", "")'))
            ];
        }


        return response()->json([
            'data' => $summary,
        ]);
    }




    private function applyConditions($query, $conditions, $not = false)
    {
        $operator = $not ? '!=' : '=';

        foreach ($conditions as $condition) {
            foreach ($condition as $key => $value) {
                if (strpos($key, '.') !== false) {
                    [$relation, $property] = explode('.', $key);
                    $query->whereHas($relation, function ($subQuery) use ($property, $value, $operator) {
                        $subQuery->where($property, $operator, $value);
                    });
                } else {
                    $query->where($key, $operator, $value);
                }
            }
        }
    }

    private function applyConditionsAnd($query, $conditions, $not = false)
    {
        $operator = $not ? '!=' : '=';

        foreach ($conditions as $condition) {
            foreach ($condition as $key => $value) {
                if (strpos($key, '.') !== false) {
                    // Si la clave tiene un punto, es una relación anidada
                    // Dividimos la clave en partes para navegar por las relaciones
                    $keys = explode('.', $key);
                    $field = array_pop($keys);
                    $relation = implode('.', $keys);

                    // Aplicamos las condiciones en la relación más interna
                    $query->whereHas($relation, function ($query) use ($field, $operator, $value) {
                        $query->where($field, $operator, $value);
                    });
                } else {
                    // Si no hay un punto en la clave, es un campo directo de la tabla principal
                    $query->where($key, $operator, $value);
                }
            }
        }
    }





    public function CalculateValuesSeller(Request $request)
    {
        error_log("CalculateValuesSeller");
        $data = $request->json()->all();
        $startDate = Carbon::createFromFormat('j/n/Y', $data['start'])->format('Y-m-d');
        $endDate = Carbon::createFromFormat('j/n/Y', $data['end'])->format('Y-m-d');
        $Map = $data['and'];
        $not = $data['not'];
        $dateFilter = $data["date_filter"];
        $idSeller = $data["id_seller"];
        error_log($idSeller);

        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }

        try {
            // $startTime = microtime(true);
            $sumRefererValue = PedidosShopify::query()
                ->with(['users.vendedores'])

                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
                ->whereHas('users.vendedores', function ($query) use ($idSeller) {
                    $query->where('referer', $idSeller);
                })
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->where('status', 'ENTREGADO')
                ->sum('value_referer');


            // $query = PedidosShopify::query()
            //     ->where('id_comercial', $idSeller)
            //     ->where('estado_interno', 'CONFIRMADO')
            //     ->where('estado_logistico', 'ENVIADO')
            //     ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate]);

            // error_log("queryValRec: " . $query->count() . " registros.");
            // // error_log("query: ");
            // error_log(json_encode($query->get()));

            //con transp int
            // $queryInt = PedidosShopify::query()
            //     ->with(['transportadora', 'pedidoCarrier'])
            //     ->where('id_comercial', $idSeller)
            //     ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
            //     ->where('estado_interno', 'CONFIRMADO')
            //     ->where('estado_logistico', 'ENVIADO')
            //     // ->whereHas('transportadora');
            //     ->whereDoesntHave('pedidoCarrier');
            // error_log("queryInt: " . $queryInt->count() . " registros.");


            //con carrier externals
            // $queryCE = PedidosShopify::query()
            //     ->with(['pedidoCarrier'])
            //     ->where('id_comercial', $idSeller)
            //     ->where('estado_interno', 'CONFIRMADO')
            //     ->where('estado_logistico', 'ENVIADO')
            //     ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
            //     ->whereHas('pedidoCarrier');
            // ->whereDoesntHave('ruta');
            // error_log("queryCE: " . $queryCE->count() . " registros.");

            // $this->applyConditions($query, $Map); //en and esta "estado_interno": "CONFIRMADO" "estado_logistico": "ENVIADO"
            // $this->applyConditions($query, $not, true); //no tiene not
            // $query1 = clone $query;
            // $query2 = clone $query;
            // $query3 = clone $query;
            // $query4 = clone $query;
            // $query5 = clone $query;

            // $this->applyConditions($queryInt, $Map);
            // $this->applyConditions($queryInt, $not, true);
            // $query2Int = clone $queryInt;
            // $query3Int = clone $queryInt;


            // $this->applyConditions($queryCE, $Map);
            // $this->applyConditions($queryCE, $not, true);
            // $query2CE = clone $queryCE;
            // $query3CE = clone $queryCE;


            $totalValoresRecibidos = floatval(PedidosShopify::where('id_comercial', $idSeller)
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->where('status', 'ENTREGADO')
                ->sum(DB::raw('REPLACE(precio_total, ",", "")')));

            $totalProductWarehouse = floatval(PedidosShopify::where('id_comercial', $idSeller)
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->where('status', 'ENTREGADO')
                ->sum('value_product_warehouse'));
            // $totalReferer = floatval($query5->where('estado_interno', 'CONFIRMADO')
            //     ->where('estado_logistico', 'ENVIADO')
            //     ->where('status', 'ENTREGADO')
            //     ->sum('value_referer'));

            // $totalShippingCostInt = $query2Int->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
            //     ->join('up_users_pedidos_shopifies_links', 'pedidos_shopifies.id', '=', 'up_users_pedidos_shopifies_links.pedidos_shopify_id')
            //     ->join('up_users', 'up_users_pedidos_shopifies_links.user_id', '=', 'up_users.id')
            //     ->join('up_users_vendedores_links', 'up_users.id', '=', 'up_users_vendedores_links.user_id')
            //     ->join('vendedores', 'up_users_vendedores_links.vendedor_id', '=', 'vendedores.id')
            //     ->sum(DB::raw('REPLACE(vendor.costo_envio, ",", "")'));

            $totalShippingCostInt = floatval(PedidosShopify::query()
                ->with(['pedidoCarrier'])
                ->where('id_comercial', $idSeller)
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->whereDoesntHave('pedidoCarrier')
                ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
                ->sum('costo_envio'));

            $totalShippingCostCE = floatval(PedidosShopify::query()
                ->with(['pedidoCarrier'])
                ->where('id_comercial', $idSeller)
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
                ->whereHas('pedidoCarrier')
                ->whereIn('status', ['ENTREGADO', 'NOVEDAD'])
                ->sum('costo_envio'));

            // $totalCostoDevolucionInt = $query3Int->whereIn('status', ['NOVEDAD'])
            //     ->whereNotIn('estado_devolucion', ['PENDIENTE'])
            //     ->join('up_users_pedidos_shopifies_links', 'pedidos_shopifies.id', '=', 'up_users_pedidos_shopifies_links.pedidos_shopify_id')
            //     ->join('up_users', 'up_users_pedidos_shopifies_links.user_id', '=', 'up_users.id')
            //     ->join('up_users_vendedores_links', 'up_users.id', '=', 'up_users_vendedores_links.user_id')
            //     ->join('vendedores', 'up_users_vendedores_links.vendedor_id', '=', 'vendedores.id')
            //     ->sum(DB::raw('REPLACE(vendedores.costo_devolucion, ",", "")'));
            // $totalCostoDevolucionInt = floatval($query3Int->whereIn('status', ['NOVEDAD'])
            //     ->whereNotIn('estado_devolucion', ['PENDIENTE'])
            //     ->sum('costo_devolucion'));

            // $totalCostoDevolucionCE = floatval($query3CE
            //     ->whereIn('status', ['NOVEDAD'])
            //     ->whereNotIn('estado_devolucion', ['PENDIENTE'])
            //     ->sum('costo_devolucion'));

            // $totalValoresRecibidos = 0;
            // error_log("totalShippingCostInt: $totalShippingCostInt");
            // error_log("totalShippingCostCE: $totalShippingCostCE");

            $totalShippingCost = $totalShippingCostInt + $totalShippingCostCE;
            // $totalCostoDevolucion = $totalCostoDevolucionInt + $totalCostoDevolucionCE;
            $totalCostoDevolucion = PedidosShopify::where('id_comercial', $idSeller)
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->where('status', 'NOVEDAD')
                ->whereNotIn('estado_devolucion', ['PENDIENTE'])
                ->sum('costo_devolucion');

            // $totalProductWarehouse = 0;
            // $totalReferer = 0;
            // error_log("-->: $totalShippingCostInt");
            // error_log("-->: $totalShippingCostCE");

            $totalValoresRecibidos = round($totalValoresRecibidos, 2);
            $totalShippingCost = round($totalShippingCost, 2);
            $totalCostoDevolucion = round($totalCostoDevolucion, 2);
            $totalProductWarehouse = round($totalProductWarehouse, 2);

            // error_log("totalValoresRecibidos: $totalValoresRecibidos ");
            // error_log("sumRefererValue: $sumRefererValue");
            // error_log("totalShippingCost: $totalShippingCost");
            // error_log("totalCostoDevolucion: $totalCostoDevolucion");
            // error_log("totalProductWarehouse: $totalProductWarehouse");

            $summary = [
                'totalValoresRecibidos' => $totalValoresRecibidos,
                'totalShippingCost' => $totalShippingCost,
                'totalCostoDevolucion' => $totalCostoDevolucion,
                'totalProductWarehouse' => $totalProductWarehouse,
                'totalReferer' => $sumRefererValue,
            ];

            /*
        $summary = [
            'totalValoresRecibidos' => $query1->whereIn('status', ['ENTREGADO'])->sum(DB::raw('REPLACE(precio_total, ",", "")')),

            'totalShippingCost' => $query2
                ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
                ->join('up_users_pedidos_shopifies_links', 'pedidos_shopifies.id', '=', 'up_users_pedidos_shopifies_links.pedidos_shopify_id')
                ->join('up_users', 'up_users_pedidos_shopifies_links.user_id', '=', 'up_users.id')
                ->join('up_users_vendedores_links', 'up_users.id', '=', 'up_users_vendedores_links.user_id')
                ->join('vendedores', 'up_users_vendedores_links.vendedor_id', '=', 'vendedores.id')
                ->sum(DB::raw('REPLACE(vendedores.costo_envio, ",", "")')),

            'totalCostoDevolucion' => $query3
                ->whereIn('status', ['NOVEDAD'])
                ->whereNotIn('estado_devolucion', ['PENDIENTE'])
                ->join('up_users_pedidos_shopifies_links', 'pedidos_shopifies.id', '=', 'up_users_pedidos_shopifies_links.pedidos_shopify_id')
                ->join('up_users', 'up_users_pedidos_shopifies_links.user_id', '=', 'up_users.id')
                ->join('up_users_vendedores_links', 'up_users.id', '=', 'up_users_vendedores_links.user_id')
                ->join('vendedores', 'up_users_vendedores_links.vendedor_id', '=', 'vendedores.id')
                ->sum(DB::raw('REPLACE(vendedores.costo_devolucion, ",", "")')),

            'totalProductWarehouse' => floatval($query4
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->where('status', 'ENTREGADO')
                ->sum('value_product_warehouse')),

            'totalReferer' => floatval($query5
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO')
                ->where('status', 'ENTREGADO')
                ->sum('value_referer')),


        ];
*/
            return response()->json([
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            error_log("error: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function CalculateValuesProvider(Request $request)
    {
        $data = $request->json()->all();
        $startDate = Carbon::createFromFormat('j/n/Y', $data['start'])->format('Y-m-d');
        $endDate = Carbon::createFromFormat('j/n/Y', $data['end'])->format('Y-m-d');
        $Map = $data['and'];
        $not = $data['not'];
        $idUser = $data['id_user'];
        $dateFilter = $data["date_filter"];


        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }

        $query = PedidosShopify::query()
            ->with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta', 'product.warehouse.provider'])
            ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate]);


        $this->applyConditionsAnd($query, $Map);
        $this->applyConditions($query, $not, true);
        $query1 = clone $query;
        // $query2 = clone $query;
        // $query3 = clone $query;
        $saldoProvider = Provider::where('user_id', $idUser)->first();
        $summary = [
            // "ped"=>$query,
            'totalValoresRecibidos' => $query1->whereIn('status', ['ENTREGADO'])->sum(DB::raw('REPLACE(value_product_warehouse, ",", "")')),


            // 'totalValoresRecibidos' => $query1->whereIn('status', ['ENTREGADO'])->
            // whereHas('product.warehouse.provider', function ($query) use ($idUser) {
            //     $query->where('user_id', $idUser);
            // })->sum(DB::raw('REPLACE(value_product_warehouse, ",", "")')),

            "totalRetirosEfectivo" => OrdenesRetiro::whereHas('users_permissions_user.providers', function ($query) use ($idUser) {
                $query->where('user_id', $idUser);
            })
                ->where(function ($query) {
                    $query->where('estado', 'APROBADO')
                        ->orWhere('estado', 'REALIZADO');
                })
                ->whereRaw("STR_TO_DATE(" . "fecha" . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
                ->sum('monto'),
            'saldoActual' => $saldoProvider->saldo,

            //     ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
            //     ->join('up_users_pedidos_shopifies_links', 'pedidos_shopifies.id', '=', 'up_users_pedidos_shopifies_links.pedidos_shopify_id')
            //     ->join('up_users', 'up_users_pedidos_shopifies_links.user_id', '=', 'up_users.id')
            //     ->join('up_users_vendedores_links', 'up_users.id', '=', 'up_users_vendedores_links.user_id')
            //     ->join('vendedores', 'up_users_vendedores_links.vendedor_id', '=', 'vendedores.id')
            //     ->sum(DB::raw('REPLACE(vendedores.costo_envio, ",", "")')),

            // 'totalCostoDevolucion' => $query3
            //     ->whereIn('status', ['NOVEDAD'])
            //     ->whereNotIn('estado_devolucion', ['PENDIENTE'])
            //     ->join('up_users_pedidos_shopifies_links', 'pedidos_shopifies.id', '=', 'up_users_pedidos_shopifies_links.pedidos_shopify_id')
            //     ->join('up_users', 'up_users_pedidos_shopifies_links.user_id', '=', 'up_users.id')
            //     ->join('up_users_vendedores_links', 'up_users.id', '=', 'up_users_vendedores_links.user_id')
            //     ->join('vendedores', 'up_users_vendedores_links.vendedor_id', '=', 'vendedores.id')
            //     ->sum(DB::raw('REPLACE(vendedores.costo_devolucion, ",", "")')),

        ];

        return response()->json([
            'data' => $summary,
        ]);
    }

    // ! NUEVA PARA EXTERNAL CARRIER
    public function CalculateValuesExternalCarrier(Request $request)
    {
        $data = $request->json()->all();
        $startDate = Carbon::createFromFormat('j/n/Y', $data['start'])->format('Y-m-d');
        $endDate = Carbon::createFromFormat('j/n/Y', $data['end'])->format('Y-m-d');
        $Map = $data['and'];
        $not = $data['not'];
        $idUser = $data['id_user'];  // ! <---- PASAR ID PARA QUE BUSQUE LA TRANSPORTADORA EXTERNA
        $dateFilter = $data["date_filter"];


        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }

        // if ($idUser == "TODO") {
        //     error_log("Filtrar Has pedidoCarrier");
        // } else {
        //     error_log("Filtrar por idExt");
        // }


        if ($dateFilter == "FECHA PAGO RECIBIDO") {
            error_log("CalculateValuesExternalCarrier_FECHA_PAGO_RECIBIDO");

            $query = PedidosShopify::query()
                ->with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta', 'product.warehouse.provider', 'carrierExternal', 'pedidoCarrier'])
                // ->where('carrier_external_id', $idUser)  // ! <---- se pretende usar el id de la transportadora externa que seleccione
                // ->where('carrier_external_id',1)
                // ->whereHas('pedidoCarrier', function ($query) use ($idUser) {
                //     $query->where('carrier_id', $idUser);
                // })
                ->whereHas('pedidoCarrier', function ($query) use ($idUser) {
                    if ($idUser != "TODO") {
                        $query->where('carrier_id', $idUser);
                    }
                })
                ->whereNotNull('gestioned_payment_cost_delivery')
                ->whereRaw(
                    "
                    DATE(JSON_UNQUOTE(JSON_EXTRACT(gestioned_payment_cost_delivery, '$.m_t_g'))) 
                    BETWEEN ? AND ?",
                    [$startDate, $endDate]
                )

                // $this->applyConditionsAnd($query, $Map);
                // $this->applyConditions($query, $not, true);
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                if ($key === 'gestioned_payment_cost_delivery') {
                                    $pedidos->whereJsonContains('gestioned_payment_cost_delivery->state', $valor);
                                } else {
                                    $pedidos->where($key, '=', $valor);
                                }
                            }
                        }
                    }
                }))->where((function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHasNeg($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                }));
        } else {

            $query = PedidosShopify::query()
                ->with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta', 'product.warehouse.provider', 'carrierExternal', 'pedidoCarrier'])
                // ->where('carrier_external_id', $idUser)  // ! <---- se pretende usar el id de la transportadora externa que seleccione
                // ->where('carrier_external_id',1)
                // ->whereHas('pedidoCarrier', function ($query) use ($idUser) {
                //     $query->where('carrier_id', $idUser);
                // })
                ->whereHas('pedidoCarrier', function ($query) use ($idUser) {
                    if ($idUser != "TODO") {
                        $query->where('carrier_id', $idUser);
                    }
                })
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])


                // $this->applyConditionsAnd($query, $Map);
                // $this->applyConditions($query, $not, true);
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                if ($key === 'gestioned_payment_cost_delivery') {
                                    $pedidos->whereJsonContains('gestioned_payment_cost_delivery->state', $valor);
                                } else {
                                    $pedidos->where($key, '=', $valor);
                                }
                            }
                        }
                    }
                }))->where((function ($pedidos) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHasNeg($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                }));
        }

        $query1 = clone $query;
        $query2 = clone $query;
        $query3 = clone $query;
        // $saldoProvider = Provider::where('user_id', $idUser)->first();
        $summary = [
            // "ped"=>$query3->get(),
            'totalValoresRecibidos' => $query1
                ->where('estado_interno', "CONFIRMADO")
                ->where('estado_logistico', "ENVIADO")
                ->whereIn('status', ['ENTREGADO'])->sum(DB::raw('REPLACE(precio_total, ",", "")')),

            // ******************************* CODIGO A USAR         ******************************
            // *************************************************************************************

            'totalCostoEntrega' => $query1
                ->where('estado_interno', "CONFIRMADO")
                ->where('estado_logistico', "ENVIADO")
                ->whereIn('status', ['ENTREGADO'])
                ->sum(DB::raw('REPLACE(costo_transportadora, ",", "")')),

            // ************************************************************************************* 

            // ! costo devolucion 
            'totalCostoDevolucion' => $query2
                ->where('estado_interno', "CONFIRMADO")
                ->where('estado_logistico', "ENVIADO")
                ->whereNotIn('estado_devolucion', ['PENDIENTE'])
                ->with('pedidoCarrier') // Incluimos la relación pedidoCarrier
                ->get()
                ->sum(function ($item) {
                    $costo_transportadora = floatval(str_replace(',', '', $item->costo_transportadora));
                    $cost_refound_external = isset($item->pedidoCarrier->cost_refound_external)
                        ? floatval(str_replace(',', '', $item->pedidoCarrier->cost_refound_external))
                        : 0;
                    return $costo_transportadora + $cost_refound_external;
                })
                +
                $query3
                ->where('estado_interno', "CONFIRMADO")
                ->where('estado_logistico', "ENVIADO")
                ->where(function ($query) {
                    $query->where('status', 'NOVEDAD')
                        ->orWhere('status', 'NO ENTREGADO');
                })
                ->sum(DB::raw('REPLACE(costo_transportadora, ",", "")'))

            // *************************************************************************************

        ];
        $summary['totalResultadoFinal'] = $summary['totalValoresRecibidos'] - $summary['totalCostoEntrega'] - $summary['totalCostoDevolucion'];
        return response()->json([
            'data' => $summary,
        ]);
    }



    public function shopifyPedidos(Request $request, $id)
    {
        $startTime = microtime(true);

        DB::beginTransaction();
        try {

            //
            $input = json_decode($request->getContent(), true);
            if (!is_array($input)) {
                error_log('Error:_El_request_no_es_un_JSON_válido.');
            }

            $id_shopify = $request->input('id');
            $order_number = $request->input('order_number');
            $name = $request->input('shipping_address.name');

            // error_log('name: ' . $name);
            // error_log('order_number: ' . $order_number);

            //solucion 2
            $cacheKey = "shopify_order_{$id_shopify}_{$id}";

            // Verificar si la orden ya está siendo procesada o fue procesada previamente
            if (Cache::has($cacheKey)) {
                error_log("orden_procesada_" . $cacheKey);
                return response()->json([
                    'error' => 'Esta orden ya ha sido procesada',
                    'orden_existente' => Cache::get($cacheKey),
                ], 200);
            }

            // Almacenar temporalmente en cache como señal de que está siendo procesada
            Cache::put($cacheKey, 'processing', now()->addMinutes(1));

            error_log("********dataID: " . $id_shopify . "_" . $id . "_" . $order_number);
            $shippingAddress = json_encode($input['shipping_address'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            error_log("_" . $id . "_" . $order_number . '_shipping_address: ' . $shippingAddress . "_");

            $orderExists = PedidosShopify::where([
                'id_shopify' => $id_shopify,
                'id_comercial' => $id,
            ])->get();

            if ($orderExists->isNotEmpty()) {
                error_log("Esta_orden_ya_existe_" . $id . "_" . $order_number . "_" . $id_shopify . "_");
                // error_log("Orden_existente: " . $orderExists);

                return response()->json([
                    'error' => 'Esta orden ya existe',
                    'orden_a_ingresar' => [
                        'numero_orden' => $order_number,
                        'nombre' => $name,
                    ],
                    'orden_existente' => $orderExists,
                ], 200);
            }

            if (!isset($input['shipping_address'])) {
                error_log('Error:_shipping_address_no_está_presente_en_el_JSON.');
            }


            //GENERATE DATE
            $currentDate = now();
            $fechaActual = $currentDate->format('d/m/Y');

            // ID DATE ORDER FOR RELATION
            $dateOrder = "";


            $created_at_shopify = $request->input('created_at');
            // error_log("********created_at_shopify: $created_at_shopify***");
            // $shippingAddress = json_encode($input['shipping_address'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            // error_log('shipping_address: ' . $shippingAddress);

            // $line_items = json_encode($input['line_items'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            // error_log('line_items: ' . $line_items);

            //VARIABLES FOR ENTITY
            $listOfProducts = [];
            $address1 = $request->input('shipping_address.address1');
            $address2 = $request->input('shipping_address.address2');
            $fullAddress = (is_null($address2) || strtolower(trim($address2)) === 'null' || trim($address2) === '')
                ? $address1
                : $address1 . ' | ' . $address2;

            $phone = $request->input('shipping_address.phone');
            $total_price = $request->input('total_price');
            $customer_note = $request->input('customer_note');
            $city = $request->input('shipping_address.city');
            $productos = $request->input('line_items');
            $provinciaName = $request->input('shipping_address.province');
            $address2 = $request->input('shipping_address.address2');
            $customerNote = $request->input('customer_note');
            error_log('customerNote: ' . $customerNote);
            //ADD PRODUCT TO LIST FOR NEW OBJECT

            error_log("******************proceso 1 terminado************************\n");
            foreach ($productos as $element) {

                $listOfProducts[] = [
                    'id' => $element['id'],
                    'name' => $element['product_id'],
                    'quantity' => $element['quantity'],
                    'price' => $element['price'],
                    'title' => $element['title'],
                    'variant_title' => $element['variant_title'],
                    'sku' => $element['sku']

                ];
            }

            // $ahora = now();

            $fechaLimite = Carbon::createFromFormat('Y-m-d', '2024-06-26');

            $search = PedidosShopify::where([
                'numero_orden' => $order_number,
                'tienda_temporal' => $productos[0]['vendor'],
                'id_comercial' => $id,
                'id_shopify' => $id_shopify,
            ])->get();
            ////

            //
            // IF ORDER NOT EXIST CREATE ORDER
            if ($search->isEmpty()) {
                $dateOrder;
                // SEARCH DATE ORDER FOR RELLATION
                $searchDate = PedidoFecha::where('fecha', $fechaActual)->get();

                // IF DATE ORDER NOT EXIST CREATE ORDER AND ADD ID ELSE IF ONLY ADD DATE ORDER ID VALUE
                if ($searchDate->isEmpty()) {
                    // Crea un nuevo registro de fecha
                    $newDate = new PedidoFecha();
                    $newDate->fecha = $fechaActual;
                    $newDate->save();

                    // Obtén el ID del nuevo registro
                    $dateOrder = $newDate->id;
                } else {
                    // Si la fecha existe, obtén el ID del primer resultado
                    $dateOrder = $searchDate[0]->id;
                }


                // Obtener la fecha y hora actual
                $fechaHoraActual = date("d/m/Y H:i");
                // Crear una nueva orden
                $formattedPrice = str_replace(["$", ",", " "], "", $total_price);


                $sku = $productos[0]['sku'];
                $lastIdProduct = 0;
                // error_log('sku:' . $sku);

                if ($sku != null && preg_match('/^(.*C*)C\d+$/', $sku)) {
                    $parts = explode('C', $sku);
                    $id_product = end($parts);

                    if (is_numeric($id_product)) {
                        $product = Product::find($id_product);
                        if ($product != null) {
                            $lastIdProduct = $id_product; //firstId
                        }
                    }
                } else {
                    // error_log($sku . ' SKU inválido o nulo');
                }



                $variants = implode(', ', array_column(array_slice($listOfProducts, 0), 'variant_title'));

                $hasVariants = trim($variants) !== '';

                $noteTrimmed = trim($customerNote);
                $hasCustomerNote = !is_null($customerNote)
                    && strtolower($noteTrimmed) !== 'null'
                    && $noteTrimmed !== ''
                    && $noteTrimmed !== '.';
                $fullNotes = null;
                if ($hasVariants && $hasCustomerNote) {
                    $fullNotes = $variants . ' | ' . $customerNote;
                } elseif ($hasVariants) {
                    $fullNotes = $variants;
                } elseif ($hasCustomerNote) {
                    $fullNotes = $customerNote;
                }
                $idCity = null;
                $idProv_local = null;
                // /*
                try {

                    if ($provinciaName != null || $provinciaName != "") {
                        $provinciaSearch = $this->normalizeText($provinciaName);

                        // $provinciaslist = DpaProvincia::pluck('id', 'provincia')->toArray();
                        // foreach ($provinciaslist as $provincia => $provId) {
                        //     if (strpos($this->normalizeText($provincia), $provinciaSearch) !== false) {
                        //         $idProv_local = $provId;
                        //         break;
                        //     }
                        // }

                        $provincia = dpaProvincia::whereRaw("LOWER(REPLACE(provincia, ' ', '')) LIKE ?", ["%$provinciaSearch%"])
                            ->first();

                        if ($provincia) {
                            $idProv_local = $provincia->id;
                        }
                    } else {
                        // error_log("La provincia está vacía o es nula");
                    }

                    // error_log("idProv_local: " . ($idProv_local ?? 'No encontrado'));


                    if ($idProv_local) {

                        $ciudadSearch = $this->normalizeText($city);

                        // $cities_exist = CoverageExternal::where('id_provincia', $idProv_local)
                        //     ->pluck('id', 'ciudad')
                        //     ->toArray();

                        // foreach ($cities_exist as $ciudadExistente => $cityId) {
                        //     if (strpos($this->normalizeText($ciudadExistente), $ciudadSearch) !== false) {
                        //         $idCity = $cityId;
                        //         break;
                        //     }
                        // }

                        $cityFound = CoverageExternal::where('id_provincia', $idProv_local)
                            ->whereRaw("CONVERT(REPLACE(ciudad, ' ', '') USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(REPLACE(?, ' ', '') USING utf8mb4) COLLATE utf8mb4_unicode_ci", [$ciudadSearch])
                            ->first();


                        if ($cityFound) {
                            $idCity = $cityFound->id;
                        }
                    }

                    // error_log("idCity: " . ($idCity ?: 'No encontrado'));
                } catch (\Exception $e) {
                    error_log("Error_busqueda_provincia_ciudad: " . $e);
                }
                // */
                //THISS to search carriers
                // $allCarrier = CarrierCoverage::where('id_coverage', $idCity)
                //     ->get();
                // error_log("allCarrier: $allCarrier");


                error_log("******************proceso 2 terminado************************\n");
                // error_log("********numero_orden: $order_number-$id: " . json_encode($productos));
                // error_log("******************variantes: . $variants. ************************\n");

                // error_log("lastIdProduct: $lastIdProduct");

                $createOrder = new PedidosShopify([
                    'marca_t_i' => $fechaHoraActual,
                    'tienda_temporal' => $productos[0]['vendor'],
                    'numero_orden' => $order_number,
                    'direccion_shipping' => $fullAddress,
                    'nombre_shipping' => $name,
                    'telefono_shipping' => $phone,
                    'precio_total' => $formattedPrice,
                    // 'observacion' => $variants,
                    'observacion' => $fullNotes,
                    'ciudad_shipping' => $city,
                    'sku' => $sku,
                    'id_product' => $lastIdProduct,
                    'id_comercial' => $id,
                    'producto_p' => $listOfProducts[0]['title'],
                    'producto_extra' => implode(', ', array_column(array_slice($listOfProducts, 1), 'title')),
                    'variant_details' => json_encode($listOfProducts),
                    'cantidad_total' => $listOfProducts[0]['quantity'],
                    'estado_interno' => "PENDIENTE",
                    'status' => "PEDIDO PROGRAMADO",
                    'estado_logistico' => 'PENDIENTE',
                    'estado_pagado' => 'PENDIENTE',
                    'estado_pago_logistica' => 'PENDIENTE',
                    'estado_devolucion' => 'PENDIENTE',
                    'do' => 'PENDIENTE',
                    'dt' => 'PENDIENTE',
                    'dl' => 'PENDIENTE',
                    'id_shopify' => $id_shopify,
                    'provincia_shipping' => $provinciaName,
                    'city_id' => $idCity,
                ]);

                $createOrder->save();

                error_log("******************proceso 3 terminado************************\n");

                // error_log("listOfProducts: " . json_encode($listOfProducts));
                $uniqueIds = [];

                foreach ($listOfProducts as $item) {
                    $skuVar = $item['sku'];
                    // error_log('skuVar:' . $skuVar);
                    if ($skuVar != null && preg_match('/^(.*C*)C\d+$/', $skuVar)) {
                        $parts = explode('C', $skuVar);
                        $id_prod = end($parts);
                        if (is_numeric($id_prod)) {
                            $product = Product::find($id_prod);
                            if ($product != null) {
                                if (!in_array($id_prod, $uniqueIds)) {
                                    $uniqueIds[] = $id_prod;
                                }
                            }
                        }
                    } else {
                        // error_log($skuVar . ' SKU inválido o nulo');
                    }
                }

                // error_log("uniqueIds: " . json_encode($uniqueIds));

                foreach ($uniqueIds as $idProd) {

                    $newPedidoProduct = new PedidosProductLink();
                    $newPedidoProduct->pedidos_shopify_id = $createOrder->id;
                    $newPedidoProduct->product_id = $idProd;
                    // $newPedidoProduct->variant_sku =  $item['sku']; //skuGen o skuVar
                    // $newPedidoProduct->units =  $item['quantity'];
                    $newPedidoProduct->save();
                }

                error_log("idMaster: $id");

                $createPedidoFecha = new PedidosShopifiesPedidoFechaLink();
                $createPedidoFecha->pedidos_shopify_id = $createOrder->id;
                $createPedidoFecha->pedido_fecha_id = $dateOrder;
                $createPedidoFecha->save();

                $createUserPedido = new UpUsersPedidosShopifiesLink();
                $createUserPedido->user_id = $id;
                $createUserPedido->pedidos_shopify_id = $createOrder->id;
                $createUserPedido->save();

                $user = UpUser::with([
                    'vendedores',
                ])->find($id);
                error_log("******************proceso 4 terminado************************\n");

                if ($user->enable_autome) {
                    if ($user->webhook_autome != null) {

                        $client = new Client();

                        $response = $client->post($user->webhook_autome, [
                            'json' => [
                                "id" => $createOrder->id,
                                "line_item_shopify_id" => $listOfProducts[0]['id'],
                                "line_item_shopify_product_id" => $listOfProducts[0]['product_id'],
                                "marca_t_i" => $createOrder->marca_t_i,
                                "tienda_temporal" => $createOrder->tienda_temporal,
                                "numero_orden" => $createOrder->numero_orden,
                                "direccion_shipping" => $createOrder->direccion_shipping,
                                "nombre_shipping" => $createOrder->nombre_shipping,
                                "telefono_shipping" => $createOrder->telefono_shipping,
                                "precio_total" => $createOrder->precio_total,
                                "observacion" => $createOrder->observacion,
                                "ciudad_shipping" => $createOrder->ciudad_shipping,
                                "id_comercial" => $createOrder->id_comercial,
                                "producto_p" => $createOrder->producto_p,
                                "producto_extra" => $createOrder->producto_extra,
                                "cantidad_total" => $createOrder->cantidad_total,
                                "status" => $createOrder->status
                            ]
                        ]);
                    }
                }

                error_log("******************proceso 5 terminado************************\n");

                Cache::put($cacheKey, $createOrder, now()->addMinutes(1));

                DB::commit();

                error_log("order_created_ID_$id: $createOrder->id");

                // Captura el tiempo al final
                $endTime = microtime(true);

                // Calcular la duración total en segundos
                $executionTime = $endTime - $startTime;
                error_log("Tiempo total de ejecución: " . $executionTime . " segundos");

                return response()->json([
                    'message' => 'La orden se ha registrado con éxito.',
                    'orden_ingresada' => $createOrder,
                    // 'search' => 'MANDE',
                    // 'and' => [],
                    //  'id_product' => $id_product
                ], 200);
            } else {
                error_log("Esta orden ya existe: $order_number-$id");
                return response()->json([
                    'error' => 'Esta orden ya existe',
                    'orden_a_ingresar' => [
                        'numero_orden' => $order_number,
                        'nombre' => $name,
                        'direccion' => $address1,
                        'telefono' => $phone,
                        'precio_total' => $total_price,
                        'nota_cliente' => $customer_note,
                        'ciudad' => $city,
                        'producto' => $listOfProducts
                    ],
                    'orden_existente' => $search,
                ], 200);
            }
        } catch (\Exception $e) {
            error_log("ERROR_shopifyPedidos: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function sendToAutome($url, $data)
    {
        $client = new Client();
        $response = $client->post($url, [
            'data' => [
                "id" => $data->id,
                "marca_t_i" => $data->marca_t_i,
                "tienda_temporal" => $data->tienda_temporal,
                "numero_orden" => $data->numero_orden,
                "direccion_shipping" => $data->direccion_shipping,
                "nombre_shipping" => $data->nombre_shipping,
                "telefono_shipping" => $data->telefono_shipping,
                "precio_total" => $data->precio_total,
                "observacion" => $data->observacion,
                "ciudad_shipping" => $data->ciudad_shipping,
                "id_comercial" => $data->id_comercial,
                "producto_p" => $data->id_comercial,
                "producto_extra" => $data->id_comercial,
                "cantidad_total" => $data->id_comercial,
                "status" => $data->id_comercial,
            ]
        ]);

        return response()->json_decode($response->getBody()->getContents());
    }


    public function testChatby(Request $request)
    {
        $data = $request->json()->all();
        return $data;
    }





    public function getOrdersForPrintGuidesInSendGuidesPrincipalLaravel(Request $request)
    {
        $data = $request->json()->all();
        // $startDate = $data['start'];
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');

        $populate = $data['populate'];

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $test = $data['test']; //force version update

        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        //*
        $relationsToInclude = $data['include'];
        $relationsToExclude = $data['exclude'];
        // ! *************************************

        $pedidos = PedidosShopify::with($populate)
            // ->whereRaw("STR_TO_DATE(marca_tiempo_envio, '%e/%c/%Y') = ?", [$startDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if ($valor === null) {
                            $pedidos->whereNull($filter);
                        } else {
                            if ($key === '/marca_tiempo_envio') {
                                $startDateFormatted = Carbon::createFromFormat('j/n/Y', $valor)->format('Y-m-d');
                                $pedidos->whereRaw("STR_TO_DATE(marca_tiempo_envio, '%e/%c/%Y') = ?", [$startDateFormatted]);
                            } elseif (strpos($filter, '.') !== false) {
                                $relacion = substr($filter, 0, strpos($filter, '.'));
                                $propiedad = substr($filter, strpos($filter, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                if ($type == "equals") {
                                    $pedidos->where($filter, '=', $valor);
                                } else {
                                    $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
                                }
                            }
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        if ($valor === null) {
                            $pedidos->whereNotNull($key);
                        } else {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '!=', $valor);
                            }
                        }
                    }
                }
            }));

        if (isset($relationsToInclude)) {
            // error_log("IS relationsToInclude");
            foreach ($relationsToInclude as $relation) {
                // error_log("Include relation: $relation");
                $pedidos->whereHas($relation);
            }
        }

        if (isset($relationsToExclude)) {
            // error_log("IS relationsToInclude");
            foreach ($relationsToExclude as $relation) {
                // error_log("Exclude relation: $relation");
                $pedidos->whereDoesntHave($relation);
            }
        }
        // ! Ordenamiento ********************************** 
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
            $pedidos->orderBy(key($orderByText), reset($orderByText));
        } else {
            $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);
        return response()->json($pedidos);
    }

    public function obtenerPedidosPorRuta(Request $request)
    {
        $rutaId = $request->input('ruta_id');
        $transportadoraId = $request->input('transportadora_id');

        // Obtener los pedidos con la ruta y la transportadora específica
        $pedidos = PedidosShopify::whereHas('pedidos_shopifies_ruta_links', function ($query) use ($rutaId) {
            $query->where('ruta_id', $rutaId)->where('estado_interno', 'CONFIRMADO')->where('estado_logistico', 'ENVIADO');
        })
            ->whereHas('pedidos_shopifies_transportadora_links', function ($query) use ($transportadoraId) {
                $query->where('transportadora_id', $transportadoraId);
            })
            ->get();

        // Filtrar los pedidos entregados
        $entregados = $pedidos->filter(function ($pedido) {
            return $pedido->status === 'ENTREGADO';
        });

        // Filtrar los pedidos no entregados
        $noEntregados = $pedidos->filter(function ($pedido) {
            return $pedido->status === 'NO ENTREGADO';
        });

        // Filtrar los pedidos con novedad
        $novedad = $pedidos->filter(function ($pedido) {
            return $pedido->status === 'NOVEDAD';
        });

        // Contar la cantidad de pedidos entregados, no entregados y con novedad
        $cantidadEntregados = $entregados->count();
        $cantidadNoEntregados = $noEntregados->count();
        $cantidadNovedad = $novedad->count();

        // Calcular la suma total de ambos
        $sumaTotal = $cantidadEntregados + $cantidadNoEntregados + $cantidadNovedad;

        return response()->json([
            'entregados' => $cantidadEntregados,
            'no_entregados' => $cantidadNoEntregados,
            'novedad' => $cantidadNovedad,
            'suma_total' => $sumaTotal
        ]);
    }


    // *
    public function updateOrderRouteAndTransport(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            //code...

            $data = $request->json()->all();

            $newrouteId = $data['ruta'];
            $newtransportadoraId = $data['transportadora'];

            $order = PedidosShopify::with(['ruta', 'transportadora'])->find($id);
            if (!$order) {
                return response()->json(['message' => 'Orden no encontrada'], 404);
            }

            //44966
            //44939
            // return response()->json($order);

            $resRuta = $order->pedidos_shopifies_ruta_links;
            $resRutaNum = count($resRuta);
            // return response()->json($resRuta);
            // return response()->json(['resRuta' => $resRuta, 'num' => $resRutaNum], 200);


            if ($resRutaNum === 0) {
                $createPedidoRuta = new PedidosShopifiesRutaLink();
                $createPedidoRuta->pedidos_shopify_id = $order->id;
                $createPedidoRuta->ruta_id = $newrouteId;
                $createPedidoRuta->save();

                $createPedidoTransportadora = new PedidosShopifiesTransportadoraLink();
                $createPedidoTransportadora->pedidos_shopify_id = $order->id;
                $createPedidoTransportadora->transportadora_id = $newtransportadoraId;
                $createPedidoTransportadora->save();

                //
                /*
                $pedido = PedidosShopify::with('users.vendedores')->where('id', $id)->first();

                $pedido->estado_interno = "CONFIRMADO";
                $pedido->fecha_confirmacion =  date("d/m/Y H:i");
                // $pedido->confirmed_by = $generatedBy;
                $pedido->confirmed_at = date('Y-m-d H:i:s');
                //new column
                // $user = UpUser::where('id',  $generatedBy)->first();
                // $username = $user ? $user->username : null;

                $transp = Transportadora::where('id',  $newtransportadoraId)->first();
                $transpNombre = $transp ? $transp->nombre : null;

                $newHistory = [
                    "area" => "estado_interno",
                    "status" => "CONFIRMADO",
                    "timestap" => date('Y-m-d H:i:s'),
                    "comment" => "Transportadora asignada: " . $newtransportadoraId . "_" . $transpNombre,
                    "path" => "",
                    // "generated_by" => $generatedBy . "_" . $username
                ];

                $pedido->status_history = json_encode([$newHistory]);
                */

                DB::commit();

                return response()->json(['orden' => 'Ruta&Transportadora asignada exitosamente'], 200);
            } else {
                $pedidoRuta = PedidosShopifiesRutaLink::where('pedidos_shopify_id', $id)->first();
                $pedidoRuta->ruta_id = $newrouteId;
                $pedidoRuta->save();

                $pedidoTrasportadora = PedidosShopifiesTransportadoraLink::where('pedidos_shopify_id', $id)->first();
                $pedidoTrasportadora->transportadora_id = $newtransportadoraId;
                $pedidoTrasportadora->save();

                //
                /*
                $pedido = PedidosShopify::with('users.vendedores')->where('id', $id)->first();

                $pedido->estado_interno = "CONFIRMADO";
                $pedido->fecha_confirmacion =  date("d/m/Y H:i");
                // $pedido->confirmed_by = $generatedBy;
                $pedido->confirmed_at = date('Y-m-d H:i:s');
                //new column
                // $user = UpUser::where('id',  $generatedBy)->first();
                // $username = $user ? $user->username : null;

                $transp = Transportadora::where('id',  $newtransportadoraId)->first();
                $transpNombre = $transp ? $transp->nombre : null;

                $newHistory = [
                    "area" => "estado_interno",
                    "status" => "CONFIRMADO",
                    "timestap" => date('Y-m-d H:i:s'),
                    "comment" => "Transportadora asignada: " . $newtransportadoraId . "_" . $transpNombre,
                    "path" => "",
                    // "generated_by" => $generatedBy . "_" . $username
                ];

                $pedido->status_history = json_encode([$newHistory]);
                */

                DB::commit();
                return response()->json(['orden' => 'Ruta&Transportadora actualizada exitosamente'], 200);
            }
        } catch (\Exception $e) {
            error_log("ERROR_updateOrderRouteAndTransport: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateOrderSubRouteAndOperator(Request $request, $id)
    {
        $data = $request->json()->all();

        $newSubRouteId = $data['subruta'];
        $newOperatorId = $data['operador'];

        $order = PedidosShopify::with(['subRuta', 'operadore'])->find($id);
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        // Actualizar o crear la relación con SubRuta
        $resSubRuta = $order->pedidos_shopifies_sub_ruta_links;
        if (count($resSubRuta) === 0) {
            $createPedidoSubRuta = new PedidosShopifiesSubRutaLink();
            $createPedidoSubRuta->pedidos_shopify_id = $order->id;
            $createPedidoSubRuta->sub_ruta_id = $newSubRouteId;
            $createPedidoSubRuta->save();
        } else {
            $pedidoSubRuta = PedidosShopifiesSubRutaLink::where('pedidos_shopify_id', $id)->first();
            $pedidoSubRuta->sub_ruta_id = $newSubRouteId;
            $pedidoSubRuta->save();
        }

        // Actualizar o crear la relación con Operador
        $resOperador = $order->pedidos_shopifies_operadore_links;
        if (count($resOperador) === 0) {
            $createPedidoOperador = new PedidosShopifiesOperadoreLink();
            $createPedidoOperador->pedidos_shopify_id = $order->id;
            $createPedidoOperador->operadore_id = $newOperatorId;
            $createPedidoOperador->save();
        } else {
            $pedidoOperador = PedidosShopifiesOperadoreLink::where('pedidos_shopify_id', $id)->first();
            $pedidoOperador->operadore_id = $newOperatorId;
            $pedidoOperador->save();
        }

        return response()->json(['orden' => 'SubRuta&Operador actualizados exitosamente'], 200);
    }



    //  *
    public function updateVerifyPaymentCostDelivery(Request $request)
    {
        try {
            $data = $request->json()->all();
            if (!isset($data['state'])) {
                return response()->json(['error' => 'El estado no está presente en los datos.'], 400);
            }

            if (!isset($data['ids']) || !is_array($data['ids'])) {
                return response()->json(['error' => 'Los IDs no están presentes o no son un arreglo.'], 400);
            }

            $state = $data['state'];
            $ids = $data['ids'];

            // Actualizar el estado de los pedidos directamente con los IDs recibidos
            PedidosShopify::whereIn('id', $ids)->update(['payment_cost_delivery' => $state]);

            return response()->json(['message' => 'El estado de los pedidos se ha actualizado correctamente.'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Se produjo un error al procesar la solicitud.'], 500);
        }
    }


    public function updateVerifyPaymentCostDeliveryInd(Request $request, $idOrder)
    {
        try {
            $data = $request->json()->all();
            if (!isset($data['state'])) {
                return response()->json(['error' => 'El estado no está presente en los datos.'], 400);
            }

            $state = $data['state'];

            $order = PedidosShopify::where('id', $idOrder)->first();

            if (!$order) {
                return response()->json(['error' => 'No se encontró ningún pedido con el ID especificado.'], 404);
            }

            $order->payment_cost_delivery = $state;
            $order->save();

            return response()->json(['message' => 'El estado del pedido se ha actualizado correctamente.'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Se produjo un error al procesar la solicitud.'], 500);
        }
    }


    public function getByDateRangeAll(Request $request)
    {
        error_log("getByDateRangeAll");
        try {

            $data = $request->json()->all();

            $populate = $data['populate'];
            $startDate = $data['start'];
            $endDate = $data['end'];
            $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
            $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
            $and = $data['and'];
            $dateFilter = $data["date_filter"];

            $selectedFilter = "fecha_entrega";
            if ($dateFilter != "FECHA ENTREGA") {
                $selectedFilter = "marca_tiempo_envio";
            }
            $status = $data['status'];
            // $internal = $data['internal'];
            // ! ordenamiento ↓
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

            // $pedidos = PedidosShopify::with([
            //     'operadore.up_users',
            //     'transportadora',
            //     'users.vendedores',
            //     'novedades',
            //     'pedidoFecha',
            //     'ruta',
            //     'subRuta',
            //     'product.warehouse.provider',
            //     "pedidoCarrier",
            // ])
            $pedidos = PedidosShopify::with($populate)
                //select('marca_t_i', 'fecha_entrega', DB::raw('concat(tienda_temporal, "-", numero_orden) as codigo'), 'nombre_shipping', 'ciudad_shipping', 'direccion_shipping', 'telefono_shipping', 'cantidad_total', 'producto_p', 'producto_extra', 'precio_total', 'comentario', 'estado_interno', 'status', 'estado_logistico', 'estado_devolucion', 'costo_envio', 'costo_devolucion')
                // ->whereRaw("STR_TO_DATE(marca_t_i, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
                ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
                ->where((function ($pedidos) use ($and) {
                    foreach ($and as $condition) {
                        foreach ($condition as $key => $valor) {
                            if (strpos($key, '.') !== false) {
                                $relacion = substr($key, 0, strpos($key, '.'));
                                $propiedad = substr($key, strpos($key, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                $pedidos->where($key, '=', $valor);
                            }
                        }
                    }
                }));
            if (!empty($status)) {
                $pedidos->whereIn('status', $status);
            }
            // if (!empty($internal)) {
            //     $pedidos->whereIn('estado_interno', $internal);
            // }
            // ! Ordena
            if ($orderBy !== null) {
                $pedidos->orderBy(key($orderBy), reset($orderBy));
            }

            $response = $pedidos->get();

            return response()->json($response);
        } catch (\Exception $e) {
            error_log("ERROR_getByDateRangeAll: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }



    public function getByDateRangeAllExternal(Request $request)
    {
        $data = $request->json()->all();

        $startDate = $data['start'];
        $endDate = $data['end'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $and = $data['and'];
        $not = $data['not'];

        $status = $data['status'];
        // $internal = $data['internal'];
        $dateFilter = $data["date_filter"];

        $selectedFilter = "fecha_entrega";
        if ($dateFilter != "FECHA ENTREGA") {
            $selectedFilter = "marca_tiempo_envio";
        }
        $status = $data['status'];
        // $internal = $data['internal'];
        // ! ordenamiento ↓
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


        $pedidos = PedidosShopify::with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta', 'product.warehouse.provider', 'pedidoCarrier'])
            //select('marca_t_i', 'fecha_entrega', DB::raw('concat(tienda_temporal, "-", numero_orden) as codigo'), 'nombre_shipping', 'ciudad_shipping', 'direccion_shipping', 'telefono_shipping', 'cantidad_total', 'producto_p', 'producto_extra', 'precio_total', 'comentario', 'estado_interno', 'status', 'estado_logistico', 'estado_devolucion', 'costo_envio', 'costo_devolucion')
            ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
            ->where((function ($pedidos) use ($and) {
                foreach ($and as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))
            ->where((function ($pedidos) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHasNeg($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '!=', $valor);
                        }
                    }
                }
            }));
        if (!empty($status)) {
            $pedidos->whereIn('status', $status);
        }
        // if (!empty($internal)) {
        //     $pedidos->whereIn('estado_interno', $internal);
        // }
        // $pedidos->orderBy('marca_t_i', 'asc');
        if ($orderBy !== null) {
            $pedidos->orderBy(key($orderBy), reset($orderBy));
        }

        $response = $pedidos->get();

        return response()->json($response);
    }


















    public function generateTransportCosts()
    {

        $id = 76;
        $pedido = PedidosShopify::where('id', $id)
            ->first();

        $numero = $pedido->numero_orden;


        DB::table('test')->insert([
            'counter' => $numero,
        ]); // Supongamos que estás filtrando por una columna "id" específica
        return response()->json($pedido);
    }


    // ! Transport_Stats

    // public function generateTransportStatsTR(Request $request)
    public function generateTransportStatsTR()
    {
        // $data = $request->json()->all();

        // Obtener el mes y año actual
        $fechaMes = Carbon::now()->format('n/Y');
        // $fechaMes = "10/2023";
        // dd($fechaMes);
        // $fechaMes = $data['fecha_mes']; // Por ejemplo, '2/2023'
        $startDate = Carbon::createFromFormat('j/n/Y', '1/' . $fechaMes)->firstOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $pedidos = PedidosShopify::select('id', 'fecha_entrega', 'status', 'estado_logistico', 'estado_interno')
            ->with(['ruta', 'transportadora'])
            ->whereBetween(DB::raw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y')"), [$startDate, $endDate])
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO', 'NOVEDAD'])
            ->get();

        // ! DESDE AQUI EMPIEZA LO PEGADO    

        $allTransportadorasRutas = collect();
        $pedidosInfo = [];
        $entregadosCount = 0;
        $noEntregadosCount = 0;
        $novedad = 0;

        foreach ($pedidos as $pedido) {
            if ($pedido->pedidos_shopifies_transportadora_links->contains('transportadora.nombre', "")) {
                continue; // Saltar a la siguiente iteración del bucle
            }
            if ($pedido->pedidos_shopifies_ruta_links->contains('ruta.id', 1)) {
                continue; // Saltar a la siguiente iteración del bucle
            }

            $transportadorasInfo = $pedido->pedidos_shopifies_transportadora_links->map(function ($link) {
                return $link->transportadora->nombre . '-' . $link->transportadora->id;
            })->implode(', ');
            $rutasInfo = $pedido->pedidos_shopifies_ruta_links->map(function ($link) {
                return $link->ruta->titulo . '-' . $link->ruta->id;
            })->implode(', ');

            $allTransportadorasRutas->push($transportadorasInfo . '|' . $rutasInfo);


            $status = $pedido->status;

            if ($status === 'ENTREGADO') {
                $entregadosCount++;
            } else if ($status === 'NO ENTREGADO') {
                $noEntregadosCount++;
            } else if ($status === 'NOVEDAD') {
                $novedad++;
            }

            $pedidosInfo[] = [
                'pedido_id' => $pedido->id,
                'fecha_entrega' => $pedido->fecha_entrega,
                'rutas' => $rutasInfo,
                'transportadoras' => $transportadorasInfo,
                'status' => $status,
            ];
        }

        // Obtener listas únicas sin repeticiones
        $uniqueTransportadorasRutas = $allTransportadorasRutas->unique()->values();

        $transportadoraRutaCount = collect();

        foreach ($uniqueTransportadorasRutas as $uniqueInfo) {
            list($transportadora, $rutas) = explode('|', $uniqueInfo);

            $counts = collect($pedidosInfo)->where('rutas', $rutas)->where('transportadoras', $transportadora)->countBy('status')->toArray();

            $transportadoraRutaCount->push([
                'pedidos_info' => $pedidosInfo,
                'transportadoras' => $transportadora,
                'rutas' => $rutas,
                'entregados_count' => $counts['ENTREGADO'] ?? 0,
                'no_entregados_count' => $counts['NO ENTREGADO'] ?? 0,
                'novedad_count' => $counts['NOVEDAD'] ?? 0,
                'total_pedidos' => ($counts['ENTREGADO'] ?? 0) + ($counts['NO ENTREGADO'] ?? 0) + ($counts['NOVEDAD'] ?? 0),
            ]);
        }

        // Agrupar internamente por la propiedad "rutas"
        $groupedRutasTransportadoras = $transportadoraRutaCount->groupBy('transportadoras')->map(function ($group) {
            return $group->map(function ($item) {
                return [
                    'rutas' => $item['rutas'],
                    'entregados_count' => $item['entregados_count'],
                    'no_entregados_count' => $item['no_entregados_count'],
                    'novedad_count' => $item['novedad_count'],
                    'total_pedidos' => $item['total_pedidos'],
                ];
            });
        });

        $groupedRutasTransportadoras = $groupedRutasTransportadoras->map(function ($group) {
            $totalGeneralEntregados = $group->sum('entregados_count');
            $totalGeneralNoEntregados = $group->sum('no_entregados_count');
            $totalGeneralNovedades = $group->sum('novedad_count');

            return [
                'items' => $group,
                'totalgeneralentregados' => $totalGeneralEntregados,
                'totalgeneralnoentregados' => $totalGeneralNoEntregados,
                'totalgeneralnovedades' => $totalGeneralNovedades,
            ];
        });

        $final = [
            'pedidos' => $pedidosInfo,
            'listarutas_transportadoras' => $groupedRutasTransportadoras,
            'entregados_count' => $entregadosCount,
            'no_entregados_count' => $noEntregadosCount,
            'novedad_count' => $novedad,
            'total_pedidos' => $entregadosCount + $noEntregadosCount + $novedad,
            'efectividad' => number_format(($entregadosCount / ($entregadosCount + $noEntregadosCount + $novedad)) * 100, 2),
        ];

        $this->updateTransportStats($final, $endDate);

        // return response()->json($final);
    }

    public function updateTransportStats($apiData, $endDate)
    {
        $initialcountervalue = 1;
        // 1. Verificar si la tabla está vacía
        if (!TransportStats::count()) {
            foreach ($apiData['listarutas_transportadoras'] as $transportadoraName => $transportadoraData) {
                $transportId = explode("-", $transportadoraName)[1];
                $entregadosDay = 0;
                $totalPedidosDay = 0;

                foreach ($transportadoraData['items'] as $item) {
                    $entregadosDay = $item['entregados_count'];
                    $totalPedidosDay = $item['total_pedidos'];

                    TransportStats::create([
                        'transport_id' => $transportId,
                        'transport_name' => explode("-", $transportadoraName)[0],
                        // Nombre de la transportadora
                        'route_name' => $item['rutas'],
                        // Nombre de la ruta

                        'monthly_counter' => $initialcountervalue,
                        'daily_counter' => $initialcountervalue,

                        'efficiency_month_date' => $endDate,
                        'efficiency_day_date' => $endDate,

                        'transport_stats_day' => $entregadosDay / $totalPedidosDay,
                        'transport_stats_month' => ($transportadoraData['totalgeneralentregados'] / ($transportadoraData['totalgeneralentregados'] + $transportadoraData['totalgeneralnoentregados'] + $transportadoraData['totalgeneralnovedades']))
                        // array_sum(
                        //     [$transportadoraData['totalgeneralentregados'], $transportadoraData['totalgeneralnoentregados'], $transportadoraData['totalgeneralnovedades']]
                        //     )
                    ]);
                }
            }
        } else {
            foreach ($apiData['listarutas_transportadoras'] as $transportadoraName => $transportadoraData) {
                $transportadoraMonthStat = ($transportadoraData['totalgeneralentregados'] / ($transportadoraData['totalgeneralentregados'] + $transportadoraData['totalgeneralnoentregados'] + $transportadoraData['totalgeneralnovedades']));
                $transportId = explode("-", $transportadoraName)[1];
                $isNewTransportadora = true;

                foreach ($transportadoraData['items'] as $item) {
                    $entregadosDay = $item['entregados_count'];
                    $totalPedidosDay = $item['total_pedidos'];
                    $transportadoraNamePart = explode("-", $transportadoraName)[0];
                    $routeName = $item['rutas'];

                    $existingStat = TransportStats::where('transport_name', $transportadoraNamePart)->first();

                    // Usar el valor de monthly_counter del registro existente si existe, de lo contrario usar 1.
                    $monthlyValue = $existingStat ? $existingStat->monthly_counter : 1;

                    $stat = TransportStats::firstOrCreate(
                        [
                            'transport_name' => $transportadoraNamePart,
                            'route_name' => $routeName,
                        ],
                        [
                            'transport_id' => $transportId,
                            'monthly_counter' => $monthlyValue,
                            'daily_counter' => 1,
                            // Corregido a 1
                            'efficiency_month_date' => $endDate,
                            'efficiency_day_date' => $endDate,
                            'transport_stats_day' => $entregadosDay / $totalPedidosDay,
                        ]
                    );

                    if (!$stat->wasRecentlyCreated) {
                        $stat->daily_counter++;
                        $isNewTransportadora = false;

                        // Consultar el valor actual de transport_stats_day
                        $currentDayStat = $stat->transport_stats_day;

                        // Sumar el nuevo valor calculado
                        $newDayStat = $currentDayStat + ($entregadosDay / $totalPedidosDay);
                        $stat->transport_stats_day = $newDayStat;
                    }

                    $stat->efficiency_day_date = $endDate;
                    $stat->save();
                }

                if (!$isNewTransportadora) {
                    TransportStats::where('transport_name', $transportadoraNamePart)
                        ->increment('monthly_counter');
                }

                // Consultar el valor actual de transport_stats_month
                $currentMonthStat = TransportStats::where('transport_name', $transportadoraNamePart)->value('transport_stats_month');

                // Sumar el nuevo valor calculado
                $newTransportadoraMonthStat = $currentMonthStat + $transportadoraMonthStat;

                // Actualizar el registro con el nuevo total
                TransportStats::where('transport_name', $transportadoraNamePart)
                    ->update([
                        'efficiency_month_date' => $endDate,
                        'transport_stats_month' => $newTransportadoraMonthStat,
                    ]);
            }
        }
    }


    public function updateFieldTime(Request $request, $id)
    {
        error_log("updateFieldTime");
        $data = $request->all();
        // $input = json_decode($request->getContent(), true);
        // error_log('allRequest: ' . json_encode($input));

        $keyvalue = $data['keyvalue'];

        // $key = $data['key'];
        // $value = $data['value'];
        $idUser = $data['iduser'];
        $from = $data['from'];
        $datarequest = $data['datarequest'];

        $parts = explode(":", $keyvalue);
        if (count($parts) === 2) {
            $key = $parts[0];
            $value = $parts[1];
        }

        DB::beginTransaction();
        try {

            $currentDateTime = date('Y-m-d H:i:s');
            // "${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year}"
            $date = now()->format('j/n/Y');
            //"${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year} ${DateTime.now().hour}:${DateTime.now().minute} ";
            $currentDateTimeText = date("d/m/Y H:i");

            // $pedido = PedidosShopify::findOrFail($id);
            $pedido = PedidosShopify::with('users.vendedores')->where('id', $id)->first();
            $user = UpUser::where('id', $idUser)->first();
            $username = $user ? $user->username : null;
            $commentHist = "";

            if ($key == "estado_logistico") {
                if ($value == "IMPRESO") {  //from log,sell
                    $pedido->estado_logistico = $value;
                    $pedido->printed_at = $currentDateTime;
                    $pedido->printed_by = $idUser;
                }
                if ($value == "PENDIENTE") {  //from log,sell
                    $pedido->estado_logistico = $value;
                    // $pedido->printed_at = $currentDateTime;
                    // $pedido->printed_by = $idUser;
                }
                if ($value == "ENVIADO") {  //from log,sell
                    $pedido->estado_logistico = $value;
                    $pedido->sent_at = $currentDateTime;
                    $pedido->sent_by = $idUser;
                    $pedido->marca_tiempo_envio = $date;
                    $pedido->estado_interno = "CONFIRMADO";
                    $pedido->fecha_entrega = $date;
                }
            }
            if ($key == "estado_devolucion") {
                if ($value == "EN BODEGA") { //from logistic
                    $pedido->estado_devolucion = $value;
                    $pedido->dl = $value;
                    $pedido->marca_t_d_l = $currentDateTimeText;
                    $pedido->received_by = $idUser;
                }
                if ($from == "carrier") {
                    if ($value == "ENTREGADO EN OFICINA") {
                        $pedido->estado_devolucion = $value;
                        $pedido->dt = $value;
                        $pedido->marca_t_d = $currentDateTimeText;
                        $pedido->received_by = $idUser;
                    }
                    if ($value == "DEVOLUCION EN RUTA") {
                        $pedido->estado_devolucion = $value;
                        $pedido->dt = $value;
                        $pedido->marca_t_d_t = $currentDateTimeText;
                        $pedido->received_by = $idUser;
                    }
                    if ($value == "PENDIENTE") { //restart
                        $pedido->estado_devolucion = $value;
                        $pedido->do = $value;
                        $pedido->dt = $value;
                        $pedido->marca_t_d = null;
                        $pedido->marca_t_d_t = null;
                        $pedido->received_by = $idUser;
                    }
                } elseif ($from == "operator") {
                    if ($value == "ENTREGADO EN OFICINA") { //from operator, logistica
                        $pedido->estado_devolucion = $value;
                        $pedido->do = $value;
                        $pedido->marca_t_d = $currentDateTimeText;
                        $pedido->received_by = $idUser;
                    }
                } elseif ($from == "seller") {
                    if ($value == "EN BODEGA PROVEEDOR") { //from seller return scanner
                        $pedido->estado_devolucion = $value;
                        $pedido->marca_t_d = $currentDateTimeText;
                        $pedido->received_by = $idUser;
                    }
                }
            }


            if ($key == "status") {
                if ($value != "NOVEDAD_date") {
                    $pedido->status = $value;
                }

                $pedido->fill($datarequest);
                $comentario = $datarequest['comentario'];
                $commentHist = $comentario;

                if ($value == "ENTREGADO" || $value == "NO ENTREGADO") {
                    $pedido->fecha_entrega = $date;
                }
                if ($value == "NOVEDAD_date") {
                    $pedido->status = "NOVEDAD";
                    $pedido->fecha_entrega = $date;
                }
                $pedido->status_last_modified_at = $currentDateTime;
                $pedido->status_last_modified_by = $idUser;

                // // * if it exists, delete from transaccion_pedidos_transportadora
                // error_log("delete from tpt");

                $idTransportadora = $pedido['transportadora'][0]['id'];
                $fechaEntrega = now()->format('j/n/Y');

                $transaccion = TransaccionPedidoTransportadora::where('id_pedido', $id)
                    ->where('id_transportadora', $idTransportadora)
                    ->where('fecha_entrega', $fechaEntrega)
                    ->get();

                $transaccionFound = $transaccion->first();

                if ($transaccionFound !== null) {
                    // error_log($transaccionFound->id);
                    $transaccionFound->delete();
                    // error_log("deleted");
                }
            }

            //v2
            if ($key == "estado_interno") {
                if ($value == "CONFIRMADO") {
                    $name_comercial = $pedido->tienda_temporal;

                    if (empty($datarequest)) {
                        error_log("no tiene carrier");
                    } else {
                        $carrier = $datarequest['carrier'];

                        $partsCarrier = explode(":", $carrier);
                        if (count($partsCarrier) === 2) {
                            $type = $partsCarrier[0];
                            $idCarr = $partsCarrier[1];
                        }

                        if ($type == "int") {
                            $transp = Transportadora::where('id', $idCarr)->first();
                            $transpNombre = $transp ? $transp->nombre : 'Desconocida';
                        } else {
                            $transp = CarriersExternal::where('id', $idCarr)->first();
                            $transpNombre = $transp ? $transp->name : 'Desconocida';
                        }
                        $commentHist = "Transportadora asignada: " . $idCarr . "_" . $transpNombre;
                        //send email

                    }
                }
                if (
                    $pedido->users->isNotEmpty() && $pedido->users[0]->vendedores->isNotEmpty()
                ) {
                    $name_comercial = $pedido->users[0]->vendedores[0]->nombre_comercial;
                }
                $pedido->estado_interno = $value;
                $pedido->fecha_confirmacion = $currentDateTimeText;
                $pedido->confirmed_by = $idUser;
                $pedido->confirmed_at = $currentDateTime;
                $pedido->name_comercial = $name_comercial;
            }

            //new column
            $newHistory = [
                "area" => $key,
                "status" => $value,
                "timestap" => $currentDateTime,
                "comment" => $commentHist,
                "path" => "",
                "generated_by" => $idUser . "_" . $username
            ];

            if ($pedido->status_history === null || $pedido->status_history === '[]') {
                $pedido->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($pedido->status_history, true);

                $existingHistory[] = $newHistory;

                $pedido->status_history = json_encode($existingHistory);
            }

            $pedido->save();

            DB::commit();

            return response()->json([$pedido], 200);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("ERROR_updateFieldTime: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getByDateRangeValuesAudit(Request $request)
    {
        try {
            ini_set('memory_limit', '512M');

            $data = $request->json()->all();
            $startDate = $data['start'];
            $endDate = $data['end'];
            $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
            $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
            $Map = $data['and'];

            $pedidos = PedidosShopify::select(
                'id',
                'numero_orden',
                'nombre_shipping',
                'id_comercial',
                'status',
                'observacion',
                'comentario',
                'estado_interno',
                'estado_logistico',
                'estado_devolucion',
                'costo_devolucion',
                'costo_transportadora',
                'costo_envio'
            )
                ->with(['operadore.up_users', 'transportadora', 'users.vendedores', 'ruta'])
                ->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])
                ->where((function ($pedidos) use ($Map) {
                    foreach ($Map as $condition) {
                        foreach ($condition as $key => $valor) {
                            $parts = explode("/", $key);
                            $type = $parts[0];
                            $filter = $parts[1];

                            if (strpos($filter, '.') !== false) {
                                $relacion = substr($filter, 0, strpos($filter, '.'));
                                $propiedad = substr($filter, strpos($filter, '.') + 1);
                                $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                            } else {
                                if ($type == "equals") {
                                    $pedidos->where($filter, '=', $valor);
                                } else {
                                    $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
                                }
                            }
                        }
                    }
                }))->get();

            $isIdComercialPresent = collect($Map)->contains(function ($condition) {
                return isset($condition['equals/id_comercial']);
            });

            $isIdTransportPresent = collect($Map)->contains(function ($condition) {
                return isset($condition['equals/transportadora.transportadora_id']);
            });

            $estadoPedidos = $pedidos
                ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO', 'NOVEDAD'])
                ->groupBy('status')
                ->map(function ($group, $key) {
                    if ($key === 'NOVEDAD') {
                        $group = $group->where('estado_devolucion', '!=', 'PENDIENTE');
                    }

                    return $group->count();
                });

            $defaultStatuses = ['ENTREGADO', 'NO ENTREGADO', 'NOVEDAD'];

            foreach ($defaultStatuses as $status) {
                if (!isset($estadoPedidos[$status])) {
                    $estadoPedidos[$status] = 0;
                }
            }

            $sumatoriaCostoTransportadora = $isIdTransportPresent
                ? $pedidos->sum('costo_transportadora')
                : null;

            if ($sumatoriaCostoTransportadora === null) {
                // Manejar el caso cuando el valor es null
                $sumatoriaCostoTransportadora = 0.0;
            }

            $sumatoriaCostoEntrega = $isIdComercialPresent
                ? $pedidos->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])->sum('costo_envio')
                : 0.0;

            $sumatoriaCostoDevolucion = $isIdComercialPresent
                ? $pedidos->sum('costo_devolucion')
                : 0.0;

            $presentVendedor = 0;

            if ($isIdComercialPresent) {
                $presentVendedor = 1;
            }
            if ($isIdTransportPresent) {
                $presentVendedor = 2;
            }
            if ($isIdTransportPresent && $isIdComercialPresent) {
                $presentVendedor = 0;
            }

            return response()->json([
                'Costo_Transporte' => $sumatoriaCostoTransportadora,
                'Costo_Entrega' => $sumatoriaCostoEntrega,
                'Costo_Devolución' => $sumatoriaCostoDevolucion,
                'Filtro_Existente' => $presentVendedor,
                'Estado_Pedidos' => $estadoPedidos,
                // 'Cantidad_Total_Pedidos' => $pedidos->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'Costo_Transporte' => 0,
                'Costo_Entrega' => 0,
                'Costo_Devolución' => 0,
                'Filtro_Existente' => 0,
                'Estado_Pedidos' => [
                    'NOVEDAD' => 0,
                    'ENTREGADO' => 0,
                    'NO ENTREGADO' => 0,
                ],
                // 'Cantidad_Total_Pedidos' => 0
            ], 200);
        }
    }


    public function getOrdersForPrintedGuidesLaravelD(Request $request)
    {
        $data = $request->json()->all();
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $idWarehouse = $data['idw'];
        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }
        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************

        $pedidos = PedidosShopify::with(['transportadora', 'users', 'users.vendedores', 'pedidoFecha', 'ruta', 'printedBy', 'sentBy', 'product.warehouse.provider'])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where(function ($pedidos) use ($idWarehouse) {
                $pedidos->whereHas('product.warehouse', function ($query) use ($idWarehouse) {
                    $query->where('warehouse_id', $idWarehouse);
                });
            })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))
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
        // ! Ordenamiento ********************************** 
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
            $pedidos->orderBy(key($orderByText), reset($orderByText));
        } else {
            $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }



    public function getOrdersForPrintedGuidesLaravelO(Request $request)
    {
        $data = $request->json()->all();
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $idWarehouse = $data['idw'];
        $idWithdrawan = $data['idWithdrawan'];
        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }
        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************

        $pedidos = PedidosShopify::with(['transportadora', 'users', 'users.vendedores', 'pedidoFecha', 'ruta', 'printedBy', 'sentBy', 'product.warehouse.provider'])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where(function ($pedidos) use ($idWarehouse) {
                $pedidos->whereHas('product.warehouse', function ($query) use ($idWarehouse) {
                    $query->where('warehouse_id', $idWarehouse);
                });
            })
            ->where('withdrawan_by', $idWithdrawan)
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }))
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
        // ! Ordenamiento ********************************** 
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
            $pedidos->orderBy(key($orderByText), reset($orderByText));
        } else {
            $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($pedidos);
    }


    public function getOrdersCountByWarehouse(Request $request)
    {
        $data = $request->json()->all();
        $idTransportadora = $data['idTransportadora'];

        // Obtener el nombre de la transportadora
        $transportadoraNombre = Transportadora::where('id', $idTransportadora)->pluck('nombre')->first();

        // Obtener almacenes aprobados
        $warehouses = Warehouse::where('approved', 1)->get();

        // Transformaciones en las propiedades
        $warehouses = $warehouses->filter(function ($warehouse) use ($transportadoraNombre) {
            // Decodificar la cadena JSON en 'collection'
            $collection = json_decode($warehouse['collection'], true);

            // Filtrar almacenes con 'collectionTransport' igual al nombre de la transportadora
            return isset($collection['collectionTransport']) && $collection['collectionTransport'] == $transportadoraNombre;
        })->map(function ($warehouse) {
            // Decodificar la cadena JSON en 'collection'
            $collection = json_decode($warehouse['collection'], true);

            // Asignar la colección modificada de nuevo a 'collection'
            $warehouse['collection'] = $collection;

            return $warehouse;
        });
        // Reindexar el array numéricamente
        $warehouses = $warehouses->values();

        // Obtener el conteo de pedidos por estado para cada bodega
        $ordersCountByWarehouse = collect([]);

        foreach ($warehouses as $warehouse) {
            $pedidos = PedidosShopify::whereHas('product.warehouse', function ($query) use ($warehouse) {
                $query->where('warehouse_id', $warehouse->warehouse_id);
            })
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'IMPRESO')
                ->where(function ($query) {
                    $query->where('retirement_status', '!=', 'PEDIDO RETIRADO')
                        ->orWhereNull('retirement_status');
                })
                ->get();

            $count = $pedidos->count();

            $firstUsername = '...';
            $primerPedido = $pedidos->first();

            if ($primerPedido && $primerPedido->withdrawan_by) {
                $withdrawanParts = explode('-', $primerPedido->withdrawan_by);
                $operatorId = $withdrawanParts[1] ?? '...';

                if ($operatorId) {
                    $operator = Operadore::find($operatorId);
                    // Asegúrate de que 'up_users' sea el nombre correcto de la relación
                    if ($operator && $operator->up_users) {
                        $firstUsername = $operator->up_users[0]->username;
                    }
                }
            }

            $ordersCountByWarehouse->push([
                'warehouse_id' => $warehouse->warehouse_id,
                'count' => $count,
                'first_username' => $firstUsername,
            ]);
        }

        return response()->json(['ordersCountByWarehouse' => $ordersCountByWarehouse]);
    }



    public function getOrdersCountByWarehouseByOrders(Request $request)
    {
        $data = $request->json()->all();
        $withdrawanBy = $data['withdrawan_by'];

        $orders = PedidosShopify::with(['product.warehouse'])
            ->where('withdrawan_by', $withdrawanBy)
            ->where('retirement_status', 'PEDIDO ASIGNADO')
            ->get();

        $warehouses = $orders->map(function ($order) {
            $warehouse = $order->product->warehouse->toArray();
            return $warehouse;
        });

        $warehouses = $warehouses->unique(function ($item) {
            return $item['warehouse_id'];
        })->values();

        // Obtener el conteo de pedidos por estado para cada bodega
        $ordersCountByWarehouse = collect([]);

        foreach ($warehouses as $warehouse) {
            $count = PedidosShopify::whereHas('product.warehouse', function ($query) use ($warehouse) {
                $query->where('warehouse_id', $warehouse['warehouse_id']); // Accede usando notación de array
            })
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'IMPRESO')
                ->where('retirement_status', 'PEDIDO ASIGNADO')
                ->count();

            $ordersCountByWarehouse->push([
                'warehouse_id' => $warehouse['warehouse_id'], // Accede usando notación de array
                'count' => $count,
            ]);
        }


        return response()->json(['ordersCountByWarehouse' => $ordersCountByWarehouse]);
    }


    public function getOrdersForPrintGuidesInSendGuidesPrincipalLaravelD(Request $request)
    {
        $data = $request->json()->all();
        // $startDate = $data['start'];
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');

        $populate = $data['populate'];

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];

        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        // ! *************************************
        $Map = $data['and'];
        $not = $data['not'];
        // ! *************************************

        $pedidos = PedidosShopify::with($populate)
            // ->whereRaw("STR_TO_DATE(marca_tiempo_envio, '%e/%c/%Y') = ?", [$startDateFormatted])
            ->where(function ($pedidos) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $searchTerm);
                    } else {
                        $pedidos->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            // ->where(function ($pedidos) use ($idWarehouse) {
            //     $pedidos->whereHas('product.warehouse', function ($query) use ($idWarehouse) {
            //         $query->where('warehouse_id', $idWarehouse);
            //     });
            // })
            ->where((function ($pedidos) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if ($key === '/marca_tiempo_envio') {
                            $startDateFormatted = Carbon::createFromFormat('j/n/Y', $valor)->format('Y-m-d');
                            $pedidos->whereRaw("STR_TO_DATE(marca_tiempo_envio, '%e/%c/%Y') = ?", [$startDateFormatted]);
                        } elseif (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            if ($type == "equals") {
                                $pedidos->where($filter, '=', $valor);
                            } else {
                                $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
                            }
                        }
                    }
                }
            }))->where((function ($pedidos) use ($not) {
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
        // ! Ordenamiento ********************************** 
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
            $pedidos->orderBy(key($orderByText), reset($orderByText));
        } else {
            $pedidos->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $pedidos = $pedidos->paginate($pageSize, ['*'], 'page', $pageNumber);
        return response()->json($pedidos);
    }


    public function getValuesDropdownSendGuide(Request $request)
    {
        $data = $request->json()->all();
        $monthYear = $data['monthYear']; // '1/2024' por ejemplo
        // $idWarehouse = $data['idWarehouse']; // '1/2024' por ejemplo

        // Obtiene el primer y último día del mes especificado
        $startDate = Carbon::createFromFormat('n/Y', $monthYear)->startOfMonth();
        $endDate = Carbon::createFromFormat('n/Y', $monthYear)->endOfMonth();

        // Formatea las fechas para la consulta
        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        // Realiza la consulta para contar los pedidos por día
        $pedidos = PedidosShopify
            ::with(['product.warehouse.provider'])
            ->selectRaw('count(*) as cantidad, DATE_FORMAT(STR_TO_DATE(marca_tiempo_envio, "%d/%c/%Y"), "%e/%m/%Y") as fecha')
            // ... resto de tu consulta

            // ->where(function ($pedidos) use ($idWarehouse) {
            //     $pedidos->whereHas('product.warehouse', function ($query) use ($idWarehouse) {
            //         $query->where('warehouse_id', $idWarehouse);
            //     });
            // })
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->whereBetween(DB::raw('STR_TO_DATE(marca_tiempo_envio, "%d/%m/%Y")'), [$startDateFormatted, $endDateFormatted])
            ->groupBy('fecha')
            ->orderBy('fecha', 'desc')
            ->get();

        // Ajusta el formato de la fecha para los días menores a 10
        $pedidos->transform(function ($pedido) {
            unset($pedido->product); // Elimina el producto
            $fecha = Carbon::createFromFormat('d/m/Y', $pedido->fecha); // Asegúrate que el formato aquí coincida con cómo se almacena la fecha en la base de datos
            $pedido->fecha = $fecha->format('j/n/Y'); // 'j' para el día y 'n' para el mes sin ceros iniciales
            return $pedido;
        });

        return response()->json($pedidos);
    }

    public function getValuesDropdownSendGuideOp(Request $request)
    {
        $data = $request->json()->all();
        $monthYear = $data['monthYear']; // '1/2024' por ejemplo
        // $WithdrawanBy = $data['WithdrawanBy']; // '1/2024' por ejemplo

        // Obtiene el primer y último día del mes especificado
        $startDate = Carbon::createFromFormat('n/Y', $monthYear)->startOfMonth();
        $endDate = Carbon::createFromFormat('n/Y', $monthYear)->endOfMonth();

        // Formatea las fechas para la consulta
        $startDateFormatted = $startDate->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        // Realiza la consulta para contar los pedidos por día
        $pedidos = PedidosShopify
            ::with(['product.warehouse.provider'])
            ->selectRaw('count(*) as cantidad, DATE_FORMAT(STR_TO_DATE(marca_tiempo_envio, "%d/%c/%Y"), "%e/%m/%Y") as fecha')
            // ->where('withdrawan_by', $WithdrawanBy)
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('retirement_status', 'PEDIDO RETIRADO')
            ->whereBetween(DB::raw('STR_TO_DATE(marca_tiempo_envio, "%d/%m/%Y")'), [$startDateFormatted, $endDateFormatted])
            ->groupBy('fecha')
            ->orderBy('fecha', 'desc')
            ->get();

        // Ajusta el formato de la fecha para los días menores a 10
        $pedidos->transform(function ($pedido) {
            unset($pedido->product); // Elimina el producto
            $fecha = Carbon::createFromFormat('d/m/Y', $pedido->fecha); // Asegúrate que el formato aquí coincida con cómo se almacena la fecha en la base de datos
            $pedido->fecha = $fecha->format('j/n/Y'); // 'j' para el día y 'n' para el mes sin ceros iniciales
            return $pedido;
        });

        return response()->json($pedidos);
    }


    public function addWithdrawanBy(Request $request)
    {
        $data = $request->json()->all();
        $idOrder = $data['idOrder'];
        $idComp = $data['id'];
        PedidosShopify::where('id', $idOrder)
            ->update(
                [
                    'withdrawan_by' => $idComp,
                    'retirement_status' => 'PEDIDO ASIGNADO'
                ]
            );
    }

    public function updateRetirementStatus(Request $request)
    {
        $data = $request->json()->all();
        $idOrder = $data['idOrder'];
        // $idComp = $data['id'];     
        PedidosShopify::where('id', $idOrder)
            ->update(
                [
                    // 'withdrawan_by' => $idComp,
                    'retirement_status' => 'PEDIDO RETIRADO'
                ]
            );
    }

    public function endRetirement(Request $request)
    {
        $data = $request->json()->all();
        $idOrder = $data['idOrder'];
        PedidosShopify::where('id', $idOrder)
            ->update(
                [
                    'withdrawan_by' => null,
                    'marca_tiempo_envio' => null,
                    'sent_by' => null,
                    'sent_at' => null,
                    'retirement_status' => NULL
                ]
            );
    }


    public function getWarehousesofOrders(Request $request)
    {
        $data = $request->all();
        $withdrawanBy = $data['withdrawan_by'];

        // Obtiene los pedidos según los criterios especificados
        $orders = PedidosShopify::with(['product.warehouse'])
            ->where('withdrawan_by', $withdrawanBy)
            ->where('retirement_status', 'PEDIDO ASIGNADO')
            ->get();

        $daysOfWeek = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

        $warehouses = $orders->map(function ($order) use ($daysOfWeek) {
            $warehouse = $order->product->warehouse->toArray();
            $warehouseWithdrawanBy = $order->withdrawan_by;

            // Extrayendo el ID del operador
            $onlyId = last(explode('-', $warehouseWithdrawanBy));

            // Asegúrate de que solo obtienes un resultado y de que el ID es un número
            if (is_numeric($onlyId)) {
                $operator = Operadore::with('up_users')->where('id', $onlyId)->first();

                if ($operator && $operator->up_users->isNotEmpty()) {
                    $warehouse['operatorNameWithdrawal'] = $operator->up_users[0]->username;
                } else {
                    $warehouse['operatorNameWithdrawal'] = 'Operador no encontrado';
                }
            } else {
                $warehouse['operatorNameWithdrawal'] = 'ID inválido';
            }
            // Decodifica la cadena JSON en 'collection', si es necesario
            $collection = json_decode($warehouse['collection'], true);

            // Convierte 'collectionDays' a nombres de días
            if (isset($collection['collectionDays'])) {
                $collection['collectionDays'] = array_map(function ($day) use ($daysOfWeek) {
                    return $daysOfWeek[$day];
                }, $collection['collectionDays']);
            }

            $warehouse['collection'] = $collection; // Vuelve a asignar la colección transformada
            return $warehouse;
        });


        $warehouses = $warehouses->unique(function ($item) {
            return $item['warehouse_id'];
        })->values();

        return response()->json(['warehouses' => $warehouses]);
    }


    public function updateOrCreatePropertyGestionedNovelty(Request $request, $id)
    {
        try {
            $data = $request->json()->all();
            $property = $data['property'];
            list($propertyName, $propertyValue) = explode(':', $property);

            $order = PedidosShopify::find($id);

            $edited_novelty = !empty($order["gestioned_novelty"]) ? json_decode($order["gestioned_novelty"], true) : [];

            // Actualizar o crear la propiedad
            $edited_novelty[$propertyName] = $propertyValue;

            // Guardar los cambios en el modelo
            $order->gestioned_novelty = json_encode($edited_novelty);
            $order->save();

            return response()->json(['success' => 'Property  updated successfully!']);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function updateGestionedNovelty(Request $request, $id)
    {
        error_log("updateGestionedNovelty");
        DB::beginTransaction();
        try {
            $data = $request->json()->all();
            $noveltyState = $data['novelty_state'];
            $startDate = $data['start'];
            $startDateFormatted = Carbon::createFromFormat('j/n/Y H:i:s', $startDate)->format('Y-m-d H:i:s');

            $order = PedidosShopify::find($id);

            // Inicializar 'edited_novelty' y 'lastTry'
            $edited_novelty = $order["gestioned_novelty"] != null ? json_decode($order["gestioned_novelty"], true) : [];
            $lastTry = $edited_novelty['try'] ?? 0;


            // verified
            // novelty_status

            switch ($noveltyState) {
                case 1:
                    // Incrementar el intento solo si está entre 0 y 4
                    if ($lastTry >= 0 && $lastTry < 5) {
                        $lastTry++;
                        $edited_novelty["state"] = 'gestioned';
                        $edited_novelty["comment"] = $data["comment"];
                        // $edited_novelty["verified"] = $data["verified"];
                        $edited_novelty["id_user"] = $data["id_user"];
                        $edited_novelty["m_t_g"] = $startDateFormatted;
                    } elseif ($lastTry == 5) {
                        return response()->json(["response" => "returned edited novelty failed"], Response::HTTP_OK);
                    }
                    break;

                case 2:
                    $edited_novelty["state"] = 'resolved';
                    $edited_novelty["comment"] = $data['comment'];
                    $edited_novelty["id_user"] = $data['id_user'];
                    $edited_novelty["m_t_g"] = $startDateFormatted;

                    $order->status = "NOVEDAD RESUELTA";

                    $user = UpUser::where('id', $data["id_user"])->first();
                    $username = $user ? $user->username : null;
                    //new column
                    $newHistory = [
                        "area" => "status",
                        "status" => "NOVEDAD RESUELTA",
                        "timestap" => date('Y-m-d H:i:s'),
                        "comment" => "",
                        "path" => "",
                        "generated_by" => $data["id_user"] . "_" . $username
                    ];

                    if ($order->status_history === null || $order->status_history === '[]') {
                        $order->status_history = json_encode([$newHistory]);
                    } else {
                        $existingHistory = json_decode($order->status_history, true);

                        $existingHistory[] = $newHistory;

                        $order->status_history = json_encode($existingHistory);
                    }

                    $order->save();

                    break;

                default:
                    $edited_novelty["state"] = 'ok';
                    $edited_novelty["comment"] = $data['comment'];
                    $edited_novelty["id_user"] = $data['id_user'];
                    $edited_novelty["m_t_g"] = $startDateFormatted;
                    break;
            }

            if ($order->fecha_entrega == "" || $order->fecha_entrega === 'null' || $order->fecha_entrega === null) {
                error_log("fechaEntregaNull_" . $order->id);
                $statusHistory = json_decode($order->status_history, true);

                $ultimoReagendado = collect($statusHistory)
                    ->filter(fn($item) => $item['status'] === 'REAGENDADO')
                    ->sortByDesc('timestap')
                    ->first();

                $comentario = $ultimoReagendado['comment'] ?? null;

                if ($comentario) {
                    $partes = explode(':', $comentario);

                    if (count($partes) > 1) {
                        $fechaTexto = trim($partes[1]);
                        $fechaTexto = str_replace(' ', '', $fechaTexto);

                        try {
                            $fecha = \Carbon\Carbon::createFromFormat('d/m/Y', $fechaTexto);
                            if ($fecha->isToday() || $fecha->isFuture()) {
                                // error_log("Fecha válida: " . $fecha->toDateString());
                                $order->fecha_entrega = $fechaTexto;
                            } else {
                                error_log("Fecha NO válida, es pasada: " . $fecha->toDateString());
                            }
                        } catch (\Exception $e) {
                            error_log("Error al convertir la fecha: $fechaTexto");
                        }
                    }
                } else {
                    error_log("No hay comentario en el último REAGENDADO.");
                }
            }
            $edited_novelty['try'] = $lastTry;


            $comment = $edited_novelty['comment'];
            $parts = explode('UID:', $comment, 2);
            if (count($parts) === 2) {
                $new_comment = $parts[0] . "({$edited_novelty['try']})" . $parts[1];
            } else {
                $new_comment = $comment;
            }

            $edited_novelty["comment"] = $new_comment;
            $order["gestioned_novelty"] = json_encode($edited_novelty);
            $order->save();

            DB::commit();

            return response()->json(["response" => "Novelty updated successfully", "edited_novelty" => $edited_novelty], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("ERROR_updateGestionedNovelty: $e");
            return response()->json(["response" => "Failed to update novelty (-_-)/ "], Response::HTTP_NOT_FOUND);
        }
    }

    public function updateGestionedPaymentCostDelivery(Request $request)
    {
        try {
            $data = $request->json()->all();
            $noveltyState = $data['payment_state'];
            $startDate = $data['start'];
            $ids = $data['ids'];
            error_log($startDate);
            $startDateFormatted = Carbon::createFromFormat('j/n/Y H:i:s', $startDate)->format('Y-m-d H:i:s');
            $id_user = $data['id_user'];

            $editedPayments = [];

            foreach ($ids as $id) {
                $order = PedidosShopify::find($id);
                if ($order) {
                    $edited_payment = $order["gestioned_payment_cost_delivery"] != null
                        ? json_decode($order["gestioned_payment_cost_delivery"], true)
                        : [];

                    if ($noveltyState == 1) {
                        $edited_payment["state"] = 1;
                        $edited_payment["id_user"] = $id_user;
                        $edited_payment["m_t_g"] = $startDateFormatted;

                        //update payment_status TransaccionGlobal
                        $transactions = TransaccionGlobal::query()
                            ->where('id_order', $id)
                            ->where('status', 'ENTREGADO')
                            ->whereNot('origin', 'Referenciado')
                            ->get();

                        foreach ($transactions as $transaction) {
                            $transactionFound = TransaccionGlobal::where('id', $transaction['id'])
                                ->first();
                            // error_log($transaction);
                            if ($transactionFound != null) {
                                $transactionFound->payment_status = "ACREDITADO";
                                $transactionFound->save();
                            }
                        }

                        //update payment_status ProviderTransaction
                        $transactionsProvider = ProviderTransaction::query()
                            ->where('origin_id', $id)
                            ->get();

                        foreach ($transactionsProvider as $transactionProv) {
                            $transactionProvFound = ProviderTransaction::where('id', $transactionProv['id'])
                                ->first();
                            // error_log($transaction);
                            if ($transactionProvFound != null) {
                                $transactionProvFound->payment_status = "ACREDITADO";
                                $transactionProvFound->save();
                            }
                        }
                    }
                    // else if ($noveltyState == 0) {
                    //     $edited_payment["state"] = 0;
                    //     $edited_payment["id_user"] = $id_user;
                    //     $edited_payment["m_t_g"] = $startDateFormatted;
                    // }

                    $order["gestioned_payment_cost_delivery"] = json_encode($edited_payment);
                    $order->save();

                    $editedPayments[] = $edited_payment;
                }
            }

            return response()->json([
                "response" => "Payment updated successfully",
                "edited_novelty" => $editedPayments
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "response" => "Failed to update Payment (-_-)/ ",
                "error" => $th->getMessage() // Include error message for debugging
            ], Response::HTTP_NOT_FOUND);
        }
    }
    public function updateGestionedPaymentCostDeliveryU(Request $request, $id)
    {
        try {
            $data = $request->json()->all();
            $noveltyState = $data['payment_state'];
            $startDate = $data['start'];
            error_log($startDate);
            $startDateFormatted = Carbon::createFromFormat('j/n/Y H:i:s', $startDate)->format('Y-m-d H:i:s');
            $id_user = $data['id_user'];

            $editedPayments = [];

            $order = PedidosShopify::find($id);
            if ($order) {
                $edited_payment = $order["gestioned_payment_cost_delivery"] != null
                    ? json_decode($order["gestioned_payment_cost_delivery"], true)
                    : [];

                if ($noveltyState == 0) {
                    $edited_payment["state"] = 0;
                    $edited_payment["id_user"] = $id_user;
                    $edited_payment["m_t_g"] = $startDateFormatted;

                    //update payment_status
                    $transactions = TransaccionGlobal::query()
                        ->where('id_order', $id)
                        ->where('status', 'ENTREGADO')
                        ->whereNot('origin', 'Referenciado')
                        ->get();

                    foreach ($transactions as $transaction) {
                        $transactionFound = TransaccionGlobal::where('id', $transaction['id'])
                            ->first();
                        // error_log($transaction);
                        if ($transactionFound != null) {
                            $transactionFound->payment_status = "PENDIENTE";
                            $transactionFound->save();
                        }
                    }

                    //update payment_status ProviderTransaction
                    $transactionsProvider = ProviderTransaction::query()
                        ->where('origin_id', $id)
                        ->get();

                    foreach ($transactionsProvider as $transactionProv) {
                        $transactionProvFound = ProviderTransaction::where('id', $transactionProv['id'])
                            ->first();
                        // error_log($transaction);
                        if ($transactionProvFound != null) {
                            $transactionProvFound->payment_status = "PENDIENTE";
                            $transactionProvFound->save();
                        }
                    }
                }
                // else if ($noveltyState == 0) {
                //     $edited_payment["state"] = 0;
                //     $edited_payment["id_user"] = $id_user;
                //     $edited_payment["m_t_g"] = $startDateFormatted;
                // }

                $order["gestioned_payment_cost_delivery"] = json_encode($edited_payment);
                $order->save();

                $editedPayments[] = $edited_payment;
            }

            return response()->json([
                "response" => "Payment updated successfully",
                "edited_novelty" => $editedPayments
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "response" => "Failed to update Payment (-_-)/ ",
                "error" => $th->getMessage() // Include error message for debugging
            ], Response::HTTP_NOT_FOUND);
        }
    }

    //  *
    public function orderProducto(Request $request)
    {
        error_log("orderProducto"); //from catalog

        DB::beginTransaction();
        try {

            //GENERATE DATE
            $currentDate = now();
            $fechaActual = $currentDate->format('d/m/Y');

            $dateOrder = "";
            //VARIABLES FOR ENTITY
            // $order_number = $request->input('NumeroOrden');
            $data = $request->json()->all();
            $generatedBy = $data['generatedBy'];
            $IdComercial = $data['IdComercial'];
            $Name_Comercial = $data['Name_Comercial'];
            $name = $data['NombreShipping'];
            $address = $data['DireccionShipping'];
            $phone = $data['TelefonoShipping'];
            $total_price = $data['PrecioTotal'];
            $city = $data['CiudadShipping'];
            if ($IdComercial == 852) {
                error_log('orderProducto_dataRequest: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
            if ($city == null || $city == "null" || $city == "") {
                error_log("orderProducto_error_city_null: $city ");
                return response()->json(['message' => 'Error, ciudad sin datos'], 404);
            }
            $product = $data['ProductoP'];
            $productE = $data['ProductoExtra'];
            $cantidadTotal = $data['Cantidad_Total'];
            $PrecioTotal = $data['PrecioTotal'];
            $formattedPrice = str_replace(",", ".", str_replace(["$", " "], "", $PrecioTotal));
            if ($data['Observacion'] != null) {
                $Observacion = $data['Observacion'];
            } else {
                $Observacion = "";
            }
            // $sku = $request->input('sku');
            $recaudo = $data['recaudo'];
            $apertura = $data['apertura'];
            // $productId = $data['product_id'];
            $variant_details = $data['variant_details'];
            //transp
            $newrouteId = $data['ruta'];
            $newtransportadoraId = $data['transportadora'];
            //
            $carrierExternalId = $data["carrier_id"];
            $ciudadDes = $data["ciudad_des"];
            $firstIdProduct = 0; //mainProduct
            $peso_total = $data['peso_total'];
            $provincia_shipping = $data['provincia_shipping'];
            $city_id = $data['city_id'];

            $nombre_normalizado = Normalizer::normalize($name, Normalizer::FORM_C);
            $direccion_normalizado = Normalizer::normalize($address, Normalizer::FORM_C);

            $numOrderstart = 1001; // Número inicial sin ceros a la izquierda
            $manualOrders = PedidosShopify::where('id_comercial', $IdComercial)
                ->where('numero_orden', 'like', 'E%')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($manualOrders->isNotEmpty()) {
                $encontrado = false;
                foreach ($manualOrders as $order) {
                    if ($order->numero_orden === "E001001") {
                        $encontrado = true;
                        break;
                    }
                }

                $lastOrder = $manualOrders->first();
                if ($encontrado) {
                    $lastOrderNumero = $lastOrder->numero_orden;
                    preg_match('/\d+/', $lastOrderNumero, $matches);
                    $numeroExtraido = $matches[0];
                    $nextOrderNumero = $numeroExtraido + 1;
                    $NumeroOrden = "E" . sprintf("%06d", $nextOrderNumero); // Aplicar ceros a la izquierda si es necesario
                } else {
                    $NumeroOrden = "E" . sprintf("%06d", $numOrderstart);
                }
            } else {
                $NumeroOrden = "E" . sprintf("%06d", $numOrderstart);
            }
            //ADD PRODUCT TO LIST FOR NEW OBJECT

            $search = PedidosShopify::where([
                'numero_orden' => $NumeroOrden,
                // 'tienda_temporal' => $productos[0]['vendor'],
                'id_comercial' => $IdComercial,
            ])->get();

            //
            $createOrder = new PedidosShopify();

            // IF ORDER NOT EXIST CREATE ORDER
            if ($search->isEmpty()) {

                // error_log("$variant_details");
                $productsList = json_decode($variant_details, true);

                // $uniqueNames = [];
                // $uniqueProducts = [];

                // foreach ($productsList as $item) {
                //     if (!in_array($item['name'], $uniqueNames)) {
                //         $uniqueNames[] = $item['name'];
                //         $uniqueProducts[] = $item;
                //     }
                // }

                // print_r($uniqueProducts);

                if (isset($productsList[0])) {
                    $item = $productsList[0];
                    // $firstSku = $item['sku']; //skuGen o skuVar

                    // $parts = explode('C', $firstSku);
                    // $id_product = end($parts);
                    $firstIdProduct = $item['name'];
                }

                $uniqueIds = [];
                // $uniqueProducts = [];

                foreach ($productsList as $item) {
                    if (!in_array($item['name'], $uniqueIds)) {
                        $uniqueIds[] = $item['name'];
                    }
                }

                // error_log("uniqueIds: " . json_encode($uniqueIds));

                // $uniqueSkus = [];
                // $uniqueProducts = [];

                // foreach ($productsList as $item) {
                //     $sku = $item['sku'];
                //     $skuPart = substr($sku, strrpos($sku, 'C') + 1);

                //     if (!in_array($skuPart, $uniqueSkus)) {
                //         $uniqueSkus[] = $skuPart;
                //         // $uniqueProducts[] = $item;
                //     }
                // }

                // print_r($uniqueProducts);

                // Formatear la fecha y hora actual
                $Marca_T_I = date("d/m/Y H:i");
                $Fecha_Confirmacion = date("d/m/Y H:i");
                $currentDateTime = date('Y-m-d H:i:s');

                $createOrder->numero_orden = $NumeroOrden;
                $createOrder->direccion_shipping = $direccion_normalizado;
                $createOrder->nombre_shipping = $nombre_normalizado;
                $createOrder->telefono_shipping = $phone;
                $createOrder->precio_total = $formattedPrice;
                $createOrder->observacion = $Observacion;
                $createOrder->ciudad_shipping = $city;
                $createOrder->id_comercial = $IdComercial;
                $createOrder->producto_p = $product;
                $createOrder->producto_extra = $productE;
                $createOrder->cantidad_total = $cantidadTotal;
                $createOrder->name_comercial = $Name_Comercial;
                $createOrder->tienda_temporal = $Name_Comercial;
                $createOrder->marca_t_i = $Marca_T_I;
                // $createOrder->estado_interno = "CONFIRMADO";
                $createOrder->status = "PEDIDO PROGRAMADO";
                $createOrder->estado_logistico = 'PENDIENTE';
                $createOrder->estado_pagado = 'PENDIENTE';
                $createOrder->estado_pago_logistica = 'PENDIENTE';
                $createOrder->estado_devolucion = 'PENDIENTE';
                $createOrder->do = 'PENDIENTE';
                $createOrder->dt = 'PENDIENTE';
                $createOrder->dl = 'PENDIENTE';
                // $createOrder->fecha_confirmacion = $Fecha_Confirmacion;
                // $createOrder->confirmed_by = $generatedBy;
                // $createOrder->confirmed_at = $currentDateTime;
                $createOrder->id_product = $firstIdProduct;
                $createOrder->variant_details = $variant_details;
                $createOrder->recaudo = $recaudo;
                $createOrder->apertura = $apertura;
                $createOrder->peso_total = $peso_total;
                $createOrder->estado_interno = "PENDIENTE";
                $createOrder->provincia_shipping = $provincia_shipping;
                $createOrder->city_id = $city_id;

                if ($newrouteId != 0) {
                    error_log("*****Transp Int********\n");
                    $createOrder->estado_interno = "CONFIRMADO";
                    $createOrder->fecha_confirmacion = $Fecha_Confirmacion;
                    $createOrder->confirmed_by = $generatedBy;
                    $createOrder->confirmed_at = $currentDateTime;
                    //new column
                    $user = UpUser::where('id', $generatedBy)->first();
                    $username = $user ? $user->username : null;

                    $transp = Transportadora::where('id', $newtransportadoraId)->first();
                    $transpNombre = $transp ? $transp->nombre : null;

                    $newHistory = [
                        "area" => "estado_interno",
                        "status" => "CONFIRMADO",
                        "timestap" => date('Y-m-d H:i:s'),
                        "comment" => "Transportadora asignada: " . $newtransportadoraId . "_" . $transpNombre,
                        "path" => "",
                        "generated_by" => $generatedBy . "_" . $username
                    ];

                    $createOrder->status_history = json_encode([$newHistory]);
                }

                $createOrder->save();

                // error_log("******************proceso 3 terminado************************\n");

                foreach ($uniqueIds as $idProd) {

                    $newPedidoProduct = new PedidosProductLink();
                    $newPedidoProduct->pedidos_shopify_id = $createOrder->id;
                    $newPedidoProduct->product_id = $idProd;
                    // $newPedidoProduct->variant_sku =  $item['sku']; //skuGen o skuVar
                    // $newPedidoProduct->units =  $item['quantity'];
                    $newPedidoProduct->save();
                }


                $dateOrder;
                // SEARCH DATE ORDER FOR RELLATION
                $searchDate = PedidoFecha::where('fecha', now()->format('d/m/Y'))->get();
                $pedidoShopifyOrder = 0;
                // IF DATE ORDER NOT EXIST CREATE ORDER AND ADD ID ELSE IF ONLY ADD DATE ORDER ID VALUE
                if ($searchDate->isEmpty()) {
                    // Crea un nuevo registro de fecha
                    $newDate = new PedidoFecha();
                    $newDate->fecha = now()->format('d/m/Y');
                    $newDate->save();

                    // Obtén el ID del nuevo registro
                    $dateOrder = $newDate->id;
                } else {
                    // Si la fecha existe, obtén el ID del primer resultado
                    $dateOrder = $searchDate[0]->id;

                    $ultimoPedidoFechaLink = PedidosShopifiesPedidoFechaLink::where('pedido_fecha_id', $dateOrder)
                        ->orderBy('pedidos_shopify_order', 'desc')
                        ->first();

                    if ($ultimoPedidoFechaLink) {
                        $pedidoShopifyOrder = $ultimoPedidoFechaLink->pedidos_shopify_order + 1;
                    } else {
                        $pedidoShopifyOrder = 1;
                    }
                }


                // Obtener la fecha y hora actual
                $createPedidoFecha = new PedidosShopifiesPedidoFechaLink();
                $createPedidoFecha->pedidos_shopify_id = $createOrder->id;
                $createPedidoFecha->pedido_fecha_id = $dateOrder;
                $createPedidoFecha->pedidos_shopify_order = $pedidoShopifyOrder;
                $createPedidoFecha->save();

                $createUserPedido = new UpUsersPedidosShopifiesLink();
                $createUserPedido->user_id = $IdComercial;
                $createUserPedido->pedidos_shopify_id = $createOrder->id;
                $createUserPedido->save();

                error_log("******************proceso 4 terminado************************\n");
                if ($newrouteId != 0) {
                    error_log("*****Transp inter********\n");
                    $createPedidoRuta = new PedidosShopifiesRutaLink();
                    $createPedidoRuta->pedidos_shopify_id = $createOrder->id;
                    $createPedidoRuta->ruta_id = $newrouteId;
                    $createPedidoRuta->save();

                    $createPedidoTransportadora = new PedidosShopifiesTransportadoraLink();
                    $createPedidoTransportadora->pedidos_shopify_id = $createOrder->id;
                    $createPedidoTransportadora->transportadora_id = $newtransportadoraId;
                    $createPedidoTransportadora->save();
                } else {
                    error_log("*****Carrier External********\n");
                    $createPedidoCarrier = new PedidosShopifiesCarrierExternalLink();
                    $createPedidoCarrier->pedidos_shopify_id = $createOrder->id;
                    $createPedidoCarrier->carrier_id = $carrierExternalId;
                    $createPedidoCarrier->city_external_id = $ciudadDes;
                    $createPedidoCarrier->save();
                }
            }

            DB::commit();
            return response()->json([
                "data" => $createOrder,
            ], 200);
        } catch (\Exception $e) {
            error_log("orderProducto_error: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rutaTranspDestroy($id)
    {
        DB::beginTransaction();
        try {
            error_log("rutaTranspDestroy");
            $pedido = PedidosShopify::where('id', $id)->first();

            $pedido->estado_interno = "PENDIENTE";
            $pedido->fecha_confirmacion = null;
            $pedido->confirmed_by = null;
            $pedido->confirmed_at = null;
            $pedido->name_comercial = null;
            $pedido->save();

            $pedidoRuta = PedidosShopifiesRutaLink::where('pedidos_shopify_id', $id)->first();
            $pedidoTransportadora = PedidosShopifiesTransportadoraLink::where('pedidos_shopify_id', $id)->first();

            if (!$pedidoRuta || !$pedidoTransportadora) {
                return response()->json(['message' => 'No se encontraro pedidoRuta o pedidoTransportadora con el ID especificado'], 404);
            }

            $pedidoRuta->delete();
            $pedidoTransportadora->delete();


            DB::commit();
            return response()->json(['message' => 'PedidoRuta y PedidoTransportadora eliminados correctamente'], 200);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }


    public function sendEmailConfirmtoProvider($idOrder)
    {
        try {
            error_log("sendEmailConfirmtoProvider");
            $pedido = PedidosShopify::with('vendor')
                ->where('id', $idOrder)->first();

            $numeroOrden = $pedido->numero_orden;
            $nameComercial = $pedido->tienda_temporal;
            if ($pedido->vendor) {
                $nameComercial = $pedido->vendor->nombre_comercial;
            }

            $code = $nameComercial . '-' . $numeroOrden;

            if ($pedido->variant_details == null || $pedido->variant_details == "[]") {
                error_log("No existe variant_details");
                return response()->json(['message' => 'No existe variant_details'], 200);
            }

            if ($pedido->users->isNotEmpty() && $pedido->users[0]->vendedores->isNotEmpty()) {
                $nameComercial = $pedido->users[0]->vendedores[0]->nombre_comercial;
            }

            $variantDetails = json_decode($pedido->variant_details, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($variantDetails)) {
                return response()->json(['message' => 'Error al decodificar variant_details'], 400);
            }

            $uniqueIds = $this->extractUniqueIds($variantDetails);
            if (empty($uniqueIds)) {
                return response()->json(['message' => 'No se encontraron unique IDs'], 400);
            }

            $firstUniqueId = $uniqueIds[0];
            $prodNames = "";

            if (count($uniqueIds) === 1) {
                $prodNames = $pedido->producto_p;
            } else {
                $prodNames = $pedido->producto_p . " " . $pedido->producto_extra;
            }

            $mainProduct = Product::with(['warehouse.provider.user', 'warehouse.up_users'])
                ->select('product_id', 'product_name', 'warehouse_id')
                ->find($firstUniqueId);

            $emailsToNotify = [];
            if (!empty($mainProduct->warehouse)) {
                if (!empty($mainProduct->warehouse->provider)) {
                    $provName = $mainProduct->warehouse->provider->name;
                }

                if (!empty($mainProduct->warehouse->up_users)) {
                    foreach ($mainProduct->warehouse->up_users as $user) {
                        if (!empty($user->pivot->notify) && $user->pivot->notify == 1) {
                            $emailsToNotify[] = $user->email;
                        }
                    }
                }
            }

            $subject = 'Nueva orden: ' . $code;
            $message = "Nueva Orden: $code\nProveedor: $provName\nID Producto/s: " . implode(',', $uniqueIds) . "\nCantidad: $pedido->cantidad_total\nProducto: $prodNames\n\n";

            // Envío de correos
            // if (!empty($emailsToNotify)) {
            //     foreach ($emailsToNotify as $email) {
            //         Mail::raw($message, function ($mail) use ($email, $subject) {
            //             $mail->to($email)->subject($subject);
            //         });
            //     }
            // } else if (!empty($mainProduct->warehouse->provider->user)) {
            //     $providerUserEmail = $mainProduct->warehouse->provider->user->email;
            //     Mail::raw($message, function ($mail) use ($providerUserEmail, $subject) {
            //         $mail->to($providerUserEmail)->subject($subject);
            //     });
            // } else {
            //     error_log("No se encontró un correo del proveedor para enviar.");
            // }

            return response()->json(['message' => 'Correo/s enviado/s'], 200);
        } catch (\Exception $e) {
            error_log("ERROR_sendEmailConfirmtoProvider: $e");
            return response()->json(['error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()], 500);
        }
    }


    function extractUniqueIds(array $variantDetails): array
    {
        $uniqueSkus = [];
        $pattern = '/^(.*[^C])C\d+$/';

        foreach ($variantDetails as $item) {
            $sku = $item['sku'] ?? null;

            if (!empty($sku) && preg_match($pattern, $sku)) {
                $uniqueSkus[] = $sku;
            }
        }

        $uniqueSkus = array_unique($uniqueSkus);

        $digitsList = [];
        foreach ($uniqueSkus as $sku) {
            $indexOfC = strrpos($sku, 'C');
            if ($indexOfC !== false && $indexOfC + 1 < strlen($sku)) {
                $digits = substr($sku, $indexOfC + 1);

                if (is_numeric($digits)) {
                    $product = Product::find($digits);
                    if ($product != null) {
                        $digitsList[] = (int) $digits;
                    }
                }
            }
        }

        return $digitsList;
    }

    //*
    public function updateGestionedPaymentCostDeliveryByIdExternal(Request $request)
    {
        ini_set('max_execution_time', 300); // 5 minutos
        try {
            error_log("updateGestionedPaymentCostDeliveryExternal");
            $startTime = microtime(true);

            $data = $request->json()->all();
            $noveltyState = $data['payment_state'];
            $idsExternal = $data['ids'];

            $nowFormatted = Carbon::now()->format('Y-m-d H:i:s');
            $id_user = $data['id_user'];

            $editedPayments = [];
            $idsNotProcessed = [];

            foreach ($idsExternal as $idExternal) {
                $order = PedidosShopify::with('pedidoCarrierSimple')
                    ->whereHas('pedidoCarrierSimple', function ($query) use ($idExternal) {
                        $query->where('external_id', $idExternal);
                    })
                    ->first();

                if ($order) {
                    $edited_payment = $order["gestioned_payment_cost_delivery"] != null
                        ? json_decode($order["gestioned_payment_cost_delivery"], true)
                        : [];

                    DB::beginTransaction();

                    try {
                        if ($noveltyState == 1) {
                            $edited_payment["state"] = 1;
                            $edited_payment["id_user"] = $id_user;
                            $edited_payment["m_t_g"] = $nowFormatted;

                            //update payment_status
                            $transactions = TransaccionGlobal::query()
                                ->where('id_order', $order['id'])
                                ->where('status', 'ENTREGADO')
                                ->whereNot('origin', 'Referenciado')
                                ->get();

                            foreach ($transactions as $transaction) {
                                $transaction = TransaccionGlobal::where('id', $transaction['id'])
                                    ->first();
                                // error_log($transaction);
                                if ($transaction != null) {
                                    $transaction->payment_status = "ACREDITADO";
                                    $transaction->save();
                                }
                            }

                            //update payment_status ProviderTransaction
                            $transactionsProvider = ProviderTransaction::query()
                                ->where('origin_id', $order['id'])
                                ->get();

                            foreach ($transactionsProvider as $transactionProv) {
                                $transactionProvFound = ProviderTransaction::where('id', $transactionProv['id'])
                                    ->first();
                                // error_log($transaction);
                                if ($transactionProvFound != null) {
                                    $transactionProvFound->payment_status = "ACREDITADO";
                                    $transactionProvFound->save();
                                }
                            }
                        }

                        $order["gestioned_payment_cost_delivery"] = json_encode($edited_payment);
                        $order->save();

                        DB::commit();

                        $editedPayments[] = $edited_payment;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $idsNotProcessed[] = $idExternal;
                        throw $e;
                    }
                } else {
                    $idsNotProcessed[] = $idExternal;
                    error_log("No se encontró pedido para external_id: $idExternal");
                }
            }

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            error_log("updateGestionedPaymentCostDeliveryExternal_tiempo_total" . $executionTime . " seg ");

            if (!empty($idsNotProcessed)) {
                error_log("updateGestionedPaymentCostDeliveryExternal: idsNotProcessed");
                return response()->json([
                    "response" => "Some IDs could not be processed",
                    "idsNotProcessed" => $idsNotProcessed,
                    "edited_novelty" => $editedPayments
                ], 422);
            }

            return response()->json([
                "response" => "Payment updated successfully",
                "edited_novelty" => $editedPayments
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            error_log("updateGestionedPaymentCostDeliveryExternal_error $th");
            return response()->json([
                "response" => "Failed to update Payment",
                "error" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    function normalizeText($text)
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $text)));
    }
}
