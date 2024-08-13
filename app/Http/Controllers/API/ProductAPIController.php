<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Reserve;
use App\Models\StockHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\ReserveAPIController;
use App\Models\ProductWarehouseLink;
use App\Models\UpUser;
use App\Models\UpUsersWarehouseLink;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Mail;

class ProductAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $products = Product::with('warehouse')->get();
        return response()->json($products);
    }


    public function getProducts(Request $request)
    {
        //all for catalog
        $data = $request->json()->all();

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];

        $populate = $data['populate'];
        $outFilters = $data['out_filters'] ?? [];

        // Asumiendo que las categorías vienen en un filtro llamado 'categories' dentro de 'out_filters'
        $categoryFilters = [];
        foreach ($outFilters as $filter) {
            if (isset($filter['input_categories'])) {
                $categoryFilters = $filter['input_categories'];
                break;
            }
        }
        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        $andMap = $data['and'];
        /*
        error_log("*relacion:" . $relacion);
        error_log("propiedad: " . $propiedad);
        error_log("valor: " . $valor);
        */

        $products = Product::with($populate)
            ->where(function ($products) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($products, $relacion, $propiedad, $searchTerm);
                    } else {
                        $products->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($products) use ($andMap) {
                foreach ($andMap as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($products, $relacion, $propiedad, $valor);
                        } else {
                            $products->where($key, '=', $valor);
                        }
                    }
                }
            }))
            ->whereHas('warehouse', function ($warehouse) {
                $warehouse->where('active', 1)
                    ->where('approved', 1);
            })
            ->where('active', 1) //los No delete
            ->where('approved', 1)
            ->when(isset($data['out_filters']), function ($query) use ($data) {
                foreach ($data['out_filters'] as $filter) {
                    foreach ($filter as $key => $value) {
                        if ($key === 'price_range') {
                            $priceRange = explode('-', $value);
                            $minPrice = isset($priceRange[0]) && $priceRange[0] !== '' ? floatval($priceRange[0]) : null;
                            $maxPrice = isset($priceRange[1]) && $priceRange[1] !== '' ? floatval($priceRange[1]) : null;

                            if (!is_null($minPrice) && !is_null($maxPrice)) {
                                $query->whereBetween('price', [$minPrice, $maxPrice]);
                            } elseif (!is_null($minPrice)) {
                                $query->where('price', '>=', $minPrice);
                            } elseif (!is_null($maxPrice)) {
                                $query->where('price', '<=', $maxPrice);
                            }
                        }
                    }
                }
            })
            ->when(count($categoryFilters) > 0, function ($query) use ($categoryFilters) {
                $query->where(function ($query) use ($categoryFilters) {
                    foreach ($categoryFilters as $category) {
                        // $query->orWhereRaw("JSON_CONTAINS(JSON_EXTRACT(features, '$.categories'), '\"$category\"')");
                        $query->orWhereRaw("JSON_CONTAINS(JSON_EXTRACT(features, '$.categories[*].name'), '\"$category\"')");
                    }
                });
            });

        /*
        $idMaster = 2;
        $favoriteValue = 1;

        $products->whereHas('productseller', function ($query) use ($idMaster, $favoriteValue) {
            $query->where('id_master', $idMaster)
                ->where('favorite', $favoriteValue);
        });

        */

        // $filterPS = [
        //     ["id_master" => 2],
        //     ["key" => ["favorite", "onsale"]],
        // ];
        $filterPS = $data['filterps'];

        if (!empty($filterPS)) {
            $idMasterValue = 0;

            foreach ($filterPS as $condition) {
                if (isset($condition['id_master'])) {
                    $idMasterValue = $condition['id_master'];
                    error_log("Valor de 'id_master': " . $idMasterValue);
                }

                if (isset($condition['key']) && is_array($condition['key'])) {
                    $keyValues = $condition['key'];

                    // Verificar si ambas claves están presentes en 'key'
                    if (in_array('favorite', $keyValues) && in_array('onsale', $keyValues)) {
                        $products->whereHas('productseller', function ($query) use ($idMasterValue) {
                            $query->where('id_master', $idMasterValue)
                                ->where('favorite', 1)
                                ->where('onsale', 1);
                        });
                        error_log("Ambas claves 'favorite' y 'onsale' están presentes en 'key'.");
                    } else {
                        // Verificar individualmente cada clave en 'key'
                        foreach ($keyValues as $keyValue) {
                            if ($keyValue == 'favorite') {
                                $products->whereHas('productseller', function ($query) use ($idMasterValue) {
                                    $query->where('id_master', $idMasterValue)
                                        ->where('favorite', 1);
                                });
                                error_log("La clave 'favorite' está presente en 'key'.");
                            } elseif ($keyValue == 'onsale') {
                                $products->whereHas('productseller', function ($query) use ($idMasterValue) {
                                    $query->where('id_master', $idMasterValue)
                                        ->where('onsale', 1);
                                });
                                error_log("La clave 'onsale' está presente en 'key'.");
                            }
                        }
                    }
                }
            }
        }



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
            $products->orderBy(key($orderByText), reset($orderByText));
        } else {
            $products->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! ******
        $products = $products->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($products);
    }

    public function getProductsNew(Request $request)
    {
        try {
            error_log("getProductsNew");

            //all for catalog
            $data = $request->json()->all();

            $pageSize = $data['page_size'];
            $pageNumber = $data['page_number'];
            $searchTerm = $data['search'];

            $populate = $data['populate'];
            $outFilters = $data['out_filters'] ?? [];

            // Asumiendo que las categorías vienen en un filtro llamado 'categories' dentro de 'out_filters'
            $categoryFilters = [];
            foreach ($outFilters as $filter) {
                if (isset($filter['input_categories'])) {
                    $categoryFilters = $filter['input_categories'];
                    break;
                }
            }
            if ($searchTerm != "") {
                $filteFields = $data['or'];
            } else {
                $filteFields = [];
            }

            $andMap = $data['and'];

            $products = Product::with($populate)
                ->where(function ($products) use ($searchTerm, $filteFields) {
                    foreach ($filteFields as $field) {
                        if (strpos($field, '.') !== false) {
                            $segments = explode('.', $field);
                            $lastSegment = array_pop($segments);
                            $relation = implode('.', $segments);

                            $products->orWhereHas($relation, function ($query) use ($lastSegment, $searchTerm) {
                                $query->where($lastSegment, 'LIKE', '%' . $searchTerm . '%');
                            });
                        } else {
                            $products->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    }
                })
                ->where(function ($products) use ($andMap) {
                    foreach ($andMap as $condition) {
                        foreach ($condition as $key => $valor) {
                            $parts = explode("/", $key);
                            $type = $parts[0];
                            $filter = $parts[1];
                            if (strpos($filter, '.') !== false) {
                                $relacion = substr($filter, 0, strpos($filter, '.'));
                                $propiedad = substr($filter, strpos($filter, '.') + 1);
                                $this->recursiveWhereHas($products, $relacion, $propiedad, $valor);
                            } else {
                                if ($type == "equals") {
                                    $products->where($filter, '=', $valor);
                                } else {
                                    $products->where($filter, 'LIKE', '%' . $valor . '%');
                                }
                            }
                        }
                    }
                })
                ->whereHas('warehouses.provider', function ($query) {
                    $query->where('active', 1)->where('approved', 1)->take(1); //primera bodega, primer proveedor
                })
                ->where('active', 1)
                ->where('approved', 1)
                ->when(isset($data['out_filters']), function ($query) use ($data) {
                    foreach ($data['out_filters'] as $filter) {
                        foreach ($filter as $key => $value) {
                            if ($key === 'price_range') {
                                $priceRange = explode('-', $value);
                                $minPrice = isset($priceRange[0]) && $priceRange[0] !== '' ? floatval($priceRange[0]) : null;
                                $maxPrice = isset($priceRange[1]) && $priceRange[1] !== '' ? floatval($priceRange[1]) : null;

                                if (!is_null($minPrice) && !is_null($maxPrice)) {
                                    $query->whereBetween('price', [$minPrice, $maxPrice]);
                                } elseif (!is_null($minPrice)) {
                                    $query->where('price', '>=', $minPrice);
                                } elseif (!is_null($maxPrice)) {
                                    $query->where('price', '<=', $maxPrice);
                                }
                            }
                        }
                    }
                })
                ->when(count($categoryFilters) > 0, function ($query) use ($categoryFilters) {
                    $query->where(function ($query) use ($categoryFilters) {
                        foreach ($categoryFilters as $category) {
                            // $query->orWhereRaw("JSON_CONTAINS(JSON_EXTRACT(features, '$.categories'), '\"$category\"')");
                            $query->orWhereRaw("JSON_CONTAINS(JSON_EXTRACT(features, '$.categories[*].name'), '\"$category\"')");
                        }
                    });
                });


            $filterPS = $data['filterps'];

            if (!empty($filterPS)) {
                $idMasterValue = 0;

                foreach ($filterPS as $condition) {
                    if (isset($condition['id_master'])) {
                        $idMasterValue = $condition['id_master'];
                        error_log("Valor de 'id_master': " . $idMasterValue);
                    }

                    if (isset($condition['key']) && is_array($condition['key'])) {
                        $keyValues = $condition['key'];

                        // Verificar si ambas claves están presentes en 'key'
                        if (in_array('favorite', $keyValues) && in_array('onsale', $keyValues)) {
                            $products->whereHas('productseller', function ($query) use ($idMasterValue) {
                                $query->where('id_master', $idMasterValue)
                                    ->where('favorite', 1)
                                    ->where('onsale', 1);
                            });
                            error_log("Ambas claves 'favorite' y 'onsale' están presentes en 'key'.");
                        } else {
                            // Verificar individualmente cada clave en 'key'
                            foreach ($keyValues as $keyValue) {
                                if ($keyValue == 'favorite') {
                                    $products->whereHas('productseller', function ($query) use ($idMasterValue) {
                                        $query->where('id_master', $idMasterValue)
                                            ->where('favorite', 1);
                                    });
                                    error_log("La clave 'favorite' está presente en 'key'.");
                                } elseif ($keyValue == 'onsale') {
                                    $products->whereHas('productseller', function ($query) use ($idMasterValue) {
                                        $query->where('id_master', $idMasterValue)
                                            ->where('onsale', 1);
                                    });
                                    error_log("La clave 'onsale' está presente en 'key'.");
                                }
                            }
                        }
                    }
                }
            }



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
                $products->orderBy(key($orderByText), reset($orderByText));
            } else {
                $products->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
            }
            // ! ******
            $products = $products->paginate($pageSize, ['*'], 'page', $pageNumber);

            return response()->json($products);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function recursiveWhereHasSameRegister($query, $relation, $property, $searchTerm)
    {
        if ($searchTerm == "null") {
            $searchTerm = null;
        }

        if (strpos($property, '.') !== false) {
            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            // Llamada a whereHas para verificar la condición $nestedProperty
            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm) {
                $q->whereHas($nestedRelation, function ($qq) use ($nestedProperty, $searchTerm) {
                    $qq->where($nestedProperty, '=', $searchTerm);
                });
            });

            // Llamada a whereHas para manejar cualquier otra lógica de búsqueda recursiva necesaria
            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm) {
                $this->recursiveWhereHasSameRegister($q, $nestedRelation, $nestedProperty, $searchTerm);
            });
        }
    }


    public function getProductsByProvider(Request $request, string $id)
    {
        //
        $data = $request->json()->all();

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];
        $id = $id;
        $populate = $data['populate'];
        if ($searchTerm != "") {
            $filteFields = $data['or'];
        } else {
            $filteFields = [];
        }

        $andMap = $data['and'];

        $products = Product::with($populate)
            ->where(function ($products) use ($searchTerm, $filteFields) {
                foreach ($filteFields as $field) {
                    if (strpos($field, '.') !== false) {
                        $relacion = substr($field, 0, strpos($field, '.'));
                        $propiedad = substr($field, strpos($field, '.') + 1);
                        $this->recursiveWhereHas($products, $relacion, $propiedad, $searchTerm);
                    } else {
                        $products->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            })
            ->where((function ($products) use ($andMap) {
                foreach ($andMap as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($products, $relacion, $propiedad, $valor);
                        } else {
                            $products->where($key, '=', $valor);
                        }
                    }
                }
            }))
            ->whereHas('warehouse.provider', function ($provider) use ($id) {
                $provider->where('id', '=', $id);
            })
            // ->whereHas('warehouse', function ($warehouse) {
            //     $warehouse->where('active', 1);
            // })
            ->where('active', 1); //los No delete

        $to = $data['to'];
        if ($to == "approve") {
            $products->whereHas('warehouse', function ($warehouse) {
                $warehouse->where('approved', 1);
            });
        }

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
            $products->orderBy(key($orderByText), reset($orderByText));
        } else {
            $products->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! **************************************************
        $products = $products->paginate($pageSize, ['*'], 'page', $pageNumber);
        return response()->json($products);
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
        error_log("storeProduct");
        try {
            $data = $request->json()->all();
            // return response()->json($data, 200);

            $product_name = $data['product_name'];
            $stock = $data['stock'];
            $price = $data['price'];
            $url_img = $data['url_img'];
            $isvariable = $data['isvariable'];
            // $features = json_encode($data['features']);
            $features = $data['features'];
            $warehouse_id = $data['warehouse_id'];
            $seller_owned = $data['seller_owned'];
            $updated_by = $data['generatedBy'];

            $warehouse = Warehouse::find($warehouse_id); // Encuentra al usuario por su ID

            $newProduct = new Product();
            $newProduct->product_name = $product_name;
            $newProduct->stock = $stock;
            $newProduct->price = $price;
            $newProduct->url_img = $url_img;
            $newProduct->isvariable = $isvariable;
            $newProduct->features = $features;
            $newProduct->warehouse_id = $warehouse_id;
            $newProduct->seller_owned = $seller_owned;
            // $newProduct->approved = 2;//Pendiente
            $newProduct->updated_by = $updated_by;
            $newProduct->save();

            $currentDateTime = date('Y-m-d H:i:s');

            $dataFeatures = json_decode($features, true);
            $skuGen = $dataFeatures['sku'];

            if ($isvariable == 0) {
                // error_log("no isvariable for StockHistory");
                $createHistory = new StockHistory();
                $createHistory->product_id =  $newProduct->product_id;
                $createHistory->variant_sku = $skuGen;
                $createHistory->type = 1; //ingreso
                $createHistory->date = $currentDateTime;
                $createHistory->units =  $stock;
                $createHistory->last_stock = 0;
                $createHistory->current_stock = $stock;
                $createHistory->description = "Registro de Nuevo Producto";
                $createHistory->updated_by = $updated_by;
                $createHistory->save();

                error_log("created History for type simple");
            } else {
                //
                // error_log("isvariable for StockHistory");
                $variants = $dataFeatures['variants'];

                foreach ($variants as $variant) {
                    // $id = $variant['id'];
                    $sku = $variant['sku'];
                    // $size = $variant['size'];
                    $inventory_quantity = $variant['inventory_quantity'];
                    $price = $variant['price'];

                    $createHistory = new StockHistory();
                    $createHistory->product_id =  $newProduct->product_id;
                    $createHistory->variant_sku = $sku;
                    $createHistory->type = 1; //ingreso
                    $createHistory->date = $currentDateTime;
                    $createHistory->units =  $inventory_quantity;
                    $createHistory->last_stock = 0;
                    $createHistory->current_stock = $inventory_quantity;
                    $createHistory->description = "Registro de Nuevo Producto";
                    $createHistory->updated_by = $updated_by;
                    $createHistory->save();
                }
                error_log("created History for each variant");
            }

            //create product_warehouse link
            $providerWarehouse = new ProductWarehouseLink();
            $providerWarehouse->id_product = $newProduct->product_id;
            $providerWarehouse->id_warehouse = $warehouse_id;
            $providerWarehouse->updated_by = $updated_by;
            $providerWarehouse->save();


            if ($newProduct) {
                try {
                    $to = 'easyecommercetest@gmail.com';
                    $subject = 'Aprobación de un nuevo producto';
                    $message = 'La bodega "' . $warehouse->branch_name . '" ha agregado un nuevo producto "' . $newProduct->product_name . '" con el id "' . $newProduct->product_id . '" para la respectiva aprobación.';
                    Mail::raw($message, function ($mail) use ($to, $subject) {
                        $mail->to($to)->subject($subject);
                    });
                } catch (\Exception $e) {
                    error_log("Error al enviar email con el producto $newProduct->product_id: $e");
                }

                return response()->json($newProduct, 200);
            } else {
                return response()->json(['message' => 'Error al crear producto'], 404);
            }
        } catch (\Exception $e) {
            error_log("Error storeProduct: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        // $product = Product::with('warehouse')->findOrFail($id);
        // return response()->json($product);
        $data = $request->json()->all();
        $populate = $data['populate'];
        $product = Product::with($populate)
            ->where('product_id', $id)
            ->first();
        if (!$product) {
            return response()->json(['message' => 'No se ha encontrado un producto con el ID especificado'], 404);
        }
        return response()->json($product);
    }

    public function getBySubProvider(Request $request)
    {
        //
        error_log("getBySubProvider");
        try {
            //code...

            $data = $request->json()->all();

            // $pageSize = $data['page_size'];
            $pageSize = $data['page_size'];

            $pageNumber = $data['page_number'];
            $searchTerm = $data['search'];
            $populate = $data['populate'];
            $orConditions = $data['multifilter'];

            if ($searchTerm != "") {
                $filteFields = $data['or'];
            } else {
                $filteFields = [];
            }

            $andMap = $data['and'];
            $not = $data['not'];
            $paginate = isset($data['paginate']) ? $data['paginate'] : true;


            $products = Product::with($populate)
                ->where(function ($products) use ($searchTerm, $filteFields) {
                    foreach ($filteFields as $field) {
                        if (strpos($field, '.') !== false) {
                            $segments = explode('.', $field);
                            $lastSegment = array_pop($segments);
                            $relation = implode('.', $segments);

                            $products->orWhereHas($relation, function ($query) use ($lastSegment, $searchTerm) {
                                $query->where($lastSegment, 'LIKE', '%' . $searchTerm . '%');
                            });
                        } else {
                            $products->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                        }
                    }
                })
                ->orWhere(function ($products) use ($orConditions) {
                    // condiciones multifilter
                    foreach ($orConditions as $condition) {
                        $products->orWhere(function ($subquery) use ($condition) {
                            foreach ($condition as $field => $value) {
                                if (strpos($field, '.') !== false) {
                                    $segments = explode('.', $field);
                                    $lastSegment = array_pop($segments);
                                    $relation = implode('.', $segments);
                                    $subquery->orWhereHas($relation, function ($query) use ($lastSegment, $value) {
                                        $query->where($lastSegment, $value);
                                    });
                                } else {
                                    $subquery->orWhere($field, $value);
                                }
                            }
                        });
                    }
                })
                ->where((function ($products) use ($andMap) {
                    foreach ($andMap as $condition) {
                        foreach ($condition as $key => $valor) {
                            $parts = explode("/", $key);
                            $type = $parts[0];
                            $filter = $parts[1];
                            if ($valor === null) {
                                $products->whereNull($filter);
                            } else {
                                if (strpos($filter, '.') !== false) {
                                    $relacion = substr($filter, 0, strpos($filter, '.'));
                                    $propiedad = substr($filter, strpos($filter, '.') + 1);
                                    $this->recursiveWhereHas($products, $relacion, $propiedad, $valor);
                                } else {
                                    if ($type == "equals") {
                                        $products->where($filter, '=', $valor);
                                    } else {
                                        $products->where($filter, 'LIKE', '%' . $valor . '%');
                                    }
                                }
                            }
                        }
                    }
                }))
                ->where((function ($databackend) use ($not) {
                    foreach ($not as $condition) {
                        foreach ($condition as $key => $valor) {
                            if ($valor === '') {
                                $databackend->whereRaw("$key <> ''");
                            } else {
                                if ($valor === null) {
                                    $databackend->whereNotNull($key);
                                } else {
                                    if (strpos($key, '.') !== false) {
                                        $relacion = substr($key, 0, strpos($key, '.'));
                                        $propiedad = substr($key, strpos($key, '.') + 1);
                                        $this->recursiveWhereHas($databackend, $relacion, $propiedad, $valor);
                                    } else {
                                        // $databackend->where($key, '!=', $valor);
                                        $databackend->whereRaw("$key <> ''");
                                    }
                                }
                            }
                        }
                    }
                }))
                ->whereHas('warehouses.provider', function ($query) {
                    $query->where('active', 1)->where('approved', 1)->take(1); //primera bodega, primer proveedor
                })
                ->where('active', 1);
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
                $products->orderBy(key($orderByText), reset($orderByText));
            } else {
                $products->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
            }

            if ($pageSize == null) {
                error_log("NO paginate");
                $products = $products->get();
            } else {
                error_log("is paginate");
                $products = $products->paginate($pageSize, ['*'], 'page', $pageNumber);
            }




            /*
        $products = UpUser::with('warehouses')->where(function ($coverages) use ($andMap) {
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
        })
        ->get();
        */
            return response()->json($products);
        } catch (\Exception $e) {
            error_log("error: $e");
            return response()->json([
                'error' => "There was an error processing your request. " . $e->getMessage()
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
        $product = Product::find($id); // Encuentra al usuario por su ID

        if ($product) {
            $product->update($request->all());
            return response()->json(['message' => 'Producto actualizado con éxito', "producto" => $product], 200);
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
        Product::where('product_id', $id)
            ->update(['active' => 0]);
    }

    public function updateRequest(Request $request, $id)
    {
        $data = $request->all();

        $provider = Product::findOrFail($id);

        $provider->fill($data);
        $provider->save();

        // Respuesta de éxito
        return response()->json(['message' => 'Registro actualizado con éxito', "res" => $provider], 200);
    }


    public function splitSku($skuProduct)
    {
        // Verificar si el SKU es nulo y asignar un valor predeterminado
        if ($skuProduct == null) {
            $skuProduct = "UNKNOWNPC0";
        }

        // Encontrar la última posición de 'C' en el SKU
        $lastCPosition = strrpos($skuProduct, 'C');

        // Extraer la parte del SKU y el ID del producto del SKU
        $onlySku = substr($skuProduct, 0, $lastCPosition);
        $productIdFromSKU = substr($skuProduct, $lastCPosition + 1);

        // Convertir el ID del producto a entero
        $productIdFromSKU = intval($productIdFromSKU);

        // Devolver un arreglo con el SKU y el ID del producto
        return ['sku' => $onlySku, 'id' => $productIdFromSKU];
    }

    // public function updateProductVariantStockInternal($quantity, $skuProduct, $type, $idComercial)
    // {
    //     $result = $this->splitSku($skuProduct);

    //     $onlySku = $result['sku'];
    //     $productIdFromSKU = $result['id'];


    //     $product = Product::find($productIdFromSKU);

    //     if ($product === null) {
    //         return null; // Retorna null si no se encuentra el producto
    //     }

    //     if ($product) {

    //         $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);

    //         $currentDateTime = date('Y-m-d H:i:s');

    //         $createHistory = new StockHistory();
    //         $createHistory->product_id =  $productIdFromSKU;
    //         $createHistory->variant_sku = $onlySku;
    //         $createHistory->type = $type;
    //         $createHistory->date = $currentDateTime;
    //         $createHistory->units = $quantity;
    //         $createHistory->last_stock = $product->stock - $quantity;
    //         $createHistory->current_stock = $product->stock;
    //         $createHistory->description = "Incremento de stock General Pedido EN BODEGA";

    //         $createHistory->save();
    //     } else {
    //         return response()->json(['message' => 'Product not found'], 404);
    //     }
    // }
    public function updateProductVariantStockInternal($variant_details, $type, $idComercial, $idOrder, $codigo_order)
    {
        error_log("updateProductVariantStock INTERNAL");
        $variants = json_decode($variant_details, true);
        $responses = [];

        $historyOrder = StockHistory::where('pedidos_shopify_id', $idOrder)
            ->latest('id')
            ->first();
        $allow = true;

        // error_log("$historyOrder: $historyOrder");
        if ($historyOrder) {
            // El registro más reciente existe
            if ($historyOrder->type == 1) {
                $allow = false;
                error_log("Dev $idOrder $historyOrder->type YA existe");
            } else {
                $allow = true;
            }
        }

        if ($allow) {

            foreach ($variants as $variant) {
                $quantity = $variant['quantity'];
                $skuProduct = $variant['sku']; // Ahora el SKU viene dentro de cada variante
                // $type = $data['type'];


                $result = $this->splitSku($skuProduct);

                $onlySku = $result['sku'];
                $productIdFromSKU = $result['id'];


                $product = Product::find($productIdFromSKU);

                if ($product === null) {
                    return null; // Retorna null si no se encuentra el producto
                }

                if ($product) {
                    $currentDateTime = date('Y-m-d H:i:s');

                    if ($product->seller_owned != 0 || $product->seller_owned != null) {
                        error_log("$productIdFromSKU IS seller_owned: $product->seller_owned");

                        $reserveController = new ReserveAPIController();

                        $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);
                        $searchResult = json_decode($response->getContent());

                        if ($searchResult && $searchResult->response) {
                            $reserve = $searchResult->reserve;
                            $previous_stock = $reserve->stock;
                            if ($type == 0 && $quantity > $reserve->stock) {
                                $responses[] = ['message' => 'No Dispone de Stock en la Reserva'];
                                continue;
                            }

                            // Actualizar el stock
                            $reserve->stock += ($type == 1) ? $quantity : -$quantity;

                            // Assuming you have a 'Reserve' model that you want to save after updating
                            $reserveModel = Reserve::find($reserve->id);
                            if ($reserveModel) {
                                $reserveModel->stock = $reserve->stock;
                                $reserveModel->save();
                            }

                            $createHistory = new StockHistory();
                            $createHistory->product_id = $productIdFromSKU;
                            $createHistory->variant_sku = $onlySku;
                            $createHistory->type = $type;
                            $createHistory->date = $currentDateTime;
                            $createHistory->units = $quantity;
                            $createHistory->last_stock_reserve = $previous_stock;
                            $createHistory->current_stock_reserve = $reserve->stock;
                            $createHistory->pedidos_shopify_id = $idOrder;
                            $createHistory->description = "Incremento de stock Reserva Pedido EN BODEGA $codigo_order";
                            $createHistory->save();
                        }
                    } else {
                        $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);

                        $createHistory = new StockHistory();
                        $createHistory->product_id = $productIdFromSKU;
                        $createHistory->variant_sku = $onlySku;
                        $createHistory->type = $type;
                        $createHistory->date = $currentDateTime;
                        $createHistory->units = $quantity;
                        $createHistory->last_stock = $product->stock - $quantity;
                        $createHistory->current_stock = $product->stock;
                        $createHistory->pedidos_shopify_id = $idOrder;
                        $createHistory->description = "Incremento de stock General Pedido EN BODEGA $codigo_order";

                        $createHistory->save();
                    }
                } else {
                    // $responses[] =['message' => 'Product not found'];
                    // continue;
                }
            }
        }
        // return response()->json($responses);
    }

    // ! ↓ FUNCIONAL ↓
    public function updateProductVariantStock(Request $request)
    {
        error_log("updateProductVariantStock");
        $reserveController = new ReserveAPIController();
        $data = $request->json()->all();
        $variants = json_decode($data['variant_detail'], true);

        // $quantity = $data['quantity'];
        // $skuProduct = $data['sku_product']; // Esto tendrá un valor como "test2"
        // $type = $data['type'];
        $idComercial = $data['id_comercial'];
        $idOrder = $data['id'];
        $code = $data['code'];
        error_log("editStock-$idOrder");

        $responses = [];

        $allow = true;

        $historyOrder = StockHistory::where('pedidos_shopify_id', $idOrder)
            ->latest('id')
            ->first();

        // error_log("$historyOrder: $historyOrder");
        if ($historyOrder) {
            // El registro más reciente existe
            if ($historyOrder->type == 0) {
                $allow = false;
                error_log("$idOrder $historyOrder->type YA existe");
            } else {
                $allow = true;
            }
        }

        if ($allow) {
            if ($variants != null) {
                foreach ($variants as $variant) {
                    $quantity = $variant['quantity'];
                    $skuProduct = $variant['sku']; // Ahora el SKU viene dentro de cada variante
                    $type = $data['type'];

                    $result = $this->splitSku($skuProduct);

                    $onlySku = $result['sku'];
                    $productIdFromSKU = $result['id'];

                    $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);

                    // Decode the JSON response to get the data
                    $searchResult = json_decode($response->getContent());

                    if ($searchResult && $searchResult->response) {
                        $reserve = $searchResult->reserve;
                        $previous_stock = $reserve->stock;

                        if ($type == 0 && $quantity > $reserve->stock) {
                            // No se puede restar más de lo que hay en stock
                            $responses[] = ['message' => 'No Dispone de Stock en la Reserva'];
                            continue;
                        }

                        // Actualizar el stock
                        $reserve->stock += ($type == 1) ? $quantity : -$quantity;

                        // Assuming you have a 'Reserve' model that you want to save after updating
                        $reserveModel = Reserve::find($reserve->id);

                        if ($reserveModel) {
                            $reserveModel->stock = $reserve->stock;
                            $reserveModel->save();
                        }

                        $currentDateTime = date('Y-m-d H:i:s');

                        $createHistory = new StockHistory();
                        $createHistory->product_id = $productIdFromSKU;
                        $createHistory->variant_sku = $onlySku;
                        $createHistory->type = $type;
                        $createHistory->date = $currentDateTime;
                        $createHistory->units = $quantity;
                        $createHistory->last_stock_reserve = $previous_stock;
                        $createHistory->current_stock_reserve = $reserve->stock;
                        $createHistory->pedidos_shopify_id = $idOrder;
                        $createHistory->description = "Reducción de stock Reserva Pedido ENVIADO $code";
                        $createHistory->save();
                        // Devolver la respuesta
                        // $responses[] = ['message' => 'Stock actualizado con éxito', 'reserve' => $reserveModel];
                    } else {
                        // Encuentra el producto por su SKU.
                        $product = Product::find($productIdFromSKU);

                        if ($product === null) {
                            return null;
                        }

                        if ($product) {
                            $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);
                            $currentDateTime = date('Y-m-d H:i:s');

                            $createHistory = new StockHistory();
                            $createHistory->product_id = $productIdFromSKU;
                            $createHistory->variant_sku = $onlySku;
                            $createHistory->type = $type;
                            $createHistory->date = $currentDateTime;
                            $createHistory->units = $quantity;
                            $createHistory->last_stock = $product->stock + $quantity;
                            $createHistory->current_stock = $product->stock;
                            $createHistory->pedidos_shopify_id = $idOrder;
                            $createHistory->description = "Reducción de stock General Pedido ENVIADO $code";

                            $createHistory->save();
                        } else {
                            $responses[] = ['message' => 'Product not found'];
                            continue;
                        }
                    }
                }
            }
        }
        error_log("updateed ProductVariantStock");
        return response()->json($responses);
    }

    public function getProductVariantStock(Request $request)
    {
        error_log("getProductVariantStock");
        try {

            $reserveController = new ReserveAPIController();
            $data = $request->json()->all();
            // $variants = json_decode($data['variant_detail'], true);
            $variants = $data['variant_detail'];
            $idComercial = $data['id_comercial'];
            // $idProductMain = $data['id_product'];
            // $idOrder = $data['id_order'];

            $responses = [];

            if ($variants != null) {
                foreach ($variants as $variant) {
                    $quantity = $variant['quantity'];
                    $skuProduct = $variant['sku']; // Ahora el SKU viene dentro de cada variante
                    error_log("$skuProduct");
                    if ($skuProduct != "" || $skuProduct != null) {
                        // $result = $this->splitSku($skuProduct);

                        // $onlySku = $result['sku'];
                        // $productIdFromSKU = $result['id'];

                        // Expresión regular para verificar el formato y que no tenga espacios
                        $onlySku = 0;
                        $productIdFromSKU = 0;

                        $pattern = '/^[a-zA-Z0-9]+C\d+$/';

                        if (preg_match($pattern, $skuProduct) && strpos($skuProduct, ' ') === false) {
                            // Si coincide con el patrón y no contiene espacios, extraer las partes
                            $lastCPosition = strrpos($skuProduct, 'C');

                            $onlySku = substr($skuProduct, 0, $lastCPosition);
                            $productIdFromSKU = substr($skuProduct, $lastCPosition + 1);
                            $productIdFromSKU = intval($productIdFromSKU);

                            // Ejemplo de salida
                            // error_log("onlySku: " . $onlySku . "\n");
                            // error_log("productIdFromSKU: " . $productIdFromSKU . "\n");

                            // error_log("$productIdFromSKU");
                            $product = Product::find($productIdFromSKU);

                            // if ($idProductMain == $productIdFromSKU) {

                            if ($product === null) {
                                error_log("Id Product not found");
                                // return null;
                                //idProduct|isAvaliable|#currentStock|#requested
                                $responses[] = "$productIdFromSKU|3|0|0";
                            }
                            if ($product) {
                                error_log("Id Product found");

                                $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);
                                //skuProduct|isAvaliable|#currentStock|#requested

                                $searchResult = json_decode($response->getContent());

                                if ($searchResult && $searchResult->response) {
                                    error_log("$productIdFromSKU-$onlySku Reserve found");

                                    $reserve = $searchResult->reserve;

                                    if ($quantity > $reserve->stock) {
                                        //skuProduct|0|#currentStock|#requested
                                        // $responses[] = ['message' => 'No Dispone de Stock en la Reserva'];
                                        // error_log("$skuProduct Reserve $quantity > $reserve->stock");
                                        error_log("$skuProduct|0|$reserve->stock|$quantity");
                                        $responses[] = "$skuProduct|0|$reserve->stock|$quantity";
                                        continue;
                                    } else {
                                        //skuProduct|1|#currentStock|#requested
                                        // error_log("$skuProduct Reserve $quantity < $reserve->stock");
                                        error_log("$skuProduct|1|$reserve->stock|$quantity");
                                        $responses[] = "$skuProduct|1|$reserve->stock|$quantity";
                                        continue;
                                    }
                                } else {
                                    error_log("Product stock general");

                                    //stock general
                                    $features = json_decode($product->features, true);

                                    $isvariable = $product->isvariable;
                                    if ($product->stock < $quantity) {
                                        error_log("$skuProduct *insufficient_stock general");
                                        /*
                                        if ($isvariable == 1) {
                                            //skuProduct|2|#currentStock
                                            error_log("$skuProduct *insufficient_stock general");
                                            error_log("$skuProduct|2|$product->stock|$quantity");
                                            $responses[] = "$skuProduct|2|$product->stock|$quantity";
                                        } else 
                                        */
                                        if ($isvariable == 0) {
                                            if ($onlySku != $features['sku']) {
                                                error_log("prod_simple no existe una variante con este sku");
                                                $responses[] = "$skuProduct|3|0|0"; //skuProduct|0|#currentStock|#requested
                                            } else {
                                                error_log("$skuProduct|0|$product->stock|$quantity");
                                                $responses[] = "$skuProduct|0|$product->stock|$quantity";
                                            }
                                        }
                                    } else {
                                        if ($isvariable == 0) {
                                            // error_log("$skuProduct *sufficient_stock general $product->stock");
                                            error_log("$skuProduct|1|$product->stock|$quantity"); //skuProduct|1|#currentStock|#requested
                                            $responses[] = "$skuProduct|1|$product->stock|$quantity";
                                        }
                                    }

                                    if ($isvariable == 1) {
                                        if (isset($features['variants']) && is_array($features['variants'])) {
                                            $found = false;

                                            foreach ($features['variants'] as $key => $variant) {

                                                if ($variant['sku'] == $onlySku) {
                                                    $found = true;
                                                    $inventory_quantity = $variant['inventory_quantity'];

                                                    if ($inventory_quantity < $quantity) {
                                                        error_log("$skuProduct|0|$inventory_quantity|$quantity"); //skuProduct|0|#currentStock
                                                        $responses[] = "$skuProduct|0|$inventory_quantity|$quantity";
                                                    } else {
                                                        error_log("$skuProduct|1|$inventory_quantity|$quantity"); //skuProduct|1|#currentStock
                                                        $responses[] = "$skuProduct|1|$inventory_quantity|$quantity";
                                                    }
                                                    break;
                                                }
                                            }

                                            if (!$found) {
                                                error_log("$skuProduct no existe una variante con este sku");
                                                $responses[] = "$skuProduct|3|0|0";
                                            }
                                        }
                                    }
                                }
                            }
                            // } else {
                            //     error_log("El id no es igual al de ProductMain: $skuProduct");

                            // }
                        } else {
                            $responses[] = "$skuProduct|4|0|0";
                            error_log("El formato de skuProduct no es válido o contiene espacios.\n");
                        }
                    } else {
                        error_log("Es vacio o null skuProductVariant: $skuProduct");
                    }
                }
            }

            return response()->json($responses);
        } catch (\Exception $e) {
            error_log("Error: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getData(Request $request)
    {
        //
        $data = $request->json()->all();
        $ids = $data['ids'];
        $populate = $data['populate'];

        try {

            $products = Product::with($populate)
                ->whereIn('product_id', $ids)
                ->get();

            return response()->json($products);
        } catch (\Exception $e) {
            error_log("Error: $e");
            return response()->json([
                'error' => "There was an error processing your request. " . $e->getMessage()
            ], 500);
        }
    }


    public function getOwners(string $idProv)
    {
        //
        error_log("getOwners");
        try {

            $products = Product::with('owner')
                ->whereHas('warehouse', function ($query) use ($idProv) {
                    $query->where('provider_id', $idProv);
                })
                ->where('active', 1)
                ->get();

            $ownersWithWarehouse = $products->map(function ($product) {
                if ($product->owner) {
                    return [
                        'warehouse_id' => $product->warehouse_id,
                        'owner' => $product->owner
                    ];
                }
            })->filter()->unique('owner.id')->values();

            return response()->json($ownersWithWarehouse);
        } catch (\Exception $e) {
            error_log("error: $e");
            return response()->json([
                'error' => "There was an error processing your request. " . $e->getMessage()
            ], 500);
        }
    }

    public function getWarehouses(string $idProv)
    {
        //
        error_log("getWarehouses");
        try {
            // Obtener los almacenes del proveedor
            $warehouses = Warehouse::where('provider_id', $idProv)
                ->select('warehouse_id', 'branch_name')
                ->get();

            // Obtener los productos del proveedor con sus almacenes
            $products = Product::select('product_id', 'product_name')
                ->whereHas('warehouses', function ($query) use ($idProv) {
                    $query->where('provider_id', $idProv);
                })

                ->has('warehouses', '>', 1)
                ->where('active', 1)
                ->get();

            // Recorrer cada producto y sus almacenes
            foreach ($products as $product) {
                $foundMatch = false;

                // Verificar si al menos un warehouse_id coincide
                foreach ($product->warehouses as $productWarehouse) {
                    foreach ($warehouses as $warehouse) {
                        if ($productWarehouse->warehouse_id == $warehouse->warehouse_id) {
                            $foundMatch = true;
                            break 2;
                        }
                    }
                }

                // Si se encontró una coincidencia, almacenar todos los datos de los almacenes de este producto
                if ($foundMatch) {
                    foreach ($product->warehouses as $productWarehouse) {
                        $matchingWarehouses[] = [
                            'warehouse_id' => $productWarehouse->warehouse_id,
                            'branch_name' => $productWarehouse->branch_name,
                            'up_users' => $productWarehouse->up_users
                        ];
                    }
                }
            }

            $matchingWarehouses = collect($matchingWarehouses)->unique('warehouse_id')->values()->all();

            // return response()->json([
            //     'data' => $products,
            //     'total' => $products->count(),
            // ], 200);


            return response()->json($matchingWarehouses, 200);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByStorage(Request $request)
    {
        error_log("getByStorage");
        try {
            $data = $request->json()->all();

            $populate = $data['populate'];
            $storage = $data['storage_w'];
            $idSellerMaster = $data['idseller'];

            $products = Product::with($populate)
                ->where('active', 1)
                ->where('approved', 1)
                ->where(function ($query) use ($idSellerMaster) {
                    $query->where('seller_owned', $idSellerMaster)
                        ->orWhereNull('seller_owned');
                })
                ->get();

            $filteredProducts = $products->filter(function ($product) use ($storage) {
                $warehouses = $product->warehouses_s;
                if ($warehouses && count($warehouses) > 0) {
                    $lastWarehouse = $warehouses->last();
                    return $lastWarehouse->warehouse_id == $storage;
                }
                return false;
            });
            $filteredProducts = $filteredProducts->values();

            return response()->json($filteredProducts);
        } catch (\Exception $e) {
            error_log("Error in getByStorage: " . $e->getMessage());
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }
}
