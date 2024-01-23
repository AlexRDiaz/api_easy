<?php



namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\ProductAPIController;
use App\Models\Ruta;

use App\Models\Product;
use App\Models\StockHistory;
use Illuminate\Http\Request;

class StockHistoryAPIController extends Controller
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
        $data = $request->json()->all();


        $product_id = $data['product_id'];
        $skuProduct = $data['sku_product'];
        $units = $data['units'];
        $description = $data['description'];
        $type = $data['type'];

        $currentDateTime = date('Y-m-d H:i:s');

        $product = Product::find($product_id);

        if ($product === null) {
            return response()->json(['message' => 'Product not found'], 404);
        }


        $last_stock = $product->stock;
        $result = $product->changeStockGen($product_id, $skuProduct, $units, $type);
        $product2 = Product::find($product_id);
        $current_stock = $product2->stock;

        $createHistory = new StockHistory();
        $createHistory->product_id = $product_id;
        $createHistory->variant_sku = $skuProduct;
        $createHistory->type = $type;
        $createHistory->date = $currentDateTime;
        $createHistory->units = $units;
        $createHistory->last_stock = $last_stock;
        $createHistory->current_stock = $current_stock;
        $createHistory->description = $description;

        $createHistory->save();

        return $product;
    }


    public function storeD(Request $request)
    {
        //
        $data = $request->json()->all();
        $product_id = $data['product_id'];
        $skuProduct = $data['sku_product'];
        $units = $data['units'];
        $description = $data['description'];
        $type = $data['type'];

        $currentDateTime = date('Y-m-d H:i:s');

        $product = Product::find($product_id);

        if ($product === null) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // $result = $product->changeStockGen($product_id, $skuProduct, $units, $type);
        $product2 = Product::find($product_id);
        $current_stock = $product2->stock;

        if ($type == 0) {
            $respuestalast = $current_stock - $units;
        } else {
            $respuestalast = $current_stock + $units;
        }
        
        $createHistory = new StockHistory();
        $createHistory->product_id = $product_id;
        $createHistory->variant_sku = $skuProduct;
        $createHistory->type = $type;
        $createHistory->date = $currentDateTime;
        $createHistory->units = $units;
        $createHistory->last_stock = $respuestalast;
        $createHistory->current_stock = $current_stock;
        $createHistory->description = $description;

        $createHistory->save();

        return $product;
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


    public function showByProduct(string $id)
    {
        //

        $history = StockHistory::where('product_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        if (!$history) {
            return response()->json(['message' => 'No se ha encontrado un producto con el ID especificado'], 404);
        }
        return response()->json($history);
    }
}

