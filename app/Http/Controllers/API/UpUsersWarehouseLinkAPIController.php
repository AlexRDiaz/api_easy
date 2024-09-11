<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UpUser;
use App\Models\UpUsersWarehouseLink;
use Illuminate\Http\Request;

class UpUsersWarehouseLinkAPIController extends Controller
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
            $id_user = $data['idUser'];
            $id_warehouse = $data['idWarehouse'];
            // create provider_warehouse_link
            $providerWarehouse = new UpUsersWarehouseLink();
            $providerWarehouse->id_user = $id_user;
            $providerWarehouse->id_warehouse = $id_warehouse;
            $providerWarehouse->save();

        } catch (\Exception $e) {
            error_log("ERROR_storeUpUsersWarehouseLink: $e");
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
        try {

            $upUser = UpUser::with(['warehouses' => function ($query) {
                $query->select('warehouses.warehouse_id', 'warehouses.branch_name');
            }])->find($id);

            if (!$upUser) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $warehouses = $upUser->warehouses->map(function ($warehouse) {
                return $warehouse->warehouse_id . '|' . $warehouse->branch_name;
            });

            return response()->json($warehouses, 200);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
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
        try {
            $upuserWare = UpUsersWarehouseLink::where("id_user", $id)->first();

            if (!$upuserWare) {
                return response()->json(['error' => 'No se encontró ningún UsersWarehouseLink con el ID especificado.'], 404);
            }

            $upuserWare->fill($request->all());
            $upuserWare->save();

            return response()->json(['message' => 'UserWarehouseLink actualizado con éxito'], 200);
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
