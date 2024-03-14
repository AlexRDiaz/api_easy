<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\CreateProviderAPIRequest;
use App\Http\Requests\API\UpdateProviderAPIRequest;
use App\Models\Provider;
use App\Models\UpUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;


/**
 * Class ProviderAPIController
 */
class ProviderAPIController extends Controller
{

    public function show($id)
    {
        $pedido = Provider::findOrFail($id);

        return response()->json($pedido);
    }

    public function getProviders($search = null)
    {
        $providers = Provider::with(['user', 'warehouses']);

        if (!empty($search)) {
            $providers->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        return response()->json(['providers' => $providers->where('active', 1)->get()]);
    }

    public function index()
    {
        //
        $providers = Provider::with('warehouses')->where('active', 1)->get();
        return response()->json(['providers' => $providers]);
    }


    public function destroy(string $id)
    {
        //
        Provider::where('id', $id)
            ->update(['active' => 0]);
    }


    public function updateRequest(Request $request, $id)
    {
        // Recuperar los datos del formulario
        $data = $request->all();

        // Encuentra el registro en base al ID
        $provider = Provider::findOrFail($id);

        // Actualiza los campos específicos en base a los datos del formulario
        $provider->fill($data);
        $provider->save();

        // Respuesta de éxito
        return response()->json(['message' => 'Registro actualizado con éxito', "res" => $provider], 200);
    }
    public function getSaldoP($id)
    {
        $provider = Provider::where("user_id",$id)->first();
        $saldo = $provider->saldo;

        // whereHas('user', function ($query) use ($id) {
            // $query->where('user.user_id', $id);
        // })->first();
        if (!$saldo) {
            return response()->json(['message' => 'Provider not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['saldo' => $saldo], Response::HTTP_OK);
        // return response()->json(['saldo' => $saldo], Response::HTTP_OK);
    }
}
