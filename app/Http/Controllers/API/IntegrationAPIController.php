<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateIntegrationAPIRequest;
use App\Http\Requests\API\UpdateIntegrationAPIRequest;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CarrierCoverage;
use App\Models\CarriersExternal;
use App\Models\Novedade;
use App\Models\NovedadesPedidosShopifyLink;
use App\Models\PedidosShopify;
use App\Models\Vendedore;
use Fpdf\Fpdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

use function Laravel\Prompts\error;
use TCPDF;

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

    public function getLabelGTM(Request $request)
    {
        error_log("getLabelGTM");

        // Obtener los datos del formulario
        $data = $request->all();

        // URL de la API externa
        $apiUrl = 'https://ec.gintracom.site/web/easy/label';

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
            if ($response->header('content-type') === 'application/pdf') {
                return response($response->body())
                    ->header('Content-Type', 'application/pdf');
            } else {
                return $response->json();
            }
        } else {
            // La solicitud falló
            error_log("La solicitud a la API externa falló $response");
            return response()->json(['error' => 'La solicitud a la API externa falló'], $response->status());
        }
    }

    public function getMultiLabels(Request $request)
    {
        error_log("getMultiLabels");
        try {

            $data = $request->all();

            $ids = $data['ids'];

            $idsArray = json_decode($ids, true);
            $pdfFinal = new Fpdi();

            foreach ($idsArray as $id) {
                $request = new Request();

                $request->merge([
                    'guia' => $id
                ]);
                $res = $this->getLabelGTM($request);
                $content = $res->getContent();

                $pdfFinal->setSourceFile(StreamReader::createByString($content));
                $templateId = $pdfFinal->importPage(1); // Importar la primera página del PDF
                $pdfFinal->addPage(); // Agregar una nueva página al PDF principal
                $pdfFinal->useTemplate($templateId, 0, 0, $pdfFinal->GetPageWidth(), $pdfFinal->GetPageHeight(), true); // Usar la página importada como plantilla en la nueva página y ajustar su tamaño para que ocupe toda la página

            }

            $pdfContent = $pdfFinal->Output('', 'S');

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="pdftotal.pdf"');
        } catch (\Exception $e) {
            error_log("Error: $e");

            return response()->json(['Error'], 500);
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
            error_log("requestUpdateState ");
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
                error_log("guia input: $guia ");
                error_log("estado input: $estado ");
                error_log("path input: $path ");
                error_log("id_novedad input: $id_novedad ");
                error_log("no_novedad input: $no_novedad ");


                // error_log("pedido: $order");
                // error_log("pedido: $order");
                $order = PedidosShopify::with(['users.vendedores', 'product.warehouses', 'ciudadExternal'])
                    ->where('carrier_external_id', 1)
                    ->where('id_externo', $guia)->first();
                // error_log("pedido: $order");

                // Obtener los almacenes del producto
                $warehouses = $order['product']['warehouses'];

                // Llamar a la función getWarehouseAddress y pasarle los datos de almacén
                $prov_origen = $this->getWarehouseProv($warehouses);
                error_log("Remitente product provincia: $prov_origen");

                $orderData = json_decode($order, true);
                $prov_destiny = $orderData['ciudad_external']['id_provincia'];
                $city_destiny = $orderData['ciudad_external']['id'];
                error_log("prov_destiny: $prov_destiny");
                error_log("city_destiny: $city_destiny");

                $variants = $orderData['variant_details'];
                error_log("->> $variants");

                $carrierExternal = CarriersExternal::where('id', 1)->first();
                // error_log("carrierExternal: $carrierExternal");

                $status_array = json_decode($carrierExternal->status, true);

                // Decodificar la cadena JSON en un arreglo asociativo
                $costs = json_decode($carrierExternal->costs, true);

                // Acceder a los valores de costs
                $costo_seguro = $costs['costo_seguro'];

                $city_type = CarrierCoverage::where('id_carrier', 1)->where('id_coverage', $city_destiny)->first();
                $coverage_type = $city_type['type'];

                DB::beginTransaction();
                try {

                    foreach ($status_array as $status) {
                        $id_ref = $status['id_ref'];

                        if ($id_ref == $estado) {
                            // error_log("id_ref: $id_ref");

                            $key = $status['estado'];
                            $name_local = $status['name_local'];
                            $name = $status['name'];
                            $id = $status['id'];

                            // error_log("name_local: $$name_local");

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

                                    $deliveryPrice = 0;
                                    if ($prov_destiny == $prov_origen) {
                                        error_log("Provincial");
                                        if ($coverage_type == "Normal") {
                                            $deliveryPrice = (float)$costs['normal1'];
                                        } else {
                                            $deliveryPrice = (float)$costs['especial1'];
                                        }
                                    } else {
                                        error_log("Nacional");
                                        if ($coverage_type == "Normal") {
                                            $deliveryPrice = (float)$costs['normal2'];
                                        } else {
                                            $deliveryPrice = (float)$costs['especial2'];
                                        }
                                    }
                                    error_log("after type: $deliveryPrice");

                                    $costo_seguro = (((float)$orderData['precio_total']) * ((float)$costs['costo_seguro'])) / 100;
                                    $costo_seguro = round($costo_seguro, 2);

                                    $deliveryPrice += $costo_seguro;
                                    error_log("after costo_seguro: $deliveryPrice");

                                    if ($orderData['recaudo'] == 1) {
                                        if (((float)$orderData['precio_total']) <= ((float)$costs['costo_recaudo']['max_price'])) {
                                            $base = round(((float)$costs['costo_recaudo']['base']), 2);
                                            $deliveryPrice += $base;
                                        } else {
                                            $incremental = (((float)$orderData['precio_total']) * ((float)$costs['costo_recaudo']['incremental'])) / 100;
                                            $incremental = round($incremental, 2);
                                            $deliveryPrice += $incremental;
                                        }
                                    }

                                    error_log("after recaudo: $deliveryPrice");
                                    $order->costo_transportadora = strval($deliveryPrice);
                                    $order->estado_logistico = $name_local;
                                    $order->sent_at = $currentDateTime;
                                    // $order->sent_by = $idUser;
                                    $order->marca_tiempo_envio = $date;
                                    $order->estado_interno = "CONFIRMADO";
                                    $order->fecha_entrega = $date;

                                    //reduccion del stock falta
                                    /*
                                    $request = new Request();

                                    $request->merge([
                                        'variant_detail' => $orderData['variant_detail'] ,
                                        'id_comercial' => $orderData['id_comercial'],
                                        'type' => 0, //reducir
                                    ]);
                                    $productController = new ProductAPIController();
                                    $response = $productController->updateProductVariantStock($request);
                                    */
                                    //
                                }
                            } else if ($key == "status") {
                                if ($name_local == "ENTREGADO" || $name_local == "NO ENTREGADO") {
                                    $order->status = $name_local;
                                    $order->fecha_entrega = $date;
                                    $order->status_last_modified_at = $currentDateTime;
                                    // $order->status_last_modified_by = $idUser;

                                    $nombreComercial = $order->users[0]->vendedores[0]->nombre_comercial;
                                    $codigo_order = $nombreComercial . "-" . $order->id;
                                    error_log("codigo_order: $codigo_order");


                                    //THIS solo para transacciones
                                    // $SellerCreditFinalValue = $this->updateProductAndProviderBalance(
                                    //     $variants,
                                    //     $orderData['precio_total'],
                                    //     'GTM',
                                    //     $orderData['precio_total'],
                                    //     $name_local,
                                    //     $codigo_order,

                                    // );

                                    // if (isset($SellerCreditFinalValue['value_product_warehouse']) && $SellerCreditFinalValue['value_product_warehouse'] !== null) {
                                    //     $order->value_product_warehouse = $SellerCreditFinalValue['value_product_warehouse'];
                                    // }

                                    // $vendedor = Vendedore::where('id_master', $order->id_comercial)->first();
                                    // if ($vendedor->referer != null) {
                                    //     $vendedorPrincipal = Vendedore::where('id_master', $vendedor->referer)->first();
                                    //     if ($vendedorPrincipal->referer_cost != 0) {
                                    //         $order->value_referer = $vendedorPrincipal->referer_cost;
                                    //     }
                                    // }


                                    //
                                } else if ($name_local == "NOVEDAD") {
                                    //
                                    $novedades = NovedadesPedidosShopifyLink::where('pedidos_shopify_id', $order->id)->get();
                                    $novedades_try = $novedades->isEmpty() ? 0 : $novedades->count();

                                    $novedad = new Novedade();
                                    $novedad->m_t_novedad = $currentDateTimeText;
                                    $novedad->try = $novedades_try + 1;
                                    $novedad->url_image = $path; //como manejar para mostrar
                                    $novedad->comment = $no_novedad;
                                    $novedad->published_at = $currentDateTime;
                                    $novedad->save();

                                    $novedad_pedido = new NovedadesPedidosShopifyLink();
                                    $novedad_pedido->novedad_id = $novedad->id;
                                    $novedad_pedido->pedidos_shopify_id = $order->id;
                                    $novedad_pedido->novedad_order = $novedades_try + 1;
                                    $novedad_pedido->save();
                                }
                                $order->status = $name_local;
                                $order->status_last_modified_at = $currentDateTime;
                                // $order->status_last_modified_by = $idUser;
                            } else if ($key == "estado_devolucion") {
                                //solo se puede poner en devolucion si se encuentra en NOVEDAD
                                if ($order->status == "NOVEDAD") {
                                    //
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
                                    $refundpercentage = $costs['costo_devolucion'];
                                    $refound_seller = (((float)$orderData['costo_envio']) * ($refundpercentage)) / 100;
                                    $refound_transp = (((float)$orderData['costo_transportadora']) * ($refundpercentage)) / 100;

                                    $order->costo_devolucion = round(((float)$refound_seller), 2);
                                    $order->cost_refound_external = round(((float)$refound_transp), 2);
                                }else{
                                    return response()->json(['message' => "Error, Order must be in NOVEDAD."], 400);
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


    function getWarehouseProv($warehousesJson)
    {
        $name = "";

        $warehousesList = json_decode($warehousesJson, true);

        foreach ($warehousesList as $warehouseJson) {
            if (is_array($warehouseJson)) {
                if (count($warehousesList) == 1) {
                    $name = "{$warehouseJson['id_provincia']}";
                } else {
                    $lastWarehouse = end($warehousesList);
                    $name = "{$lastWarehouse['id_provincia']}";
                }
            } else {
                error_log("El elemento de la lista no es un mapa válido: ");
            }
        }
        return $name;
    }
}
