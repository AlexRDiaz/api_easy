<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PedidosProductLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidosProductLinkAPIController extends Controller
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
        try {
            //
            $data = $request->json()->all();
            $idOrder = $data['id_order'];
            $idProduct = $data['id_product'];

            $newPedidoProduct = new PedidosProductLink();
            $newPedidoProduct->pedidos_shopify_id =  $idOrder;
            $newPedidoProduct->product_id =     $idProduct;

            $newPedidoProduct->save();

            return response()->json(['message' => 'pedidoProd creado correctamente'], 200);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
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
    public function destroy(Request $request)
    {
        //
        DB::beginTransaction();
        try {
            error_log("orderProductLinkAPIDestroy");
            $data = $request->json()->all();
            $idOrder = $data['id_order'];
            $idProduct = $data['id_product'];

            $pedidoProd = PedidosProductLink::where('product_id', $idProduct)
                ->where('pedidos_shopify_id', $idOrder)
                ->first();


            if (!$pedidoProd) {
                return response()->json(['message' => 'No se encontraro PedidosProductLink con el ID especificado'], 404);
            }

            $pedidoProd->delete();


            DB::commit();
            return response()->json(['message' => 'pedidoProd eliminados correctamente'], 200);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
}
