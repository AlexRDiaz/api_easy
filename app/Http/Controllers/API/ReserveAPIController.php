<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateReserveAPIRequest;
use App\Http\Requests\API\UpdateReserveAPIRequest;
use App\Models\Reserve;
use App\Repositories\ReserveRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\UpUser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\error;

/**
 * Class ReserveAPIController
 */
class ReserveAPIController extends Controller
{


    /**
     * Display a listing of the Reserves.
     * GET|HEAD /reserves
     */
    public function index(Request $request)
    {
        $reserves = Reserve::all();
        //     $request->except(['skip', 'limit']),
        //     $request->get('skip'),
        //     $request->get('limit')
        // );

        return response()->json(['reserve' => $reserves], Response::HTTP_OK);
    }

    /**
     * Store a newly created Reserve in storage.
     * POST /reserves
     */
    public function store(Request $request)
    {

        DB::beginTransaction();
        error_log("reserves_store");
        try {

            $data = $request->json()->all();

            $product_id = $data['product_id'];
            $sku = $data['sku'];
            $stock = $data['stock'];
            $id_comercial = $data['id_comercial'];
            $warehouse_price = $data['warehouse_price'];
            $updated_by = $data['generatedBy'];

            $reserveController = new ReserveAPIController();

            $response = $reserveController->findByProductAndSku($product_id, $sku, $id_comercial);
            $searchResult = json_decode($response->getContent());

            if ($searchResult && $searchResult->response) {
                error_log("reserva ya existente");
                return response()->json([
                    'error' => 'reserva_ya_existente'
                ], 204);
            }

            $productFound = Product::find($product_id);
            $isvariable = $productFound->isvariable;
            $features = json_decode($productFound->features, true);

            if ($isvariable == 0) {
                if ($productFound->stock < $stock) {
                    error_log("*insufficient_stock");
                    return response()->json([
                        'error' => 'insufficient_stock'
                    ], 505);
                }
            } else {
                error_log("isvariable");
                if (isset($features['variants']) && is_array($features['variants'])) {
                    error_log($sku);

                    foreach ($features['variants'] as $key => $variant) {
                        if ($variant['sku'] == $sku) {
                            if ($variant['inventory_quantity'] < $stock) {
                                error_log("insufficient_stock_variant");
                                return response()->json([
                                    'error' => 'Error insufficient_stock_variant'
                                ], 505);
                            }
                        }
                    }
                }
            }

            $user = UpUser::with('vendor')->find($id_comercial);
            $nombreComercial = $user->vendor->nombre_comercial;

            $createReserve = new Reserve();
            $createReserve->product_id = $product_id;
            $createReserve->sku = $sku;
            $createReserve->stock = $stock;
            $createReserve->id_comercial = $id_comercial;
            $createReserve->warehouse_price = $warehouse_price;
            $createReserve->updated_by = $updated_by;
            $createReserve->save();

            $description = "Reserva por " . $nombreComercial;
            $type = 0;

            $currentDateTime = date('Y-m-d H:i:s');

            $product = Product::find($product_id);
            $last_stock = $product->stock;
            $isVariable = $product->isvariable;
            // error_log("$isVariable");

            $features1 = json_decode($product->features, true);
            $variants = $features1['variants'];
            // error_log(var_export($variants, true));

            $result = $product->changeStockGen($product_id, $sku, $stock, $type);
            $product2 = Product::find($product_id);
            $current_stock = $product2->stock;

            if ($isVariable == 0) {

                $createHistory = new StockHistory();
                $createHistory->product_id = $product_id;
                $createHistory->variant_sku = $sku;
                $createHistory->type = $type;
                $createHistory->date = $currentDateTime;
                $createHistory->units = $stock;
                $createHistory->last_stock = $last_stock;
                $createHistory->current_stock = $current_stock;
                $createHistory->description = $description;
                $createHistory->updated_by = $updated_by;
                $createHistory->save();
                // error_log("created reserve-History for type simple");

            } else {
                //
                $features2 = json_decode($product2->features, true);
                $variants2 = $features2['variants'];
                $variantLastStock = 0;
                $variantCurrentStock = 0;

                foreach ($variants as $variant) {
                    if ($variant['sku'] ===  $sku) {
                        $variantLastStock = $variant['inventory_quantity'];
                        break;
                    }
                }

                foreach ($variants2 as $variant2) {
                    if ($variant2['sku'] ===  $sku) {
                        $variantCurrentStock = $variant2['inventory_quantity'];
                        break;
                    }
                }

                // error_log("variantLastStock: $variantLastStock");
                // error_log("variantCurrentStock: $variantCurrentStock");

                $createHistory = new StockHistory();
                $createHistory->product_id = $product_id;
                $createHistory->variant_sku = $sku;
                $createHistory->type = $type;
                $createHistory->date = $currentDateTime;
                $createHistory->units = $stock;
                $createHistory->last_stock = $variantLastStock;
                $createHistory->current_stock = $variantCurrentStock;
                $createHistory->description = $description;
                $createHistory->updated_by = $updated_by;
                $createHistory->save();
                // error_log("created reserve-History for variant");

            }


            $newHistory = new StockHistory();
            $newHistory->product_id = $product_id;
            $newHistory->variant_sku = $sku;
            $newHistory->type = 1;
            $newHistory->date = $currentDateTime;
            $newHistory->units = $stock;
            $newHistory->last_stock_reserve = 0;
            $newHistory->current_stock_reserve = $stock;
            $newHistory->description = "Ingreso-Reserva " . $nombreComercial;
            $newHistory->updated_by = $updated_by;
            $newHistory->save();

            // return $createReserve;
            DB::commit();
            return response()->json([
                "res" => "Se realizo la reserva y el createHistory exitosamente"
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("error_reserves_store: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified Reserve.
     * GET|HEAD /reserves/{id}
     */
    public function show($id)
    {
        // /** @var Reserve $reserve */
        $reserve = Reserve::find($id);

        // if (empty($reserve)) {
        //     return $this->sendError('Reserve not found');
        // }

        return $this->sendResponse($reserve->toArray(), 'Reserve retrieved successfully');
    }

    /**
     * Update the specified Reserve in storage.
     * PUT/PATCH /reserves/{id}
     */

    public function findByProductAndSku($productId, $sku, $idComercial)
    {


        $reserve = Reserve::where('product_id', $productId)->where("sku", $sku)
            ->where("id_comercial", $idComercial)->first();


        if ($reserve == null) {
            return response()->json(['response' => false]);
        }
        return response()->json(['reserve' => $reserve, "response" => true], Response::HTTP_OK);
    }

    public function update($id, Request $request)
    {
        // $input = $request->all();

        // /** @var Reserve $reserve */
        // $reserve = $this->reserveRepository->find($id);

        // if (empty($reserve)) {
        //     return $this->sendError('Reserve not found');
        // }

        // $reserve = $this->reserveRepository->update($input, $id);

        // return $this->sendResponse($reserve->toArray(), 'Reserve updated successfully');
    }

    /**
     * Remove the specified Reserve from storage.
     * DELETE /reserves/{id}
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        // /** @var Reserve $reserve */
        // $reserve = $this->reserveRepository->find($id);

        // if (empty($reserve)) {
        //     return $this->sendError('Reserve not found');
        // }

        // $reserve->delete();

        // return $this->sendSuccess('Reserve deleted successfully');
    }

    public function editStock(Request $request)
    {
        error_log("editStock");
        DB::beginTransaction();

        try {

            $data = $request->json()->all();

            $product_id = $data['product_id'];
            $skuProduct = $data['sku_product'];
            $units = $data['units'];
            $seller_owned = $data['seller_owned'];
            $description = $data['description'];
            $type = $data['type'];
            $updated_by = $data['generatedBy'];


            $reserveController = new ReserveAPIController();

            $response = $reserveController->findByProductAndSku($product_id, $skuProduct, $seller_owned);
            $searchResult = json_decode($response->getContent());

            if ($searchResult && $searchResult->response) {
                $currentDateTime = date('Y-m-d H:i:s');

                $reserve = $searchResult->reserve;
                $previous_stock = $reserve->stock;
                if ($type == 0 && $units > $reserve->stock) {
                    error("No Dispone de Stock en la Reserva");
                    return response()->json([
                        'error' => 'Error no se encontro la reserva: '
                    ], 500);
                }

                // Actualizar el stock
                $reserve->stock += ($type == 1) ? $units : -$units;

                // Assuming you have a 'Reserve' model that you want to save after updating
                $reserveModel = Reserve::find($reserve->id);
                if ($reserveModel) {
                    $reserveModel->stock = $reserve->stock;
                    $reserveModel->updated_by = $updated_by;
                    $reserveModel->save();
                }

                $createHistory = new StockHistory();
                $createHistory->product_id = $product_id;
                $createHistory->variant_sku = $skuProduct;
                $createHistory->type = $type;
                $createHistory->date = $currentDateTime;
                $createHistory->units = $units;
                $createHistory->last_stock_reserve = $previous_stock;
                $createHistory->current_stock_reserve = $reserve->stock;
                $createHistory->description = "Reserva - " . $description;
                $createHistory->updated_by = $updated_by;
                $createHistory->save();

                $responses[] = ['message' => 'Reserva/Stock actualizado con éxito', 'reserve' => $reserveModel];
            } else {
                error("Error no se encontro la reserva");
                return response()->json([
                    'error' => 'Error no se encontro la reserva: '
                ], 500);
            }
            // return $createReserve;
            DB::commit();
            return response()->json([
                "res" => "Se edito la reserva y el stockHistory exitosamente"
            ]);
        } catch (\Exception $e) {
            error("editStock Error: $e");
            DB::rollback(); // En caso de error, revierte todos los cambios realizados en la transacción
            // Maneja el error aquí si es necesario
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function admin(Request $request)
    {
        error_log("adminReserve");
        DB::beginTransaction();

        try {

            $data = $request->json()->all();

            $product_id = $data['product_id'];
            $skuProduct = $data['sku_product'];
            $units = $data['units'];
            $id_comercial = $data['id_comercial'];
            $description = $data['description'];
            $type = $data['type'];
            $updated_by = $data['generatedBy'];

            $onlySku = $skuProduct;

            $user = UpUser::with('vendor')->find($id_comercial);
            $nombreComercial = $user->vendor->nombre_comercial;

            $productFound = Product::find($product_id);
            $isvariable = $productFound->isvariable;
            $features = json_decode($productFound->features, true);

            if ($type == 1) { //add
                if ($isvariable == 0) {
                    if ($productFound->stock < $units) {
                        error_log("*insufficient_stock");
                        return response()->json([
                            'error' => 'insufficient_stock'
                        ], 505);
                    }
                } else {
                    if (isset($features['variants']) && is_array($features['variants'])) {
                        error_log($onlySku);

                        foreach ($features['variants'] as $key => $variant) {
                            if ($variant['sku'] == $onlySku) {
                                if ($variant['inventory_quantity'] < $units) {
                                    error_log("insufficient_stock_variant");
                                    return response()->json([
                                        'error' => 'Error insufficient_stock_variant'
                                    ], 505);
                                }
                            }
                        }
                    }
                }
            }
            $reserveController = new ReserveAPIController();

            $response = $reserveController->findByProductAndSku($product_id, $skuProduct, $id_comercial);
            $searchResult = json_decode($response->getContent());

            if ($searchResult && $searchResult->response) {
                $currentDateTime = date('Y-m-d H:i:s');

                $reserve = $searchResult->reserve;
                $previous_stock = $reserve->stock;
                if ($type == 0 && $units > $reserve->stock) {
                    error("No Dispone de Stock en la Reserva");
                    return response()->json([
                        'error' => 'Error no se encontro la reserva: '
                    ], 500);
                }

                if ($type != 3) { //add o remove

                    if ($type == 1) {
                        error_log("add");
                        $productFound->stock -= $units;

                        if ($isvariable == 1) {
                            //update stock variant
                            if (isset($features['variants']) && is_array($features['variants'])) {
                                foreach ($features['variants'] as $key => $variant) {
                                    if ($variant['sku'] == $onlySku) {
                                        error_log("rmove to variable " . $onlySku);

                                        $features['variants'][$key]['inventory_quantity'] -= $units;
                                        break;
                                    }
                                }
                            }
                            $productFound->features = json_encode($features);
                        }
                    } else if ($type == 0) {
                        error_log("remove");

                        $productFound->stock += $units;

                        if ($isvariable == 1) {
                            //update stock variant
                            if (isset($features['variants']) && is_array($features['variants'])) {
                                foreach ($features['variants'] as $key => $variant) {
                                    if ($variant['sku'] == $onlySku) {
                                        error_log("add to variable " . $onlySku);

                                        $features['variants'][$key]['inventory_quantity'] += $units;
                                        break;
                                    }
                                }
                            }
                            $productFound->features = json_encode($features);
                        }
                    }
                    $productFound->save();

                    $label = "";
                    if ($type == 0) {
                        $reserve->stock -= $units;
                        $label = "Reducción-reserva";
                    } else if ($type == 1) {
                        $reserve->stock += $units;
                        $label = "Incremento-reserva";
                    }

                    $reserveModel = Reserve::find($reserve->id);
                    if ($reserveModel) {
                        $reserveModel->stock = $reserve->stock;
                        $reserveModel->updated_by = $updated_by;
                        $reserveModel->save();
                    }

                    $createHistory = new StockHistory();
                    $createHistory->product_id = $product_id;
                    $createHistory->variant_sku = $skuProduct;
                    $createHistory->type = $type;
                    $createHistory->date = $currentDateTime;
                    $createHistory->units = $units;
                    $createHistory->last_stock_reserve = $previous_stock;
                    $createHistory->current_stock_reserve = $reserve->stock;
                    $createHistory->description = $label . " " . $description . " " . $nombreComercial;
                    $createHistory->updated_by = $updated_by;
                    $createHistory->save();

                    if ($type == 0) {
                        //record StockHistory add units to stockPublic
                        $newHistory = new StockHistory();
                        $newHistory->product_id = $product_id;
                        $newHistory->variant_sku = $skuProduct;
                        $newHistory->type = 1;
                        $newHistory->date = $currentDateTime;
                        $newHistory->units = $units;
                        $newHistory->last_stock = $productFound->stock - $units;
                        $newHistory->current_stock = $productFound->stock;
                        $newHistory->description = "Ingreso-Stock general por reducción de reserva " . $nombreComercial;
                        $newHistory->updated_by = $updated_by;
                        $newHistory->save();
                    }
                }
                if ($type == 3) { //to delete
                    error_log("delete");
                    //add units to stockPublic
                    $reserveFound = Reserve::find($reserve->id);
                    $stockReserved = $reserveFound->stock;

                    if ($reserveFound) {

                        $productFound->stock += $stockReserved;

                        if ($isvariable == 1) {
                            //update stock variant
                            if (isset($features['variants']) && is_array($features['variants'])) {
                                foreach ($features['variants'] as $key => $variant) {
                                    if ($variant['sku'] == $onlySku) {
                                        $features['variants'][$key]['inventory_quantity'] += $stockReserved;
                                        break;
                                    }
                                }
                            }
                            $productFound->features = json_encode($features);
                        }
                        $productFound->save();
                        // error_log("prodAferSave: " . $productFound);

                        //record StockHistory add stockReserved to stockPublic
                        $newHistory = new StockHistory();
                        $newHistory->product_id = $product_id;
                        $newHistory->variant_sku = $skuProduct;
                        $newHistory->type = 1;
                        $newHistory->date = $currentDateTime;
                        $newHistory->units = $stockReserved;
                        $newHistory->last_stock = $productFound->stock - $stockReserved;
                        $newHistory->current_stock = $productFound->stock;
                        $newHistory->description = "Ingreso-Stock general por eliminación de reserva " . $nombreComercial;
                        $newHistory->updated_by = $updated_by;
                        $newHistory->save();

                        //delete reserve
                        $reserveFound->delete();
                    }
                }
                $responses[] = ['message' => 'Reserva eliminada con éxito'];
            } else {
                error("Error no se encontro la reserva");
                return response()->json([
                    'error' => 'Error no se encontro la reserva: '
                ], 500);
            }

            DB::commit();
            return response()->json([
                "res" => "Se edito la reserva y el stockHistory exitosamente",
                200
            ]);
        } catch (\Exception $e) {
            error("adminReserve_Error: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
}
