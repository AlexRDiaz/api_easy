<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateIntegrationAPIRequest;
use App\Http\Requests\API\UpdateIntegrationAPIRequest;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CarriersExternal;
use App\Models\PedidosShopify;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Class IntegrationAPIController
 */
class IntegrationAPIController extends Controller
{

    /**
     * Display a listing of the Integrations.
     * GET|HEAD /integrations
     */
    public function index()
    {
        //
        $integrations = Integration::all();
        return response()->json($integrations);
    }
    public function getIntegrationsByUser($id)
    {
        //
        $integrations = Integration::where("user_id", $id)->get();
        return response()->json($integrations);
    }

    public function putIntegrationsUrlStore(Request $request)
    {
        $authorizationHeader = $request->header('Authorization');
        $input = $request->all();

        //
        $token = explode(" ", $authorizationHeader)[1];
        $integration = Integration::where("token", $token)->first();

        if ($integration == null) {
            return response()->json(["response" => "token not found", "token" => $token, "integration" => $integration], Response::HTTP_BAD_REQUEST);
        }
        $integration->store_url = $input["store_url"];
        $integration->save();
        return response()->json(["response" => "url saved succesfully", "integration" => $integration], Response::HTTP_OK);
    }

    public function getIntegrationsByStorename(Request $request)
    {
        $input = $request->all();

        //
        $integration = Integration::where("store_url", $input["store_url"])->first();

        if ($integration == null) {
            return response()->json(["response" => "token not found"], Response::HTTP_NOT_FOUND);
        }
        return response()->json(["response" => "token get succesfully", "integration" => $integration], Response::HTTP_OK);
    }

    /**
     * Store a newly created Integration in storage.
     * POST /integrations
     */

    /**
     * Display the specified Integration.
     * GET|HEAD /integrations/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Integration $integration */
        $integration = Integration::find($id);

        if (empty($integration)) {
            return response()->json([
                'error' => 'No data '
            ], 404);
        }

        return response()->json([
            "res" => $integration
        ]);
    }


    /**
     * Update the specified Integration in storage.
     * PUT/PATCH /integrations/{id}
     */
    // public function update($id, UpdateIntegrationAPIRequest $request): JsonResponse
    // {
    //     $input = $request->all();

    //     /** @var Integration $integration */
    //     $integration = $this->integrationRepository->find($id);

    //     if (empty($integration)) {
    //         return $this->sendError('Integration not found');
    //     }

    //     $integration = $this->integrationRepository->update($input, $id);

    //     return $this->sendResponse($integration->toArray(), 'Integration updated successfully');
    // }

    /**
     * Remove the specified Integration from storage.
     * DELETE /integrations/{id}
     *
     * @throws \Exception
     */
    // public function destroy($id): JsonResponse
    // {
    //     /** @var Integration $integration */
    //     $integration = $this->integrationRepository->find($id);

    //     if (empty($integration)) {
    //         return $this->sendError('Integration not found');
    //     }

    //     $integration->delete();

    //     return $this->sendSuccess('Integration deleted successfully');
    // }


    public function putIntegrationsUrlStoreG(Request $request)
    {
        error_log("putIntegrationsUrlStoreG");

        // Obtener los datos del formulario
        $data = $request->all();

        // URL de la API externa
        $apiUrl = 'https://ec.gintracom.site/web/easy/pedido';

        // Nombre de usuario y contraseña para la autenticación básica
        $username = 'easy';
        $password = 'f7b2d589796f5f209e72d5697026500d';

        // Realizar la solicitud POST a la API externa con autenticación básica
        $response = Http::withBasicAuth($username, $password)
            ->post($apiUrl, $data);

        // Verificar el estado de la respuesta
        if ($response->successful()) {
            // La solicitud fue exitosa
            error_log("La solicitud fue exitosa");
            return $response->json(); // Devolver la respuesta JSON de la API externa
        } else {
            // La solicitud falló
            error_log("La solicitud a la API externa falló $response");
            return response()->json(['error' => 'La solicitud a la API externa falló'], $response->status());
        }
    }

    public function requestUpdateState()
    {
        //* Verificar si se proporcionaron credenciales de autenticación
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            error_log("Unauthorized-No credentials provided. Please provide your username and password.");
            return response()->json(['status' => 'Unauthorized', "message" => "No credentials provided. Please provide your username and password."], 401);
        }

        // Verificar las credenciales de autenticación
        $user = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        $access = true;

        if ($user !== 'gintracom' || $password !== '$2y$10$pxgkZvDG8CwMpKL10Rw1IukUAk4bWUlAAvERRNqYZJVMQ8PWfd4zW') {
            error_log("Credenciales de autenticación inválidas.");
            $access = false;
            return response()->json(['status' => 'Unauthorized', "message" => "Invalid credentials provided. Please try again."], 401);
        }

        if ($access) {
            $request_body = file_get_contents('php://input');
            $data = json_decode($request_body, true);
            // error_log(print_r($data, true));

            // Verificar si los campos obligatorios están presentes y no están vacíos
            $required_fields = ['guia', 'estado', 'fecha_historial'];
            $missing_fields = [];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $missing_fields[] = $field;
                }
            }

            // Si faltan campos, enviar un mensaje indicando qué campos faltan
            if (!empty($missing_fields)) {
                $missing_fields_str = implode(', ', $missing_fields);
                $error_message = "Missing required fields: $missing_fields_str";
                error_log($error_message);
                // Aquí puedes enviar una respuesta al cliente o manejar el error de otra manera según sea necesario
                return response()->json(['message' => $error_message], 400);
            } else {
                //* okey keep proccess
                $guia = $data['guia'];
                $estado = $data['estado'];
                $path = $data['path'];
                $latitud = $data['latitud'];
                $longitud = $data['longitud'];
                $id_novedad = $data['id_novedad'];
                $no_novedad = $data['no_novedad'];
                $fecha_historial = $data['fecha_historial'];

                $currentDateTime = date('Y-m-d H:i:s');
                $date = now()->format('j/n/Y');
                $currentDateTimeText = date("d/m/Y H:i");

                // foreach ($data as $key => $value) {
                //     error_log("$key: $value");
                // }

                $order = PedidosShopify::where('id_externo', $guia)->first();
                // error_log("pedido: $order");

                $carrierExternal = CarriersExternal::where('id', 1)->first();
                // error_log("carrierExternal: $carrierExternal");

                $status_array = json_decode($carrierExternal->status, true);


                DB::beginTransaction();
                try {

                    foreach ($status_array as $status) {
                        $id_ref = $status['id_ref'];

                        // error_log("id_ref: $id_ref");
                        // error_log("Estado: $estado");
                        if ($id_ref == $estado) {
                            $key = $status['estado'];
                            $name_local = $status['name_local'];
                            $name = $status['name'];
                            $id = $status['id'];
                            // error_log("Estado: $key, Nombre Local: $name_local, ID Ref: $id_ref, Nombre: $name, ID: $id");
                            if ($key == "estado_interno") {
                                // $order->estado_devolucion = "";

                            } else if ($key == "estado_logistico") {
                                if ($name_local == "IMPRESO") {  //from externo
                                    $order->estado_logistico = $name_local;
                                    $order->printed_at = $currentDateTime;
                                    // $order->printed_by = $idUser;
                                }
                                if ($name_local == "ENVIADO") {  //from externo
                                    $order->estado_logistico = $name_local;
                                    $order->sent_at = $currentDateTime;
                                    // $order->sent_by = $idUser;
                                    $order->marca_tiempo_envio = $date;
                                    $order->estado_interno = "CONFIRMADO";
                                    $order->fecha_entrega = $date;
                                }
                            } else if ($key == "status") {
                                if ($name_local == "ENTREGADO" || $name_local == "NO ENTREGADO") {
                                    $order->fecha_entrega = $date;
                                }
                                $order->status = $name_local;
                                $order->status_last_modified_at = $currentDateTime;
                                // $order->status_last_modified_by = $idUser;
                            } else if ($key == "estado_devolucion") {
                                if ($name_local == "EN BODEGA") { //from logistic
                                    $order->estado_devolucion = $name_local;
                                    $order->dl = $name_local;
                                    $order->marca_t_d_l = $currentDateTimeText;
                                    // $order->received_by = $idUser;
                                } else if ($name_local == "ENTREGADO EN OFICINA") {
                                    $order->estado_devolucion = $name_local;
                                    $order->dt = $name_local;
                                    $order->marca_t_d = $currentDateTimeText;
                                    // $order->received_by = $idUser;
                                } else if ($name_local == "DEVOLUCION EN RUTA") {
                                    $order->estado_devolucion = $name_local;
                                    $order->dt = $name_local;
                                    $order->marca_t_d_t = $currentDateTimeText;
                                    // $order->received_by = $idUser;
                                }
                            }

                            $order->save();

                            break;
                        }
                    }

                    DB::commit();
                    return response()->json(['message' => 'Order updated successfully.'], 200);

                } catch (\Exception $e) {
                    DB::rollback();
                    return response()->json([
                        'error' => "There was an error processing your request. " . $e->getMessage()
                    ], 500);
                }
            }
        }

    }
}
