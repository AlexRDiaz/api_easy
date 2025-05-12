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

    public function getProviders(Request $request)
    {
        $search = $request->input('search', ''); // Por defecto, vacío
        $companyId = $request->input('company_id');

        $providers = Provider::with(['user', 'warehouses']);

        // Construir la consulta base
        $providers = Provider::with(['user', 'warehouses'])
            ->where('active', 1)
            ->where('company_id', $companyId);

        // Aplicar búsqueda si hay un término
        if (!empty($search)) {
            $providers->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        // Obtener los resultados
        $result = $providers->get();

        // Devolver la respuesta JSON
        return response()->json(['providers' => $result]);
    }

    public function index(string $companyId)
    {
        //
        $providers = Provider::with('warehouses')
            ->where('company_id', $companyId)
            ->get();
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
        // error_log("getSaldoP from $id");

        try {

            $provider = Provider::where("user_id", $id)->first();

            if (!$provider) {
                error_log("!saldo");
                return response()->json(['message' => 'Provider not found'], 404);
            }

            $saldo = $provider->saldo;
            return response()->json(['saldo' => $saldo], 200);
        } catch (\Exception $e) {
            error_log("error: $e");
            return response()->json([
                'error' => "There was an error processing your request. " . $e->getMessage()
            ], 500);
        }
    }

    public function getProvidersPaginated(Request $request)
    {
        $search = $request->input('search', '');
        $companyId = $request->input('company_id');
        $pageSize = $request->input('pageSize', 70); 

        $providers = Provider::with(['user', 'warehouses'])
            ->where('active', 1)
            ->where('company_id', $companyId);

        if (!empty($search)) {
            $providers->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $paginated = $providers->paginate($pageSize);

        return response()->json([
            'providers' => $paginated->items(),     
            'total' => $paginated->total(),         
            'from' => $paginated->firstItem(),      
            'to' => $paginated->lastItem(),          
            'last_page' => $paginated->lastPage(),   
        ]);
    }
}
