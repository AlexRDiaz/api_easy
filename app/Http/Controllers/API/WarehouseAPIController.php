<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Transportadora;
use App\Models\UpUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class WarehouseAPIController extends Controller
{

    public function index()
    {
        //
        // $warehouses = Warehouse::all();
        $warehouses = Warehouse::with('provider')
            ->select('warehouse_id', 'branch_name', 'address', 'city', 'active', 'approved', 'customer_service_phone', 'provider_id',)
            ->get();

        return response()->json(['warehouses' => $warehouses]);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'branch_name' => 'nullable|string|max:70',
                'address' => 'nullable|string|max:70',
                'customer_service_phone' => 'nullable|string|max:70',
                'reference' => 'nullable|string|max:70',
                'description' => 'nullable|string|max:65535',
                'url_image' => 'nullable|string|max:150',
                'id_provincia' => 'nullable|int',
                'id_city' => 'nullable|int',
                'city' => 'nullable|string|max:80',
                'collection' => 'nullable|json',
                'provider_id' => 'nullable|integer',
            ]);

            $data = $request->json()->all();
            $branch_name = $data['branch_name'];
            $address = $data['address'];
            $customer_service_phone = $data['customer_service_phone'];
            $reference = $data['reference'];
            $description = $data['description'];
            $url_image = $data['url_image'];
            $id_provincia = $data['id_provincia'];
            $id_city = $data['id_city'];
            $city = $data['city'];
            $collection = $data['collection'];
            $provider_id = $data['provider_id'];

            $newWarehouse = new Warehouse();
            $newWarehouse->branch_name = $branch_name;
            $newWarehouse->address = $address;
            $newWarehouse->customer_service_phone = $customer_service_phone;
            $newWarehouse->reference = $reference;
            $newWarehouse->description = $description;
            $newWarehouse->url_image = $url_image;
            $newWarehouse->id_provincia = $id_provincia;
            $newWarehouse->id_city = $id_city;
            $newWarehouse->city = $city;
            $newWarehouse->collection = $collection;
            $newWarehouse->provider_id = $provider_id;
            $newWarehouse->approved = 1; //Default db 2 Pendiente //1 Aprobado //0 Rechazado 3//Descontinuado
            $newWarehouse->save();

            // $warehouse = Warehouse::create($validatedData);
            if ($newWarehouse) {
                $to = 'easyecommercetest@gmail.com';
                $subject = 'Aprobación de una bodega nueva';
                $message = 'Se ha creado la bodega "' . $request->branch_name . '" a la espera de la aprobación de funcionamiento.';
                Mail::raw($message, function ($mail) use ($to, $subject) {
                    $mail->to($to)->subject($subject);
                });

                return response()->json($newWarehouse, 201); // 201: Recurso creado

            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500); // 500: Error interno del servidor
        }
    }


    public function show(string $warehouse_id)
    {
        $warehouse = Warehouse::where('warehouse_id', $warehouse_id)->first();
        if (!$warehouse) {
            return response()->json(['message' => 'Not Found!'], 404);
        }
        return response()->json($warehouse);
    }




    public function update(Request $request, string $warehouse_id)
    {
        $validatedData = $request->validate([
            'branch_name' => 'nullable|string|max:70',
            'address' => 'nullable|string|max:70',
            'customer_service_phone' => 'nullable|string|max:70',
            'reference' => 'nullable|string|max:70',
            'description' => 'nullable|string|max:65535',
            'url_image' => 'nullable|string|max:150',
            'city' => 'nullable|string|max:80',
            'collection' => 'nullable|json',
            'id_provincia' => 'nullable|integer',
            'id_city' => 'nullable|integer',
            'active' => 'nullable|integer',
            'approved' => 'nullable|integer',
            'provider_id' => 'nullable|integer',
        ]);

        $warehouse = Warehouse::where('warehouse_id', $warehouse_id)->first();
        if (!$warehouse) {
            return response()->json(['message' => 'Not Found!'], 404);
        }
        $warehouse->update($validatedData);
        return response()->json($warehouse);
    }

    public function deactivate(string $warehouse_id)
    {
        $warehouse = Warehouse::where('warehouse_id', $warehouse_id)->first();
        if (!$warehouse) {
            return response()->json(['message' => 'Not Found!'], 404);
        }

        $warehouse->update(['active' => 0]);

        return response()->json(['message' => 'Deactivated Successfully'], 200);
    }

    public function activate(string $warehouse_id)
    {
        $warehouse = Warehouse::where('warehouse_id', $warehouse_id)->first();
        if (!$warehouse) {
            return response()->json(['message' => 'Not Found!'], 404);
        }

        $warehouse->update(['active' => 1]);

        return response()->json(['message' => 'Deactivated Successfully'], 200);
    }



    public function filterByProvider($provider_id)
    {
        // Usamos el método where para filtrar por 'provider_id'
        $warehouses = Warehouse::where('provider_id', $provider_id)->get();

        // Verificamos si la colección está vacía
        if ($warehouses->isEmpty()) {
            return response()->json(['message' => 'No warehouses found for the given provider ID'], 404);
        }

        // Usamos el método with para cargar la relación 'provider'
        $warehouses = Warehouse::with('provider')->where('provider_id', $provider_id)->get();

        return response()->json(['warehouses' => $warehouses]);
    }

    public function approvedWarehouses(Request $request)
    {
        $data = $request->json()->all();
        $idTransportadora = $data['idTransportadora'];

        // Obtener el nombre de la transportadora
        $transportadoraNombre = Transportadora::where('id', $idTransportadora)->pluck('nombre')->first();

        // Obtener almacenes aprobados
        $warehouses = Warehouse::where('approved', 1)->get();

        // Días de la semana mapeados
        $daysOfWeek = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

        // Transformaciones en las propiedades
        $warehouses = $warehouses->filter(function ($warehouse) use ($daysOfWeek, $transportadoraNombre) {
            // Decodificar la cadena JSON en 'collection'
            $collection = json_decode($warehouse['collection'], true);

            // Convertir 'collectionDays' a nombres de días
            $collection['collectionDays'] = array_map(function ($day) use ($daysOfWeek) {
                return $daysOfWeek[$day]; // No restamos porque los días comienzan desde 1
            }, $collection['collectionDays']);

            // Filtrar almacenes con 'collectionTransport' igual al nombre de la transportadora
            return isset($collection['collectionTransport']) && $collection['collectionTransport'] == $transportadoraNombre;
        })->map(function ($warehouse) use ($daysOfWeek) {
            // Decodificar la cadena JSON en 'collection'
            $collection = json_decode($warehouse['collection'], true);

            // Convertir 'collectionDays' a nombres de días
            $collection['collectionDays'] = array_map(function ($day) use ($daysOfWeek) {
                return $daysOfWeek[$day]; // No restamos porque los días comienzan desde 1
            }, $collection['collectionDays']);

            // Asignar la colección modificada de nuevo a 'collection'
            $warehouse['collection'] = $collection;

            return $warehouse;
        });
        // Reindexar el array numéricamente
        $warehouses = $warehouses->values();

        return response()->json(['warehouses' => $warehouses]);
    }

    public function getSpecials(string $idCompany)
    {
        error_log("getSpecials");
        error_log($idCompany);
        $warehouses = Warehouse::with('provider')
            ->whereHas('provider', function ($query) {
                $query->where('active', 1)->where('approved', 1)->where('special', 1);
            })
            ->where('active', 1)
            ->where('approved', 1)
            // ->get();
            ->get(['branch_name', 'warehouse_id', 'city', 'provider_id']);

        return response()->json(['warehouses' => $warehouses]);
    }

    public function bySubprov(string $idSubProv)
    {
        //
        error_log("bySubprov");
        try {
            $user = UpUser::with('warehouses')
                ->where('id', $idSubProv)
                ->first();

            if ($user) {
                $warehouses = $user->warehouses->map(function ($warehouse) {
                    return [
                        'warehouse_id' => $warehouse->warehouse_id,
                        'branch_name' => $warehouse->branch_name,
                        'provider_id' => $warehouse->provider_id
                    ];
                });

                return response()->json($warehouses);
            } else {
                return response()->json(['error' => 'User not found'], 404);
            }
        } catch (\Exception $e) {
            error_log("error: $e");
            return response()->json([
                'error' => "There was an error processing your request. " . $e->getMessage()
            ], 500);
        }
    }
}
