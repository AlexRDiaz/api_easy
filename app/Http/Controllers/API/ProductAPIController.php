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


    // public function getProducts(Request $request)
    // {
    //     //all for catalog
    //     $data = $request->json()->all();

    //     $pageSize = $data['page_size'];
    //     $pageNumber = $data['page_number'];
    //     $searchTerm = $data['search'];

    //     $populate = $data['populate'];
    //     if ($searchTerm != "") {
    //         $filteFields = $data['or'];
    //     } else {
    //         $filteFields = [];
    //     }

    //     $andMap = $data['and'];

    //     $products = Product::with($populate)
    //         ->where(function ($products) use ($searchTerm, $filteFields) {
    //             foreach ($filteFields as $field) {
    //                 if (strpos($field, '.') !== false) {
    //                     $relacion = substr($field, 0, strpos($field, '.'));
    //                     $propiedad = substr($field, strpos($field, '.') + 1);
    //                     $this->recursiveWhereHas($products, $relacion, $propiedad, $searchTerm);
    //                 } else {
    //                     $products->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
    //                 }
    //             }
    //         })
    //         ->where((function ($products) use ($andMap) {
    //             foreach ($andMap as $condition) {
    //                 foreach ($condition as $key => $valor) {
    //                     if (strpos($key, '.') !== false) {
    //                         $relacion = substr($key, 0, strpos($key, '.'));
    //                         $propiedad = substr($key, strpos($key, '.') + 1);
    //                         $this->recursiveWhereHas($products, $relacion, $propiedad, $valor);
    //                     } else {
    //                         $products->where($key, '=', $valor);
    //                     }
    //                 }
    //             }
    //         }))
    //         ->whereHas('warehouse', function ($warehouse) {
    //             $warehouse->where('active', 1)
    //             ->where('approved', 1);
    //         })
    //         ->where('active', 1)//los No delete
    //         ->where('approved', 1);
    //     // ! sort
    //     $orderByText = null;
    //     $orderByDate = null;
    //     $sort = $data['sort'];
    //     $sortParts = explode(':', $sort);

    //     $pt1 = $sortParts[0];

    //     $type = (stripos($pt1, 'fecha') !== false || stripos($pt1, 'marca') !== false) ? 'date' : 'text';

    //     $dataSort = [
    //         [
    //             'field' => $sortParts[0],
    //             'type' => $type,
    //             'direction' => $sortParts[1],
    //         ],
    //     ];

    //     foreach ($dataSort as $value) {
    //         $field = $value['field'];
    //         $direction = $value['direction'];
    //         $type = $value['type'];

    //         if ($type === "text") {
    //             $orderByText = [$field => $direction];
    //         } else {
    //             $orderByDate = [$field => $direction];
    //         }
    //     }

    //     if ($orderByText !== null) {
    //         $products->orderBy(key($orderByText), reset($orderByText));
    //     } else {
    //         $products->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
    //     }
    //     // ! **************************************************
    //     $products = $products->paginate($pageSize, ['*'], 'page', $pageNumber);
    //     return response()->json($products);
    // }


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

            $warehouse = Warehouse::find($warehouse_id); // Encuentra al usuario por su ID

            $newProduct = new Product();
            $newProduct->product_name = $product_name;
            $newProduct->stock = $stock;
            $newProduct->price = $price;
            $newProduct->url_img = $url_img;
            $newProduct->isvariable = $isvariable;
            $newProduct->features = $features;
            $newProduct->warehouse_id = $warehouse_id;
            // $newProduct->approved = 2;//Pendiente


            $newProduct->save();


            if ($newProduct) {
                $to = 'easyecommercetest@gmail.com';
                $subject = 'Aprobación de un nuevo producto';
                $message = 'La bodega "' . $warehouse->branch_name . '" ha agregado un nuevo producto "' . $newProduct->product_name . '" con el id "' . $newProduct->product_id . '" para la respectiva aprobación.';
                Mail::raw($message, function ($mail) use ($to, $subject) {
                    $mail->to($to)->subject($subject);
                });

                return response()->json($newProduct, 200);
            } else {
                return response()->json(['message' => 'Error al crear producto'], 404);
            }
        } catch (\Exception $e) {
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

    public function updateProductVariantStockInternal($quantity, $skuProduct, $type, $idComercial)
    {
        $result = $this->splitSku($skuProduct);

        $onlySku = $result['sku'];
        $productIdFromSKU = $result['id'];


        $product = Product::find($productIdFromSKU);

        if ($product === null) {
            return null; // Retorna null si no se encuentra el producto
        }

        if ($product) {

            $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);

            $currentDateTime = date('Y-m-d H:i:s');

            $createHistory = new StockHistory();
            $createHistory->product_id =  $productIdFromSKU;
            $createHistory->variant_sku = $onlySku;
            $createHistory->type = $type;
            $createHistory->date = $currentDateTime;
            $createHistory->units = $quantity;
            $createHistory->last_stock = $product->stock - $quantity;
            $createHistory->current_stock = $product->stock;
            $createHistory->description = "Incremento de stock General Pedido EN BODEGA";

            $createHistory->save();
        } else {
            return response()->json(['message' => 'Product not found'], 404);
        }
    }
    // ! ↓ FUNCIONAL ↓ 
    public function updateProductVariantStock(Request $request)
    {
        $reserveController = new ReserveAPIController();
        $data = $request->json()->all();

        $quantity = $data['quantity'];
        $skuProduct = $data['sku_product']; // Esto tendrá un valor como "test2"
        $type = $data['type'];
        $idComercial = $data['id_comercial'];

        $result = $this->splitSku($skuProduct);

        $onlySku = $result['sku'];
        $productIdFromSKU = $result['id'];

        $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);

        // Decode the JSON response to get the data
        $searchResult = json_decode($response->getContent());

        if ($searchResult && $searchResult->response) {
            $reserve = $searchResult->reserve;

            if ($type == 0 && $quantity > $reserve->stock) {
                // No se puede restar más de lo que hay en stock
                return response()->json(['message' => 'No Dispone de Stock en la Reserva'], 400);
            }

            // Actualizar el stock
            $reserve->stock += ($type == 1) ? $quantity : -$quantity;

            // Assuming you have a 'Reserve' model that you want to save after updating
            $reserveModel = Reserve::find($reserve->id);
            if ($reserveModel) {
                $reserveModel->stock = $reserve->stock;
                $reserveModel->save();
            }

            // Devolver la respuesta
            return response()->json(['message' => 'Stock actualizado con éxito', 'reserve' => $reserveModel]);
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
                $createHistory->product_id =  $productIdFromSKU;
                $createHistory->variant_sku = $onlySku;
                $createHistory->type = $type;
                $createHistory->date = $currentDateTime;
                $createHistory->units = $quantity;
                $createHistory->last_stock = $product->stock + $quantity;
                $createHistory->current_stock = $product->stock;
                $createHistory->description = "Reducción de stock General Pedido ENVIADO";

                $createHistory->save();
            } else {
                return response()->json(['message' => 'Product not found'], 404);
            }
        }
    }
}
