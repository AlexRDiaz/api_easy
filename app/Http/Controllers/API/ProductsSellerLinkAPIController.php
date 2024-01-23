<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductsSellerLink;
use Illuminate\Http\Request;

class ProductsSellerLinkAPIController extends Controller
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
            $data = $request->json()->all();
            // return response()->json($data, 200);

            $product_id = $data['product_id'];
            $id_master = $data['id_master'];
            $key = $data['key'];

            $newProductSeller = new ProductsSellerLink();
            $newProductSeller->product_id = $product_id;
            $newProductSeller->id_master = $id_master;
            if ($key == "favorite") {
                $newProductSeller->favorite = 1;
            } elseif ($key == "onsale") {
                $newProductSeller->onsale = 1;
            }

            $newProductSeller->save();
            return response()->json($newProductSeller, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500); // 500: Error interno del servidor
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
        $productSeller = ProductsSellerLink::findOrFail($id);
        $productSeller->update($request->all());
        return response()->json(['message' => 'Actualización exitosa'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    //

    public function getProductSeller(Request $request)
    {
        try {
            $data = $request->json()->all();

            if (!$data || !isset($data['product_id']) || !isset($data['id_master'])) {
                return response()->json(['error' => 'Datos incompletos'], 400);
            }

            $product_id = $data['product_id'];
            $id_master = $data['id_master'];

            // return response()->json($data, 200);
            $productSeller = ProductsSellerLink::where('product_id', $product_id)
                ->where('id_master', $id_master)
                ->first();
            if (!$productSeller) {
                error_log("Respuesta: " . "No hay un productSeller con estas especificaciones");
                return response()->json(['product_id' => $product_id, 'id_master' => $id_master], 204);
            }

            return response()->json($productSeller, 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'Error en la consulta a la base de datos'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
