<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PedidosShopifiesCarrierExternalLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidosShopifiesCarrierExternalLinkAPIController extends Controller
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
        error_log("PedidosCarrierLinkAPI store");
        try {
            $data = $request->json()->all();
            // return response()->json($data, 200);

            $pedidos_shopify_id = $data['order_id'];
            $carrier_id = $data['carrier_id'];
            $city_external_id = $data['city_external_id'];
            $external_id = $data['external_id'];

            $pedidoCarrier = PedidosShopifiesCarrierExternalLink::where('pedidos_shopify_id', $pedidos_shopify_id)->first();

            if ($pedidoCarrier != null) {
                error_log("update");
                $pedidoCarrier->pedidos_shopify_id = $pedidos_shopify_id;
                $pedidoCarrier->carrier_id = $carrier_id;
                $pedidoCarrier->city_external_id = $city_external_id;
                $pedidoCarrier->external_id = $external_id;
                $pedidoCarrier->save();
                return response()->json(['message' => 'Registro actualizado con éxito', "res" => $pedidoCarrier], 200);
            } else {
                error_log("new");
                $newPedidoCarrier = new PedidosShopifiesCarrierExternalLink();
                $newPedidoCarrier->pedidos_shopify_id = $pedidos_shopify_id;
                $newPedidoCarrier->carrier_id = $carrier_id;
                $newPedidoCarrier->city_external_id = $city_external_id;
                $newPedidoCarrier->external_id = $external_id;
                $newPedidoCarrier->save();
                return response()->json(['message' => 'Registro creado con éxito', "res" => $newPedidoCarrier], 200);
            }
        } catch (\Exception $e) {
            error_log("Error: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $pedidoCarrier = PedidosShopifiesCarrierExternalLink::with(['carrier', 'cityExternal'])
            ->where('pedidos_shopify_id', $id)->first();

        if (!$pedidoCarrier) {
            return response()->json(['message' => 'pedidoCarrier no encontrada'], 404);
        }
        return response()->json($pedidoCarrier, 200);
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
        // Recuperar los datos del formulario
        $data = $request->all();
        try {
            // Encuentra el registro en base al ID
            $pedidoCarrier = PedidosShopifiesCarrierExternalLink::findOrFail($id);

            // Actualiza los campos específicos en base a los datos del formulario
            $pedidoCarrier->fill($data);
            $pedidoCarrier->save();

            // Respuesta de éxito
            return response()->json(['message' => 'Registro actualizado con éxito', "res" => $pedidoCarrier], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateByOrder(Request $request, string $id)
    {
        //
        // Recuperar los datos del formulario
        $data = $request->all();
        try {
            // Encuentra el registro en base al ID
            $pedidoCarrier = PedidosShopifiesCarrierExternalLink::where('pedidos_shopify_id', $id)->first();

            // Actualiza los campos específicos en base a los datos del formulario
            $pedidoCarrier->fill($data);
            $pedidoCarrier->save();

            // Respuesta de éxito
            return response()->json(['message' => 'Registro actualizado con éxito', "res" => $pedidoCarrier], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        DB::beginTransaction();
        try {
            error_log("OrderCarrier Destroy");
            $pedidoCarrier = PedidosShopifiesCarrierExternalLink::where('pedidos_shopify_id', $id)->first();

            if (!$pedidoCarrier) {
                return response()->json(['message' => 'No se encontraro pedidoCarrier con el ID especificado'], 404);
            }

            $pedidoCarrier->delete();

            DB::commit();
            return response()->json(['message' => 'PedidoCarrier eliminado correctamente'], 200);
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
}
