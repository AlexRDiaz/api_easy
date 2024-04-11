<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductWarehouseLink;
use Illuminate\Http\Request;

class ProductWarehouseLinkAPIController extends Controller
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
            $id_product = $data['idProduct'];
            $id_warehouse = $data['idWarehouse'];
            // create provider_warehouse_link
            $exists = ProductWarehouseLink::where('id_product', $id_product)->where('id_warehouse', $id_warehouse)->first();
            error_log("$exists");
            if ($exists == null) {
                # code...
                $productwarehouselink = new ProductWarehouseLink();
                $productwarehouselink->id_product = $id_product;
                $productwarehouselink->id_warehouse = $id_warehouse;
                $productwarehouselink->save();

                return response()->json(['message' => 'ProductWarehouseLink creado con éxito'], 200);
            } else {
                error_log("Error,esta relacion ya existe"); //so update

                $data = $request->all();

                // Encuentra el registro en base al ID
                $prodWarehouselink = ProductWarehouseLink::findOrFail($exists->id);
                $prodWarehouselink->id_product = $id_product;
                $prodWarehouselink->id_warehouse = $id_warehouse;
                $prodWarehouselink->save();

                return response()->json(['message' => 'ProductWarehouseLink actualizado'], 204);
            }
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
    public function update(Request $request)
    {
        //
        try {
            error_log("update");
            $data = $request->json()->all();
            $id_product = $data['idProduct'];
            $id_warehouse_old = $data['idWarehouse_old'];
            $id_warehouse_new = $data['idWarehouse_new'];

            $exists = ProductWarehouseLink::where('id_product', $id_product)->where('id_warehouse', $id_warehouse_old)->first();

            if ($exists != null) {
                $exists->id_product = $id_product;
                $exists->id_warehouse = $id_warehouse_new;
                $exists->save();
                return response()->json(['message' => 'ProductWarehouseLink actualizado'], 204);
            } else {
                error_log("Error,esta relacion NO existe"); //so update
            }
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
