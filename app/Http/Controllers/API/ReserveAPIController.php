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

        try {

            $data = $request->json()->all();

            $product_id = $data['product_id'];
            $sku = $data['sku'];
            $stock = $data['stock'];
            $id_comercial = $data['id_comercial'];
            $warehouse_price = $data['warehouse_price'];

            $user = UpUser::find($id_comercial); 

            $createReserve = new Reserve();
            $createReserve->product_id = $product_id;
            $createReserve->sku = $sku;
            $createReserve->stock = $stock;
            $createReserve->id_comercial = $id_comercial;
            $createReserve->warehouse_price = $warehouse_price;
            $createReserve->save();

            $description = "Reserva por " . $user->email;
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
                $createHistory->save();
                error_log("created reserve-History for type simple");
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
                $createHistory->save();
                error_log("created reserve-History for variant");
            }
            // return $createReserve;
            DB::commit(); 
            return response()->json([
                "res" => "Se realizo la reserva y el createHistory exitosamente"
            ]);
        } catch (\Exception $e) {
            DB::rollback(); // En caso de error, revierte todos los cambios realizados en la transacción
            // Maneja el error aquí si es necesario
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
}
