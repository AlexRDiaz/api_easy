<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateIntegrationAPIRequest;
use App\Http\Requests\API\UpdateIntegrationAPIRequest;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\TransaccionGlobal;
use App\Models\CarrierCoverage;
use App\Models\CarriersExternal;
use App\Models\IntegrationUser;
use App\Models\Novedade;
use App\Models\NovedadesPedidosShopifyLink;
use App\Models\PedidosShopifiesCarrierExternalLink;
use App\Models\PedidosShopify;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderTransaction;
use App\Models\Reserve;
use App\Models\StockHistory;
use App\Models\Transaccion;
use App\Models\UpUser;
use App\Models\Vendedore;
use App\Repositories\vendedorRepository;
use Carbon\Carbon;
use DateTime;
use Fpdf\Fpdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Facades\Log;


use function Laravel\Prompts\error;
use TCPDF;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

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
            error_log("La solicitud fue exitosa $response");
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
                $id_gestion = $data['id_gestion'];
                $no_gestion = $data['no_gestion'];
                $nota = $data['nota'];

                $currentDateTime = date('Y-m-d H:i:s');
                $date = now()->format('j/n/Y');
                $currentDateTimeText = date("d/m/Y H:i");

                // foreach ($data as $key => $value) {
                //     error_log("$key: $value");
                // }
                error_log("guia input: $guia ");
                error_log("estado input: $estado ");
                // error_log("path input: $path ");
                error_log("id_novedad input: $id_novedad ");
                error_log("no_novedad input: $no_novedad ");
                error_log("id_gestion input: $id_gestion ");
                error_log("no_gestion input: $no_gestion ");
                error_log("nota input: $nota ");



                // error_log("pedido: $order");
                // error_log("pedido: $order");
                $order = PedidosShopify::with(['users.vendedores', 'product.warehouses', 'pedidoCarrier'])
                    ->whereHas('pedidoCarrier', function ($query) use ($guia) {
                        $query->where('carrier_id', 1)
                            ->where('external_id', $guia);
                    })->first();

                // error_log("pedido: $order");



                // ! for transaction_global
                $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                    ->where('id_seller', $order->users[0]->vendedores[0]->id_master)
                    ->first();

                $marcaT = "";

                if (!empty($order->marca_t_i)) {
                    try {
                        $marca_t_i = $order->marca_t_i;

                        // Agregar ceros a las fechas/hora de un solo dígito en el formato 9/9/2024 9:9
                        $marca_t_i = preg_replace_callback('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{1,2})/', function ($matches) {
                            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                            $year = $matches[3];
                            $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
                            $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
                            return "$day/$month/$year $hour:$minute";
                        }, $marca_t_i);

                        // Intentar crear la fecha con el formato esperado
                        $marcaT = Carbon::createFromFormat('d/m/Y H:i', $marca_t_i)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Log::error('Error al convertir marca_t_i: ' . $pedido->marca_t_i . ' - ' . $e->getMessage());
                        // Asignar una fecha por defecto si la conversión falla
                        $marcaT = Carbon::now()->format('Y-m-d H:i:s');
                    }
                } else {
                    // Asignar una fecha por defecto
                    $marcaT = Carbon::now()->format('Y-m-d H:i:s');
                }


                // Obtener los almacenes del producto
                $warehouses = $order['product']['warehouses'];

                // Llamar a la función getWarehouseAddress y pasarle los datos de almacén
                $prov_origen = $this->getWarehouseProv($warehouses);
                // error_log("Remitente product provincia: $prov_origen");

                $orderData = json_decode($order, true);
                $prov_destiny = $orderData["pedido_carrier"][0]["city_external"]['id_provincia'];
                $city_destiny = $orderData["pedido_carrier"][0]["city_external"]['id'];

                // error_log("prov_destiny: $prov_destiny");
                // error_log("city_destiny: $city_destiny");

                $carrierExternal = CarriersExternal::where('id', 1)->first();
                // error_log("carrierExternal: $carrierExternal");

                $status_array = json_decode($carrierExternal->status, true);

                // Decodificar la cadena JSON en un arreglo asociativo
                $costs = json_decode($carrierExternal->costs, true);

                // Acceder a los valores de costs
                $costo_seguro = $costs['costo_seguro'];

                $city_type = CarrierCoverage::where('id_carrier', 1)->where('id_coverage', $city_destiny)->first();
                $coverage_type = $city_type['type'];

                $orderid = $orderData['id'];
                $nombreComercial = $order->users[0]->vendedores[0]->nombre_comercial;
                $codigo_order = $nombreComercial . "-" . $order->numero_orden;
                // error_log("codigo_order: $codigo_order");

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
                            $iva = 0.15; //15%
                            $costo_easy = 2.3;
                            $commentHist = "";

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

                                    //reduccion del stock
                                    $variants = json_decode($orderData['variant_details'], true);

                                    $idComercial = $orderData['id_comercial'];
                                    $responses = [];

                                    $allow = true;

                                    $historyOrder = StockHistory::where('pedidos_shopify_id', $order->id)
                                        ->latest('id')
                                        ->first();

                                    // error_log("historyOrder: $historyOrder");
                                    if ($historyOrder) {
                                        // El registro más reciente existe
                                        if ($historyOrder->type == 0) {
                                            $allow = false;
                                            error_log("$order->id $historyOrder->type YA existe");
                                        } else {
                                            $allow = true;
                                        }
                                    }

                                    if ($allow) {

                                        if ($variants != null) {
                                            foreach ($variants as $variant) {
                                                $quantity = $variant['quantity'];
                                                $skuProduct = $variant['sku'];
                                                error_log("skuProduct: $skuProduct");

                                                if ($skuProduct != "" || $skuProduct != null) {

                                                    $onlySku = 0;
                                                    $productIdFromSKU = 0;

                                                    $pattern = '/^[a-zA-Z0-9]+C\d+$/';

                                                    if (preg_match($pattern, $skuProduct) && strpos($skuProduct, ' ') === false) {

                                                        $type = 0;
                                                        $productapi = new ProductAPIController();

                                                        $result = $productapi->splitSku($skuProduct);

                                                        $onlySku = $result['sku'];
                                                        $productIdFromSKU = $result['id'];
                                                        $reserveController = new ReserveAPIController();

                                                        $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);
                                                        $searchResult = json_decode($response->getContent());

                                                        if ($searchResult && $searchResult->response) {
                                                            $reserve = $searchResult->reserve;
                                                            $previous_stock = $reserve->stock;
                                                            if ($type == 0 && $quantity > $reserve->stock) {
                                                                $responses[] = ['message' => 'No Dispone de Stock en la Reserva'];
                                                                continue;
                                                            }

                                                            // Actualizar el stock
                                                            $reserve->stock -= $quantity;

                                                            // Assuming you have a 'Reserve' model that you want to save after updating
                                                            $reserveModel = Reserve::find($reserve->id);
                                                            if ($reserveModel) {
                                                                $reserveModel->stock = $reserve->stock;
                                                                $reserveModel->save();
                                                            }

                                                            $createHistory = new StockHistory();
                                                            $createHistory->product_id = $productIdFromSKU;
                                                            $createHistory->variant_sku = $onlySku;
                                                            $createHistory->type = 0;
                                                            $createHistory->date = $currentDateTime;
                                                            $createHistory->units = $quantity;
                                                            $createHistory->last_stock_reserve = $previous_stock;
                                                            $createHistory->current_stock_reserve = $reserve->stock;
                                                            $createHistory->pedidos_shopify_id = $order->id;
                                                            $createHistory->description = "Reducción de stock Reserva Pedido ENVIADO $codigo_order";
                                                            $createHistory->save();

                                                            $responses[] = ['message' => 'Stock actualizado con éxito', 'reserve' => $reserveModel];
                                                        } else {
                                                            // Encuentra el producto por su SKU.
                                                            $product = Product::find($productIdFromSKU);

                                                            if ($product === null) {
                                                                return null;
                                                            }

                                                            if ($product) {
                                                                $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);
                                                                $currentDateTime = date('Y-m-d H:i:s');

                                                                $createHistory = new StockHistory();
                                                                $createHistory->product_id = $productIdFromSKU;
                                                                $createHistory->variant_sku = $onlySku;
                                                                $createHistory->type = $type;
                                                                $createHistory->date = $currentDateTime;
                                                                $createHistory->units = $quantity;
                                                                $createHistory->last_stock = $product->stock + $quantity;
                                                                $createHistory->current_stock = $product->stock;
                                                                $createHistory->pedidos_shopify_id = $order->id;
                                                                $createHistory->description = "Reducción de stock General Pedido ENVIADO $codigo_order";

                                                                $createHistory->save();
                                                            } else {
                                                                $responses[] = ['message' => 'Product not found'];
                                                                continue;
                                                            }
                                                        }
                                                    } else {
                                                        // $responses[] = "$skuProduct|4|0|0";
                                                        error_log("El formato de skuProduct no es válido o contiene espacios.\n");
                                                    }
                                                } else {
                                                    error_log("Es vacio o null skuProductVariant: $skuProduct");
                                                }
                                            }
                                        }
                                    }
                                    //
                                }
                            } else if ($key == "status") {


                                if ($name_local == "ENTREGADO") {

                                    //TRANSACCIONES
                                    //paymentOrderDelivered
                                    $transaccionSellerPrevious = Transaccion::where('id_origen', $orderid)
                                        ->get();

                                    if ($transaccionSellerPrevious->isNotEmpty()) {
                                        // Obtener el último registro de transacción
                                        $lastTransaction = $transaccionSellerPrevious->last();
                                        // error_log($lastTransaction);
                                        error_log("$lastTransaction->origen");

                                        if ($lastTransaction->origen !== 'reembolso' && $lastTransaction->origen !== 'envio') {
                                            // return response()->json(["res" => "Transacciones ya Registradas"]);
                                            return response()->json(['message' => "Error, Transacciones ya Registradas."], 400);
                                        }
                                    }

                                    error_log("Registro de costos");

                                    $order->status = $name_local;
                                    $order->fecha_entrega = $date;
                                    $order->status_last_modified_at = $currentDateTime;
                                    // $order->status_last_modified_by = $idUser;

                                    //updt
                                    $deliveryPrice = 0;
                                    if ($prov_destiny == $prov_origen) {
                                        error_log("Provincial");
                                        if ($coverage_type == "Normal") {
                                            $deliveryPrice = (float)$costs['normal1'];
                                            error_log("normal1: $deliveryPrice");
                                        } else {
                                            $deliveryPrice = (float)$costs['especial1'];
                                            error_log("especial1: $deliveryPrice");
                                        }
                                    } else {
                                        error_log("Nacional");
                                        if ($coverage_type == "Normal") {
                                            $deliveryPrice = (float)$costs['normal2'];
                                            error_log("normal2: $deliveryPrice");
                                        } else {
                                            $deliveryPrice = (float)$costs['especial2'];
                                            error_log("especial2: $deliveryPrice");
                                        }
                                    }

                                    $deliveryPrice = $deliveryPrice + ($deliveryPrice * $iva);
                                    $deliveryPrice = round($deliveryPrice, 2);

                                    error_log("after type + iva: $deliveryPrice");

                                    $costo_seguro = (((float)$orderData['precio_total']) * ((float)$costs['costo_seguro'])) / 100;
                                    $costo_seguro = round($costo_seguro, 2);
                                    $costo_seguro =  $costo_seguro + ($costo_seguro * $iva);
                                    $costo_seguro = round($costo_seguro, 2);

                                    error_log("costo_seguro: $costo_seguro");

                                    $deliveryPrice += $costo_seguro;
                                    error_log("after costo_seguro: $deliveryPrice");
                                    $costo_recaudo = 0;
                                    //como si entrego add recaudo
                                    if ($orderData['recaudo'] == 1) {
                                        if (((float)$orderData['precio_total']) <= ((float)$costs['costo_recaudo']['max_price'])) {
                                            $base = round(((float)$costs['costo_recaudo']['base']), 2);
                                            $base = $base + ($base * $iva);
                                            $base = round($base, 2);
                                            error_log("costo base: $base");
                                            $costo_recaudo = $base;
                                        } else {
                                            $incremental = (((float)$orderData['precio_total']) * ((float)$costs['costo_recaudo']['incremental'])) / 100;
                                            $incremental = round($incremental, 2);
                                            $incremental = $incremental + ($incremental * $iva);
                                            $incremental = round($incremental, 2);
                                            error_log("costo incremental: $incremental");
                                            $costo_recaudo = $incremental;
                                        }
                                    }
                                    $deliveryPrice += $costo_recaudo;

                                    $deliveryPrice = round($deliveryPrice, 2);
                                    $deliveryPriceSeller = $deliveryPrice + $costo_easy;
                                    // $deliveryPriceSeller = $deliveryPriceSeller + ($deliveryPriceSeller * $iva);
                                    $deliveryPriceSeller = round($deliveryPriceSeller, 2);


                                    error_log("costo entrega after recaudo: $deliveryPrice");
                                    error_log("costo deliveryPriceSeller: $deliveryPriceSeller");
                                    $order->costo_transportadora = strval($deliveryPrice);
                                    $order->costo_envio = strval($deliveryPriceSeller);

                                    $variants = json_decode($orderData['variant_details'], true);

                                    // /*
                                    error_log("Transaccion nueva");
                                    //transactions
                                    $SellerCreditFinalValue = $this->updateProductAndProviderBalanceInt(
                                        $variants,
                                        $orderData['precio_total'],
                                        1,  //cambiar
                                        $orderid,
                                        "ENTREGADO",
                                        $codigo_order,
                                    );


                                    if (isset($SellerCreditFinalValue['value_product_warehouse']) && $SellerCreditFinalValue['value_product_warehouse'] !== null) {
                                        $order->value_product_warehouse = $SellerCreditFinalValue['value_product_warehouse'];
                                    }

                                    $vendedor = Vendedore::where('id_master', $order->id_comercial)->first();
                                    if ($vendedor->referer != null) {
                                        $vendedorPrincipal = Vendedore::where('id_master', $vendedor->referer)->first();
                                        if ($vendedorPrincipal->referer_cost != 0) {
                                            $order->value_referer = $vendedorPrincipal->referer_cost;
                                        }
                                    }

                                    // $order->save(); //esta al final so...
                                    error_log("Registro de credit por pedido entregado");

                                    $comentarioCredit = 'Recaudo  de valor por pedido ' . $order->status;

                                    $monto = 0;
                                    if ($SellerCreditFinalValue['total'] != null) {
                                        $monto = $SellerCreditFinalValue['total'];
                                    }

                                    // ! parte uno de la transaccion global
                                    $newTransactionGlobal = new TransaccionGlobal();
                                    $previousValue = 0;
                                    // ! ****************************************

                                    $this->CreditInt(
                                        $order->id_comercial,
                                        $monto,
                                        $orderid,
                                        'recaudo',
                                        $codigo_order,
                                        $comentarioCredit,
                                        1   //cambiar
                                    );

                                    error_log("Registro de Debit por producto y por costo_envio");

                                    $productValues = [];
                                    $sumatoria = 0;
                                    // !*********
                                    if (!empty($SellerCreditFinalValue['valor_producto'])) {
                                        $productValues = $SellerCreditFinalValue['valor_producto'];
                                        // No necesitas codificar a JSON si ya es un array
                                        // $decodevalues = json_encode($productValues);
                                        error_log(print_r($productValues, true));
                                        foreach ($productValues as $valueProduct) {
                                            // Asumiendo que $valueProduct es un número, podrías necesitar validar o formatearlo según sea necesario
                                            $comentarioDebit = 'Costo de valor de Producto en Bodega ' . $order->status;
                                            $this->DebitInt(
                                                $order->id_comercial,
                                                $valueProduct,
                                                $orderid,
                                                'valor producto bodega',
                                                $codigo_order,
                                                $comentarioDebit,
                                                1   //cambiar
                                            );

                                            $sumatoria += $valueProduct;
                                        }

                                        $newTransactionGlobal->provider_cost = -$sumatoria;
                                    } else {
                                        $newTransactionGlobal->provider_cost = 0;
                                    }

                                    // ! parte dos de la transaccion global


                                    $this->DebitInt(
                                        $order->id_comercial,
                                        strval($deliveryPriceSeller),
                                        $orderid,
                                        'envio',
                                        $codigo_order,
                                        'Costo de envio por pedido ENTREGADO',
                                        1   //cambiar
                                    );

                                    $vendedor = Vendedore::where("id_master", $order->id_comercial)->get();
                                    $startDateFormatted = new DateTime();

                                    if ($vendedor[0]->referer != null) {
                                        $refered = Vendedore::where('id_master', $vendedor[0]->referer)->get();
                                        $vendedorId = $vendedor[0]->referer;
                                        $user = UpUser::where("id", $vendedorId)->with('vendedores')->first();
                                        $vendedor = $user['vendedores'][0];
                                        $saldo = $vendedor->saldo;
                                        $nuevoSaldo = $saldo + $refered[0]->referer_cost;
                                        $vendedor->saldo = $nuevoSaldo;

                                        // ! parte tres de la transaccion global

                                        $newTrans = new Transaccion();

                                        $newTrans->tipo = "credit";
                                        $newTrans->monto = $refered[0]->referer_cost;
                                        $newTrans->valor_actual = $nuevoSaldo;
                                        $newTrans->valor_anterior = $saldo;
                                        $newTrans->marca_de_tiempo = $startDateFormatted;
                                        $newTrans->id_origen = $orderid;
                                        $newTrans->codigo = $codigo_order;

                                        $newTrans->origen = "referido";
                                        $newTrans->comentario = "comision por referido";

                                        $newTrans->id_vendedor = $vendedorId;
                                        $newTrans->state = 1;
                                        $newTrans->generated_by = 1; //cambiar
                                        $newTrans->save();

                                        $vendedorencontrado = Vendedore::findOrFail($user['vendedores'][0]['id']);
                                        // Actualiza el saldo del vendedor
                                        $vendedorencontrado->saldo = $nuevoSaldo;
                                        $vendedorencontrado->save();

                                        // ! generacion de la transacicon_global para el referenciado
                                        // Obtener la transacción global previa
                                        $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $refered[0]->id_master)
                                            ->orderBy('id', 'desc')
                                            ->first();

                                        $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;



                                        $newTransactionGlobalReferer = new TransaccionGlobal();

                                        if ($existingTransaction == null) {

                                            $newTransactionGlobalReferer->admission_date = $marcaT;
                                            $newTransactionGlobalReferer->delivery_date = now()->format('Y-m-d');
                                            $newTransactionGlobalReferer->status = $order->status;
                                            // 'return_state' => $pedido->estado_devolucion,
                                            $newTransactionGlobalReferer->return_state = null;
                                            $newTransactionGlobalReferer->id_order = $order->id;
                                            $newTransactionGlobalReferer->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                                            $newTransactionGlobalReferer->origin = 'Referenciado';
                                            $newTransactionGlobalReferer->withdrawal_price = 0; // Ajusta según necesites
                                            $newTransactionGlobalReferer->value_order = 0; // Ajusta según necesites
                                            $newTransactionGlobalReferer->return_cost = 0;
                                            $newTransactionGlobalReferer->delivery_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobalReferer->notdelivery_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobalReferer->provider_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobalReferer->referer_cost = $refered[0]->referer_cost; // Ajusta según necesites
                                            $newTransactionGlobalReferer->total_transaction =
                                                $newTransactionGlobalReferer->value_order +
                                                $newTransactionGlobalReferer->return_cost +
                                                $newTransactionGlobalReferer->delivery_cost +
                                                $newTransactionGlobalReferer->notdelivery_cost +
                                                $newTransactionGlobalReferer->provider_cost +
                                                $newTransactionGlobalReferer->referer_cost;
                                            $newTransactionGlobalReferer->previous_value = $previousValue;
                                            $newTransactionGlobalReferer->current_value = $previousValue + $newTransactionGlobalReferer->total_transaction;
                                            $newTransactionGlobalReferer->state = true;
                                            // $newTransactionGlobalReferer->id_seller = $pedido->users[0]->vendedores[0]->id_master;
                                            $newTransactionGlobalReferer->id_seller = $refered[0]->id_master;
                                            // $newTransactionGlobalReferer->internal_transportation_cost = -$costoTransportadora; // Ajusta según necesites
                                            $newTransactionGlobalReferer->internal_transportation_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobalReferer->external_transportation_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobalReferer->external_return_cost = 0;
                                            $newTransactionGlobalReferer->save();
                                        }

                                        // ! **************************************
                                    }
                                    // */
                                    //
                                    $order->status = $name_local;
                                    $order->status_last_modified_at = $currentDateTime;




                                    $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                                        ->where('id_seller', $order->users[0]->vendedores[0]->id_master)

                                        ->first();

                                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                                        ->orderBy('id', 'desc')
                                        ->first();

                                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;


                                    // $newTransactionGlobal = new TransaccionGlobal();

                                    if ($existingTransaction == null) {

                                        $newTransactionGlobal->admission_date = $marcaT;
                                        $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                                        // $newTransactionGlobal->status = $pedido->status;
                                        $newTransactionGlobal->status = 'ENTREGADO';
                                        // 'return_state' => $pedido->estado_devolucion,
                                        $newTransactionGlobal->return_state = null;
                                        $newTransactionGlobal->id_order = $order->id;
                                        $newTransactionGlobal->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                                        $newTransactionGlobal->origin = 'Pedido ' . $order->status;
                                        $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                                        $newTransactionGlobal->value_order = $order->precio_total; // Ajusta según necesites
                                        $newTransactionGlobal->return_cost = 0;
                                        $newTransactionGlobal->delivery_cost = -$order->costo_envio; // Ajusta según necesites
                                        $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                                        // $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                                        $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                                        $newTransactionGlobal->total_transaction =
                                            $newTransactionGlobal->value_order +
                                            $newTransactionGlobal->return_cost +
                                            $newTransactionGlobal->delivery_cost +
                                            $newTransactionGlobal->notdelivery_cost +
                                            $newTransactionGlobal->provider_cost +
                                            $newTransactionGlobal->referer_cost;
                                        $newTransactionGlobal->previous_value = $previousValue;
                                        $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                                        $newTransactionGlobal->state = true;
                                        $newTransactionGlobal->id_seller = $order->users[0]->vendedores[0]->id_master;
                                        $newTransactionGlobal->internal_transportation_cost = 0; // Ajusta según necesites
                                        $newTransactionGlobal->external_transportation_cost = -strval($deliveryPrice); // Ajusta según necesites
                                        $newTransactionGlobal->external_return_cost = 0;
                                        $newTransactionGlobal->save();
                                    }
                                } else if ($name_local == "NOVEDAD") {
                                    //
                                    if ($id_gestion == 2 || $id_gestion == "") {
                                        # code...
                                        $commentText = "";
                                        if ($id_gestion == 2) {
                                            // error_log("Nueva nov. creada la solucion");
                                            $commentText = $no_novedad . "-" . $no_gestion . "-" . $nota;
                                        } else {
                                            $commentText = $no_novedad;
                                            // error_log("Nueva nov. creada");
                                        }
                                        // error_log("$commentText");
                                        $commentHist = $commentText;

                                        $novedades = NovedadesPedidosShopifyLink::where('pedidos_shopify_id', $order->id)->get();
                                        $novedades_try = $novedades->isEmpty() ? 0 : $novedades->count();

                                        $novedad = new Novedade();
                                        $novedad->m_t_novedad = $currentDateTimeText;
                                        $novedad->try = $novedades_try + 1;
                                        $novedad->url_image = $path; //como manejar para mostrar
                                        // $novedad->comment = $no_novedad;
                                        $novedad->comment =  $commentText;
                                        // $novedad->external_id = $id_novedad;
                                        if ($id_gestion == "") {
                                            $novedad->external_id = $id_novedad;
                                        } else {
                                            $novedad->external_id = 1300;
                                        }
                                        $novedad->published_at = $currentDateTime;
                                        $novedad->save();

                                        $novedad_pedido = new NovedadesPedidosShopifyLink();
                                        $novedad_pedido->novedad_id = $novedad->id;
                                        $novedad_pedido->pedidos_shopify_id = $order->id;
                                        $novedad_pedido->novedad_order = $novedades_try + 1;
                                        $novedad_pedido->save();

                                        $order->status = $name_local;
                                        $order->status_last_modified_at = $currentDateTime;
                                    } else if ($id_gestion == 1) {
                                        // error_log("se acepto la solucion");
                                    }
                                } else {
                                    $order->status = $name_local;
                                    $order->status_last_modified_at = $currentDateTime;
                                }
                                // $order->status = $name_local;
                                // $order->status_last_modified_at = $currentDateTime;
                                // $order->status_last_modified_by = $idUser;

                            } else if ($key == "estado_devolucion") {
                                //solo se puede poner en devolucion si se encuentra en NOVEDAD
                                if ($order->status == "NOVEDAD") {
                                    //
                                    if ($name_local == "EN BODEGA") { //from logistic
                                        $order->estado_devolucion = $name_local;
                                        $order->dl = $name_local;
                                        $order->marca_t_d_t = $currentDateTimeText;
                                        // $order->received_by = $idUser;

                                        //update stock de producto
                                        //caso de producto con dueño externo vuelve a reserva
                                        $seller_owned = $order['product']['seller_owned'];
                                        error_log("$seller_owned");

                                        $historyOrder = StockHistory::where('pedidos_shopify_id', $order->id)
                                            ->latest('id')
                                            ->first();
                                        $allow = true;

                                        // error_log("$historyOrder: $historyOrder");
                                        if ($historyOrder) {
                                            // El registro más reciente existe
                                            if ($historyOrder->type == 1) {
                                                $allow = false;
                                                error_log("$order->id $historyOrder->type YA existe");
                                            } else {
                                                $allow = true;
                                            }
                                        }

                                        if ($allow) {
                                            if ($seller_owned != 0 || $seller_owned != null) {
                                                error_log("IS seller_owned: $seller_owned");

                                                //reduccion del stock
                                                $variants = json_decode($orderData['variant_details'], true);

                                                $idComercial = $orderData['id_comercial'];
                                                $responses = [];
                                                if ($variants != null) {
                                                    foreach ($variants as $variant) {
                                                        $quantity = $variant['quantity'];
                                                        $skuProduct = $variant['sku'];
                                                        $type = 1;
                                                        $productapi = new ProductAPIController();

                                                        $result = $productapi->splitSku($skuProduct);

                                                        $onlySku = $result['sku'];
                                                        $productIdFromSKU = $result['id'];

                                                        $reserveController = new ReserveAPIController();

                                                        $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);
                                                        $searchResult = json_decode($response->getContent());

                                                        if ($searchResult && $searchResult->response) {
                                                            $reserve = $searchResult->reserve;
                                                            $previous_stock = $reserve->stock;
                                                            if ($type == 0 && $quantity > $reserve->stock) {
                                                                $responses[] = ['message' => 'No Dispone de Stock en la Reserva'];
                                                                continue;
                                                            }

                                                            // Actualizar el stock
                                                            $reserve->stock += $quantity;

                                                            // Assuming you have a 'Reserve' model that you want to save after updating
                                                            $reserveModel = Reserve::find($reserve->id);
                                                            if ($reserveModel) {
                                                                $reserveModel->stock = $reserve->stock;
                                                                $reserveModel->save();
                                                            }

                                                            $createHistory = new StockHistory();
                                                            $createHistory->product_id = $productIdFromSKU;
                                                            $createHistory->variant_sku = $onlySku;
                                                            $createHistory->type = 1;
                                                            $createHistory->date = $currentDateTime;
                                                            $createHistory->units = $quantity;
                                                            $createHistory->last_stock_reserve = $previous_stock;
                                                            $createHistory->current_stock_reserve = $reserve->stock;
                                                            $createHistory->pedidos_shopify_id = $order->id;
                                                            $createHistory->description = "Incremento de stock Reserva Pedido EN BODEGA $codigo_order";
                                                            $createHistory->save();

                                                            $responses[] = ['message' => 'Stock actualizado con éxito', 'reserve' => $reserveModel];
                                                        } else {
                                                            error_log("No found producto: $productIdFromSKU-$onlySku");
                                                        }
                                                    }
                                                }
                                            } else {
                                                //producto normal: reducir stock general
                                                error_log("stock general");
                                                $variants = json_decode($orderData['variant_details'], true);


                                                if ($variants != null) {
                                                    foreach ($variants as $variant) {
                                                        $quantity = $variant['quantity'];
                                                        $skuProduct = $variant['sku'];
                                                        $type = 1;

                                                        $productapi = new ProductAPIController();

                                                        $result = $productapi->splitSku($skuProduct);

                                                        $onlySku = $result['sku'];
                                                        $productIdFromSKU = $result['id'];

                                                        $product = Product::find($productIdFromSKU);

                                                        $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);


                                                        $createHistory = new StockHistory();
                                                        $createHistory->product_id = $productIdFromSKU;
                                                        $createHistory->variant_sku = $onlySku;
                                                        $createHistory->type = $type;
                                                        $createHistory->date = $currentDateTime;
                                                        $createHistory->units = $quantity;
                                                        $createHistory->last_stock = $product->stock - $quantity;
                                                        $createHistory->current_stock = $product->stock;
                                                        $createHistory->pedidos_shopify_id = $order->id;
                                                        $createHistory->description = "Incremento de stock General Pedido EN BODEGA $codigo_order";

                                                        $createHistory->save();
                                                    }
                                                }
                                            }
                                        }
                                    } else if ($name_local == "ENTREGADO EN OFICINA") {
                                        $order->estado_devolucion = $name_local;
                                        $order->dt = $name_local;
                                        $order->marca_t_d_t = $currentDateTimeText;
                                        // $order->received_by = $idUser;
                                    } else if ($name_local == "DEVOLUCION EN RUTA") {
                                        $order->estado_devolucion = $name_local;
                                        $order->dt = $name_local;
                                        $order->marca_t_d_t = $currentDateTimeText;
                                        // $order->received_by = $idUser;
                                    }

                                    // ! integracion

                                    // Verificar si ya existe una transacción global para este pedido y vendedor
                                    $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                                        ->where('id_seller', $order->users[0]->vendedores[0]->id_master)

                                        ->first();

                                    // Obtener la transacción global previa
                                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                                        ->orderBy('id', 'desc')
                                        ->first();


                                    // ! aqui deberia ir otra transaccion global pero seria (NOVEDAD, est_devolucion != pendiente para las externas)

                                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                                    error_log("-----------");
                                    error_log($existingTransaction);
                                    error_log("-----------");

                                    if ($existingTransaction != null) {
                                        $existingTransaction->return_state = $order->estado_devolucion;
                                        $existingTransaction->save();
                                    }

                                    if ($order->costo_devolucion == null) {
                                        //
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
                                        $deliveryPrice = $deliveryPrice + ($deliveryPrice * $iva);
                                        $deliveryPrice = round($deliveryPrice, 2);

                                        error_log("after type + iva: $deliveryPrice");

                                        $costo_seguro = (((float)$orderData['precio_total']) * ((float)$costs['costo_seguro'])) / 100;
                                        $costo_seguro = round($costo_seguro, 2);
                                        $costo_seguro =  $costo_seguro + ($costo_seguro * $iva);
                                        $costo_seguro = round($costo_seguro, 2);

                                        error_log("costo_seguro: $costo_seguro");

                                        $deliveryPrice += $costo_seguro;
                                        error_log("after costo_seguro: $deliveryPrice");
                                        $costo_recaudo = 0;

                                        $deliveryPrice += $costo_recaudo;
                                        $deliveryPrice = round($deliveryPrice, 2);
                                        $deliveryPriceSeller = $deliveryPrice + $costo_easy;
                                        // $deliveryPriceSeller = $deliveryPriceSeller + ($deliveryPriceSeller * $iva);
                                        $deliveryPriceSeller = round($deliveryPriceSeller, 2);


                                        error_log("costo entrega after recaudo: $deliveryPrice");
                                        error_log("costo deliveryPriceSeller: $deliveryPriceSeller");

                                        $order->costo_transportadora = strval($deliveryPrice);
                                        $order->costo_envio = strval($deliveryPriceSeller);


                                        $refundpercentage = $costs['costo_devolucion'];
                                        $refound_seller = ($deliveryPriceSeller * ($refundpercentage)) / 100;
                                        $refound_transp = ($deliveryPrice * ($refundpercentage)) / 100;

                                        $order->costo_devolucion = round(((float)$refound_seller), 2);
                                        // $order->cost_refound_external = round(((float)$refound_transp), 2);
                                        $pedidoCarrier = PedidosShopifiesCarrierExternalLink::where('pedidos_shopify_id', $orderid)->first();
                                        $pedidoCarrier->cost_refound_external = round(((float)$refound_transp), 2);
                                        $pedidoCarrier->save();
                                        /*
                                        $comentarioDebitEnvio = 'Costo de envio por pedido en ' . $order->status . " y " . $order->estado_devolucion;
                                        /*
                                        $comentarioDebitEnvio = 'Costo de envio por pedido en ' . $order->status . " y " . $order->estado_devolucion;
                                        $this->DebitInt(
                                            $order->id_comercial,
                                            $deliveryPriceSeller,
                                            $orderid,
                                            'envio',
                                            $codigo_order,
                                            $comentarioDebitEnvio,
                                            1   //cambiar
                                        );

                                        $comentarioDebitDev = 'Costo de devolución por pedido en ' . $order->status . " y " . $order->estado_devolucion;
                                        $this->DebitInt(
                                            $order->id_comercial,
                                            round(((float)$refound_seller), 2),
                                            $orderid,
                                            'devolucion',
                                            $codigo_order,
                                            $comentarioDebitDev,
                                            1   //cambiar
                                        );
                                        */


                                        if ($existingTransaction == null) {

                                            error_log($existingTransaction);

                                            $newTransactionGlobal = new TransaccionGlobal();

                                            $newTransactionGlobal->admission_date = $marcaT;
                                            $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                                            $newTransactionGlobal->status = "NOVEDAD";
                                            $newTransactionGlobal->return_state = $order->estado_devolucion;
                                            // $newTransactionGlobal->return_state = null;
                                            $newTransactionGlobal->id_order = $order->id;
                                            $newTransactionGlobal->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                                            $newTransactionGlobal->origin = 'Pedido ' . 'NOVEDAD';
                                            $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                                            $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                                            $newTransactionGlobal->return_cost = -$order->costo_devolucion;
                                            $newTransactionGlobal->delivery_cost = -$order->costo_envio; // Ajusta según necesites
                                            $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobal->total_transaction =
                                                $newTransactionGlobal->value_order +
                                                $newTransactionGlobal->return_cost +
                                                $newTransactionGlobal->delivery_cost +
                                                $newTransactionGlobal->notdelivery_cost +
                                                $newTransactionGlobal->provider_cost +
                                                $newTransactionGlobal->referer_cost;
                                            $newTransactionGlobal->previous_value = $previousValue;
                                            $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                                            $newTransactionGlobal->state = true;
                                            $newTransactionGlobal->id_seller = $order->users[0]->vendedores[0]->id_master;
                                            $newTransactionGlobal->internal_transportation_cost = 0; // Ajusta según necesites
                                            $newTransactionGlobal->external_transportation_cost =  -$order->costo_transportadora; // Ajusta según necesites
                                            $newTransactionGlobal->external_return_cost = -round(((float)$refound_transp), 2);
                                            $newTransactionGlobal->save();
                                        }
                                    } else {
                                        //
                                        error_log("ya existe registro costo_devolucion");
                                    }
                                } else {
                                    return response()->json(['message' => "Error, Order must be in NOVEDAD."], 400);
                                }
                            }

                            //new column
                            $newHistory = [
                                "area" => $key,
                                "status" => $name_local,
                                "timestap" => date('Y-m-d H:i:s'),
                                "comment" => $commentHist,
                                "path" => $path,
                                "generated_by" => "1_GTM"
                            ];

                            if ($order->status_history === null || $order->status_history === '[]') {
                                $order->status_history = json_encode([$newHistory]);
                            } else {
                                $existingHistory = json_decode($order->status_history, true);

                                $existingHistory[] = $newHistory;

                                $order->status_history = json_encode($existingHistory);
                            }

                            $order->save();

                            break;
                        }
                    }

                    DB::commit();
                    return response()->json(['message' => 'Order updated successfully.'], 200);
                } catch (\Exception $e) {
                    DB::rollback();
                    error_log("ErrorRequestUpdateState: $e ");
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
                    $name = $warehouseJson['id_provincia'];
                    // error_log("prov: $name, city: " . $warehouseJson['city']);
                } else {
                    $lastWarehouse = end($warehousesList);
                    $name = $lastWarehouse['id_provincia'];
                    // error_log("prov: $name, city: " . $warehouseJson['city']);
                }
            } else {
                error_log("El elemento de la lista no es un mapa válido: ");
            }
        }
        return $name;
    }

    function getWarehouseCity($warehousesJson)
    {
        $name = "";

        $warehousesList = json_decode($warehousesJson, true);

        foreach ($warehousesList as $warehouseJson) {
            if (is_array($warehouseJson)) {
                if (count($warehousesList) == 1) {
                    $name = $warehouseJson['id_city'];
                } else {
                    $lastWarehouse = end($warehousesList);
                    $name = $lastWarehouse['id_city'];
                }
            } else {
                error_log("El elemento de la lista no es un mapa válido: ");
            }
        }
        return $name;
    }

    public function updateProductAndProviderBalanceInt($variants, $totalPrice, $generated_by, $id_origin, $orderStatus, $codeOrder)
    {
        DB::beginTransaction();
        try {
            $responses = [];
            $totalValueProductWarehouse = 0;


            foreach ($variants as $variant) {
                $quantity = $variant['quantity'];
                $skuProduct = $variant['sku']; // Ahora el SKU viene dentro de cada variante

                // foreach ($variant_details as $variant){}
                if ($skuProduct == null) {
                    $skuProduct = "UKNOWNPC0";
                }
                $productId = substr($skuProduct, strrpos($skuProduct, 'C') + 1);
                $firstPart = substr($skuProduct, 0, strrpos($skuProduct, 'C'));

                // Log::info('sku', [$firstPart]);

                // Buscar el producto por ID
                $product = Product::with('warehouse')->find($productId);

                if ($product === null) {
                    DB::commit();
                    return ["total" => null, "valor_producto" => null, "value_product_warehouse" => null, "error" => "Product Not Found!"];
                }

                error_log("ak-> $id_origin");
                error_log("ak-> $codeOrder");


                $providerTransactionPrevious = ProviderTransaction::where('transaction_type', 'Pago Producto')
                    ->where('status', 'ENTREGADO')
                    ->where('origin_id', $id_origin)
                    ->where('origin_code', $codeOrder)
                    ->where('sku_product_reference', $skuProduct)
                    ->first();

                // error_log("ak-> $providerTransactionPrevious");

                $price = 0;

                if (!$providerTransactionPrevious) {
                    $providerId = $product->warehouse->provider_id;
                    $productName = $product->product_name;

                    $price = $product->price;

                    // Log::info('price', [$price]);


                    $amountToDeduct = $price * $quantity;

                    $total = $totalPrice;
                    $diferencia = $amountToDeduct;

                    $totalValueProductWarehouse += $price * $quantity;

                    $provider = Provider::findOrFail($providerId);
                    $provider->saldo += $amountToDeduct;
                    $provider->save();


                    $providerTransaction = new ProviderTransaction([
                        'transaction_type' => 'Pago Producto',
                        'amount' => $amountToDeduct,
                        'previous_value' => $provider->saldo - $amountToDeduct,
                        'current_value' => $provider->saldo,
                        'timestamp' => now(),
                        'origin_id' => $id_origin,
                        'origin_code' => $codeOrder,
                        // 'origin_code' => $skuProduct,
                        'provider_id' => $providerId,
                        'comment' => $productName,
                        'generated_by' => $generated_by,
                        'status' => $orderStatus,
                        'description' => "Valor por guia ENTREGADA",
                        'sku_product_reference' => $skuProduct
                    ]);
                    $providerTransaction->save();
                    $responses[] = $diferencia;
                }
            }
            DB::commit(); // Confirmar los cambios
            return ["total" => $total, "valor_producto" => $responses, "value_product_warehouse" => $totalValueProductWarehouse, "error" => null];
        } catch (\Exception $e) {
            DB::rollback();
            return ["total" => null, "valor_producto" => null, "value_product_warehouse" => null, "error" => $e->getMessage()];
        }
    }

    public function CreditInt($vendedorId, $monto, $idOrigen, $origen, $codigo, $comentario, $generated_by)
    {
        $startDateFormatted = new DateTime();
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y H:i', $startDate)->format('Y-m-d H:i');
        $tipo = "credit";

        $user = UpUser::where("id", $vendedorId)->with('vendedores')->first();
        $vendedor = $user['vendedores'][0];
        $saldo = $vendedor->saldo;
        $nuevoSaldo = $saldo + $monto;
        $vendedor->saldo = $nuevoSaldo;

        $newTrans = new Transaccion();

        $newTrans->tipo = $tipo;
        $newTrans->monto = $monto;
        $newTrans->valor_anterior = $saldo;

        $newTrans->valor_actual = $nuevoSaldo;
        $newTrans->marca_de_tiempo = $startDateFormatted;
        $newTrans->id_origen = $idOrigen;
        $newTrans->codigo = $codigo;

        $newTrans->origen = $origen;
        $newTrans->comentario = $comentario;
        $newTrans->id_vendedor = $vendedorId;
        $newTrans->state = 1;
        $newTrans->generated_by = $generated_by;
        $newTrans->save();

        $vendedorencontrado = Vendedore::findOrFail($user['vendedores'][0]['id']);
        // Actualiza el saldo del vendedor
        $vendedorencontrado->saldo = $nuevoSaldo;
        $vendedorencontrado->save();

        return response()->json("Monto acreditado");
    }

    public function DebitInt($vendedorId, $monto, $idOrigen, $origen, $codigo, $comentario, $generated_by)
    {
        $startDateFormatted = new DateTime();
        //  $startDateFormatted = Carbon::createFromFormat('j/n/Y H:i', $startDate)->format('Y-m-d H:i');
        $tipo = "debit";

        $user = UpUser::where("id", $vendedorId)->with('vendedores')->first();
        $vendedor = $user['vendedores'][0];
        $saldo = $vendedor->saldo;
        $nuevoSaldo = $saldo - $monto;
        $vendedor->saldo = $nuevoSaldo;


        $newTrans = new Transaccion();

        $newTrans->tipo = $tipo;
        $newTrans->monto = $monto;
        $newTrans->valor_actual = $nuevoSaldo;
        $newTrans->valor_anterior = $saldo;
        $newTrans->marca_de_tiempo = $startDateFormatted;
        $newTrans->id_origen = $idOrigen;
        $newTrans->codigo = $codigo;

        $newTrans->origen = $origen;
        $newTrans->comentario = $comentario;

        $newTrans->id_vendedor = $vendedorId;
        $newTrans->state = 1;
        $newTrans->generated_by = $generated_by;

        $newTrans->save();

        $vendedorencontrado = Vendedore::findOrFail($user['vendedores'][0]['id']);
        // Actualiza el saldo del vendedor
        $vendedorencontrado->saldo = $nuevoSaldo;
        $vendedorencontrado->save();
        return response()->json("Monto debitado");
    }

    public function putSolucionGTM(Request $request)
    {
        error_log("putSolucionGTM");

        $data = $request->all();
        // error_log(json_encode($data));

        $apiUrl = 'https://ec.gintracom.site/web/easy/solucion';

        // Nombre de usuario y contraseña para la autenticación básica
        $username = 'easy';
        $password = 'f7b2d589796f5f209e72d5697026500d';

        $response = Http::withBasicAuth($username, $password)
            ->post($apiUrl, $data);

        if ($response->successful()) {
            // La solicitud fue exitosa
            error_log("La solicitud fue exitosa $response");
            return $response->json(); // Devolver la respuesta JSON de la API externa
        } else {
            // La solicitud falló
            error_log("La solicitud a la API externa falló $response");
            return response()->json(['error' => 'La solicitud a la API externa falló'], $response->status());
        }
    }

    //******************** LAARCOURIER */

    public function getTokenLaar()
    {
        error_log("getTokenLaar");

        $response = Http::post('https://api.laarcourier.com:9727/authenticate', [
            'username' => 'prueba.easy.ecommerce.api',
            'password' => 'J(s{GC]4',
        ]);

        if ($response->successful()) {
            $token = $response->json('token');
            return $token;
        }

        return response()->json(['error' => 'No se pudo obtener el token'], 401);
    }

    public function postOrderLaar(Request $request)
    {
        error_log("postOrderLaar");

        $data = $request->all();
        $apiUrl = 'https://api.laarcourier.com:9727/guias/contado';

        $token = $this->getTokenLaar();

        if (!$token) {
            return response()->json(['error' => 'Token no encontrado'], 401);
        }

        $response = Http::withToken($token)
            ->post($apiUrl, $data);

        if ($response->successful()) {
            error_log("postOrderLaar_successful: $response");
            return $response->json();
        } else {
            $errorMessage = $response->json('Message') ?? 'No se pudo obtener la información';

            error_log("postOrderLaar_error: " . json_encode($response->json()));

            return response()->json(['error' => $errorMessage], $response->status());
        }
    }


    public function authenticate(Request $request)
    {
        $credentials = $request->only('username', 'password');

        // Buscar al usuario por su correo electrónico en la tabla UpUser
        $user = UpUser::where('username', $credentials['username'])->first();

        $upUser = UpUser::with([
            'roles_fronts',
        ])
            ->where('username', $credentials['username'])->first();

        // error_log($upUser);

        if (!$user) {
            error_log("1");
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Validar la contraseña proporcionada por el usuario con el hash almacenado en la base de datos
        if (!Hash::check($credentials['password'], $user->password)) {
            error_log("2");

            return response()->json(['error' => 'Credenciales inválidas'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->blocked == 1 && $user->active == 1) {
            error_log("3");

            return response()->json(['error' => 'Usuario Bloqueado'], Response::HTTP_UNAUTHORIZED);
        }
        if ($user->active == 0) {
            error_log("3");

            return response()->json(['error' => 'Usuario Eliminado'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $isIntegration = collect($user->roles_fronts)->contains('id', 6);

            if ($isIntegration) {
                $ttl = 1440; //24hrs
                // $ttl = 3; //test 3 min
                // Configura el TTL específico para el token
                JWTAuth::factory()->setTTL($ttl);

                // Genera el token
                $token = JWTAuth::fromUser($user);
            } else {
                return response()->json(['error' => 'Acceso denegado: rol no autorizado'], Response::HTTP_FORBIDDEN);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        error_log("usuario logueado>  $user->username ");

        return response()->json([
            'jwt' => $token,
            // 'user' => $user

            'user' => $user->username
        ], Response::HTTP_OK);
        /*
            $credentials = $request->only('username', 'password');

            // Buscar al usuario por su correo electrónico en la tabla UpUser
            $user = IntegrationUser::where('username', $credentials['username'])->first();

            if (!$user) {
                error_log("1");
                return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            // Validar la contraseña proporcionada por el usuario con el hash almacenado en la base de datos
            if (!Hash::check($credentials['password'], $user->password)) {
                error_log("2");

                return response()->json(['error' => 'Credenciales inválidas'], Response::HTTP_UNAUTHORIZED);
            }

            if ($user->active == 0) {
                error_log("3");

                return response()->json(['error' => 'Usuario Eliminado'], Response::HTTP_UNAUTHORIZED);
            }

            try {
                // Indica que el guard a utilizar es integration_user
                $token = JWTAuth::fromUser($user); // Usa fromUser en lugar de attempt

            } catch (JWTException $e) {
                return response()->json(['error' => 'No se pudo crear el token'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            error_log("usuario logueado>  $user->username ");
            return response()->json([
                'jwt' => $token,
                'user' => $user
            ], Response::HTTP_OK);
        */
    }

    public function getLabelLaar(Request $request)
    {
        error_log("getLabelLaar");

        // Obtener los datos del formulario
        $data = $request->all();
        $guia = $data['guia'];

        $apiUrl = 'https://api.laarcourier.com:9727/guias/';

        // Realizar la solicitud POST a la API externa con autenticación básica
        $response = Http::get($apiUrl . $guia . '/etiquetas');


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

    public function getMultiLabelsGeneral(Request $request)
    {
        error_log("getMultiLabelsGeneral");
        try {
            $data = $request->all();
            $ids = $data['ids'];

            $pdfFinal = new Fpdi();

            foreach ($ids as $id) {
                $idCarrier = $id['id_carrier'];
                $guia = $id['guia'];

                if (empty($guia)) {
                    throw new \Exception("Guía no proporcionada.");
                }

                $res = null;

                // Asignar la respuesta dependiendo del id_carrier
                if ($idCarrier == 1) {
                    error_log("gtm: $guia");
                    $res = $this->getLabelGTM($request->merge(['guia' => $guia]));
                } elseif ($idCarrier == 5) {
                    error_log("laar: $guia");
                    $res = $this->getLabelLaar($request->merge(['guia' => $guia]));
                }

                if (!$res) {
                    throw new \Exception("No se pudo obtener la etiqueta para la guía $guia y transportista $idCarrier.");
                }

                $content = $res->getContent();

                // Incorporar la página en el PDF final
                $pdfFinal->setSourceFile(StreamReader::createByString($content));
                $templateId = $pdfFinal->importPage(1); // Importar la primera página del PDF
                $pdfFinal->addPage();
                $pdfFinal->useTemplate($templateId, 0, 0, $pdfFinal->GetPageWidth(), $pdfFinal->GetPageHeight(), true);
            }

            $pdfContent = $pdfFinal->Output('', 'S');

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="pdftotal.pdf"');
        } catch (\Exception $e) {
            error_log("Error: " . $e->getMessage());

            return response()->json(['Error' => $e->getMessage()], 500);
        }
    }


    public function requestUpdateStateLaar(Request $request)
    {
        try {
            // Obtener el usuario autenticado mediante el token
            $user = JWTAuth::parseToken()->authenticate();

            error_log("requestUpdateStateLaar");
            $request_body = file_get_contents('php://input');
            $data = json_decode($request_body, true);
            // error_log(print_r($data, true));

            // Verificar si los campos obligatorios están presentes y no están vacíos
            $required_fields = ['noGuia', 'estadoActualCodigo', 'estadoActual', 'pesoKilos'];
            $missing_fields = [];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $missing_fields[] = $field;
                }
            }

            if (!empty($missing_fields)) {
                $missing_fields_str = implode(', ', $missing_fields);
                $error_message = "Missing required fields: $missing_fields_str";
                error_log($error_message);
                return response()->json(['message' => $error_message], 400);
            } else {
                //
                $guia = $data['noGuia'];
                $estadoCod = $data['estadoActualCodigo'];
                $estadoActual = $data['estadoActual'];
                $pesoKilos = $data['pesoKilos']; //float
                $novedad = $data['novedad'];

                error_log("laar_guia_input: $guia");
                error_log("estadoCod input: $estadoCod");
                error_log("estadoActual input: $estadoActual");

                if (!empty($novedad)) {
                    error_log("Novedad recibida: " . json_encode($novedad));
                } else {
                    error_log("Novedad está vacía o no presente.");
                }

                $order = PedidosShopify::with([
                    'vendor',
                    // 'users.vendedores',
                    'product.warehouses',
                    'pedidoCarrier',
                ])
                    ->whereHas('pedidoCarrier', function ($query) use ($guia) {
                        $query->where('carrier_id', 5)
                            ->where('external_id', $guia);
                    })->first();

                // error_log("pedido: $order");

                $currentDateTime = date('Y-m-d H:i:s');
                $date = now()->format('j/n/Y');
                $currentDateTimeText = date("d/m/Y H:i");

                // ! for transaction_global
                $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                    ->where('id_seller', $order->users[0]->vendedores[0]->id_master)
                    ->first();

                $marcaT = "";
                if (!empty($order->marca_t_i)) {
                    try {
                        $marca_t_i = $order->marca_t_i;

                        // Agregar ceros a las fechas/hora de un solo dígito en el formato 9/9/2024 9:9
                        $marca_t_i = preg_replace_callback('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{1,2})/', function ($matches) {
                            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                            $year = $matches[3];
                            $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
                            $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
                            return "$day/$month/$year $hour:$minute";
                        }, $marca_t_i);

                        // Intentar crear la fecha con el formato esperado
                        $marcaT = Carbon::createFromFormat('d/m/Y H:i', $marca_t_i)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Log::error('Error al convertir marca_t_i: ' . $pedido->marca_t_i . ' - ' . $e->getMessage());
                        // Asignar una fecha por defecto si la conversión falla
                        $marcaT = Carbon::now()->format('Y-m-d H:i:s');
                    }
                } else {
                    // Asignar una fecha por defecto
                    $marcaT = Carbon::now()->format('Y-m-d H:i:s');
                }

                $warehouses = $order['product']['warehouses'];
                $city_origen = $this->getWarehouseCity($warehouses);
                // error_log("remitente product city: $city_origen");

                $orderData = json_decode($order, true);
                $prov_destiny = $orderData["pedido_carrier"][0]["city_external"]['id_provincia'];
                $city_destiny = $orderData["pedido_carrier"][0]["city_external"]['id'];

                // error_log("prov_destiny: $prov_destiny");
                // error_log("city_destiny: $city_destiny");

                $carrierExternal = CarriersExternal::where('id', 3)->first();
                // error_log("carrierExternal: $carrierExternal");

                $status_array = json_decode($carrierExternal->status, true);

                $costs = json_decode($carrierExternal->costs, true);

                $city_type = CarrierCoverage::where('id_carrier', 3)->where('id_coverage', $city_destiny)->first();
                $coverage_type = $city_type['type'];
                // error_log("coverage_type: $coverage_type");

                $orderid = $orderData['id'];
                $nombreComercial = $order->vendor->nombre_comercial;
                $codigo_order = $nombreComercial . "-" . $order->numero_orden;
                // error_log("codigo_order: $codigo_order");

                DB::beginTransaction();
                try {
                    //

                    foreach ($status_array as $status) {
                        $id_ref = $status['id_ref'];

                        if ($id_ref == $estadoCod) {

                            $key = $status['estado'];
                            $name_local = $status['name_local'];
                            $name = $status['name'];
                            $id = $status['id'];

                            //control de Entregado o Devolución/Entrega
                            if ($id_ref == 7) {
                                if ($estadoActual == "ENTREGADO") {
                                    $key = 'status';
                                    $name_local = 'ENTREGADO';
                                } else {
                                    // Si `estadoActual` no es "ENTREGADO", verifica la existencia de novedad y la palabra "Devolución"
                                    if (!empty($novedad) && stripos($novedad['nombreTipoNovedad'], 'Devolucion') !== false) {
                                        // Si `novedad` tiene un valor y `nombreTipoNovedad` contiene la palabra "Devolución"
                                        $key = 'estado_devolucion';
                                        $name_local = 'EN BODEGA';
                                    } else {
                                        $key = 'status';
                                    }
                                }
                                error_log("key_control: $key");
                            }

                            error_log("Estado: $key, Nombre Local: $name_local, ID_ref: $id_ref, estadoActual: $name, ID: $id");
                            $iva = 0.15; //15%
                            $costo_easy = 2.3;
                            $commentHist = "";

                            if ($key == "estado_logistico") {

                                if ($name_local == "ENVIADO") {  //from externo

                                    $order->estado_logistico = $name_local;
                                    $order->sent_at = $currentDateTime;
                                    // $order->sent_by = $idUser;
                                    $order->marca_tiempo_envio = $date;
                                    $order->estado_interno = "CONFIRMADO";
                                    $order->fecha_entrega = $date;

                                    //reduccion del stock
                                    $variants = json_decode($orderData['variant_details'], true);

                                    $idComercial = $orderData['id_comercial'];
                                    $responses = [];

                                    $allow = true;

                                    $historyOrder = StockHistory::where('pedidos_shopify_id', $order->id)
                                        ->latest('id')
                                        ->first();

                                    // error_log("historyOrder: $historyOrder");
                                    if ($historyOrder) {
                                        // El registro más reciente existe
                                        if ($historyOrder->type == 0) {
                                            $allow = false;
                                            error_log("$order->id $historyOrder->type YA existe");
                                        } else {
                                            $allow = true;
                                        }
                                    }

                                    if ($allow) {

                                        if ($variants != null) {
                                            foreach ($variants as $variant) {
                                                $quantity = $variant['quantity'];
                                                $skuProduct = $variant['sku'];
                                                error_log("skuProduct: $skuProduct");

                                                if ($skuProduct != "" || $skuProduct != null) {

                                                    $onlySku = 0;
                                                    $productIdFromSKU = 0;

                                                    $pattern = '/^[a-zA-Z0-9]+C\d+$/';

                                                    if (preg_match($pattern, $skuProduct) && strpos($skuProduct, ' ') === false) {

                                                        $type = 0;
                                                        $productapi = new ProductAPIController();

                                                        $result = $productapi->splitSku($skuProduct);

                                                        $onlySku = $result['sku'];
                                                        $productIdFromSKU = $result['id'];
                                                        $reserveController = new ReserveAPIController();

                                                        $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);
                                                        $searchResult = json_decode($response->getContent());

                                                        if ($searchResult && $searchResult->response) {
                                                            $reserve = $searchResult->reserve;
                                                            $previous_stock = $reserve->stock;
                                                            if ($type == 0 && $quantity > $reserve->stock) {
                                                                $responses[] = ['message' => 'No Dispone de Stock en la Reserva'];
                                                                continue;
                                                            }

                                                            // Actualizar el stock
                                                            $reserve->stock -= $quantity;

                                                            // Assuming you have a 'Reserve' model that you want to save after updating
                                                            $reserveModel = Reserve::find($reserve->id);
                                                            if ($reserveModel) {
                                                                $reserveModel->stock = $reserve->stock;
                                                                $reserveModel->save();
                                                            }

                                                            $createHistory = new StockHistory();
                                                            $createHistory->product_id = $productIdFromSKU;
                                                            $createHistory->variant_sku = $onlySku;
                                                            $createHistory->type = 0;
                                                            $createHistory->date = $currentDateTime;
                                                            $createHistory->units = $quantity;
                                                            $createHistory->last_stock_reserve = $previous_stock;
                                                            $createHistory->current_stock_reserve = $reserve->stock;
                                                            $createHistory->pedidos_shopify_id = $order->id;
                                                            $createHistory->description = "Reducción de stock Reserva Pedido ENVIADO $codigo_order";
                                                            $createHistory->save();

                                                            $responses[] = ['message' => 'Stock actualizado con éxito', 'reserve' => $reserveModel];
                                                        } else {
                                                            // Encuentra el producto por su SKU.
                                                            $product = Product::find($productIdFromSKU);

                                                            if ($product === null) {
                                                                return null;
                                                            }

                                                            if ($product) {
                                                                $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);
                                                                $currentDateTime = date('Y-m-d H:i:s');

                                                                $createHistory = new StockHistory();
                                                                $createHistory->product_id = $productIdFromSKU;
                                                                $createHistory->variant_sku = $onlySku;
                                                                $createHistory->type = $type;
                                                                $createHistory->date = $currentDateTime;
                                                                $createHistory->units = $quantity;
                                                                $createHistory->last_stock = $product->stock + $quantity;
                                                                $createHistory->current_stock = $product->stock;
                                                                $createHistory->pedidos_shopify_id = $order->id;
                                                                $createHistory->description = "Reducción de stock General Pedido ENVIADO $codigo_order";

                                                                $createHistory->save();
                                                            } else {
                                                                $responses[] = ['message' => 'Product not found'];
                                                                continue;
                                                            }
                                                        }
                                                    } else {
                                                        // $responses[] = "$skuProduct|4|0|0";
                                                        error_log("El formato de skuProduct no es válido o contiene espacios.\n");
                                                    }
                                                } else {
                                                    error_log("Es vacio o null skuProductVariant: $skuProduct");
                                                }
                                            }
                                        }
                                    }
                                    //
                                }
                            } else if ($key == "status") {


                                if ($name_local == "ENTREGADO") {

                                    //TRANSACCIONES
                                    //paymentOrderDelivered
                                    $transaccionSellerPrevious = Transaccion::where('id_origen', $orderid)
                                        ->get();

                                    if ($transaccionSellerPrevious->isNotEmpty()) {
                                        // Obtener el último registro de transacción
                                        $lastTransaction = $transaccionSellerPrevious->last();
                                        // error_log($lastTransaction);
                                        error_log("$lastTransaction->origen");

                                        if ($lastTransaction->origen !== 'reembolso' && $lastTransaction->origen !== 'envio') {
                                            // return response()->json(["res" => "Transacciones ya Registradas"]);
                                            return response()->json(['message' => "Error, Transacciones ya Registradas."], 400);
                                        }
                                    }

                                    error_log("Registro de costos");

                                    $order->status = $name_local;
                                    $order->fecha_entrega = $date;
                                    $order->status_last_modified_at = $currentDateTime;
                                    // $order->status_last_modified_by = $idUser;

                                    //costos
                                    $deliveryPrice = 0;
                                    error_log("city_origen: $city_origen");
                                    error_log("city_destiny: $city_destiny");
                                    error_log("coverage_type: $coverage_type");


                                    $isSameCity = ($city_destiny == $city_origen && ($city_destiny == 68 || $city_destiny == 32));
                                    $tipoDestino = "";
                                    if ($isSameCity) {
                                        $deliveryPrice = (float)($costs["local"]);
                                        $tipoDestino = "local";
                                        error_log("local $deliveryPrice");
                                    } else {
                                        //
                                        switch ($coverage_type) {
                                            case 'TP':
                                                $deliveryPrice = (float)($costs["principal"]);
                                                $tipoDestino = "principal";
                                                error_log("principal $deliveryPrice");
                                                break;
                                            case 'TS':
                                                $deliveryPrice = (float)($costs["secundario"]);
                                                $tipoDestino = "secundario";
                                                error_log("secundario $deliveryPrice");
                                                break;
                                            case "TE":
                                                $deliveryPrice = (float)($costs["especial"]);
                                                $tipoDestino = "especial";
                                                error_log("especial $deliveryPrice");
                                                break;
                                            case "TO":
                                                $deliveryPrice = (float)($costs["oriente"]);
                                                $tipoDestino = "oriente";
                                                error_log("oriente $deliveryPrice");
                                                break;
                                            default:
                                                $deliveryPrice = 0;
                                                $tipoDestino = "local";
                                                error_log("Tipo de cobertura desconocido");
                                                break;
                                        }
                                    }

                                    $costo_recaudo = 0;
                                    //COD
                                    if ($orderData['recaudo'] == 1) {
                                        $tarifasRango = $costs['costo_recaudo']['tarifas_rango'];

                                        foreach ($tarifasRango as $key => $rango) {

                                            $min = (float)($rango['min']);
                                            $max = (float)($rango['max']);
                                            $tarifa = $rango['tarifa'];

                                            if (((float)$orderData['precio_total']) >= $min && ((float)$orderData['precio_total'])  <= $max) {
                                                if (is_string($tarifa) && substr($tarifa, -1) === '%') {
                                                    $porcentaje = (float)str_replace('%', '', $tarifa) / 100;
                                                    $costo_recaudo = ((float)$orderData['precio_total'])  * $porcentaje;
                                                } elseif (is_float($tarifa)) {
                                                    $costo_recaudo = $tarifa;
                                                }
                                                error_log("costo_recaudo: $costo_recaudo");
                                            }
                                        }
                                    }
                                    $deliveryPrice += $costo_recaudo;
                                    $deliveryPrice = round($deliveryPrice, 2);

                                    $pesoRango = $costs["peso_rango"];

                                    $maxKg = (float)$pesoRango['max_kg'];
                                    $tarifaBase = (float)$pesoRango['tarifa_base'];
                                    $tarifaAdicionalPorKg = (float)$pesoRango['tarifa_adicional'][$tipoDestino];

                                    $pesoTotal = (float)$orderData['peso_total'];
                                    $costByWeight = 0;

                                    if ($pesoTotal <= $maxKg) {
                                        $costByWeight = 0;
                                        error_log("costByWeight_base:");
                                    } else {
                                        $pesoAdicional = $pesoTotal - $maxKg;
                                        $pesoRedondeado = ceil($pesoAdicional);
                                        $costByWeight = $pesoRedondeado * $tarifaAdicionalPorKg;
                                        error_log("pesoAdicional Redond:");
                                    }

                                    $costByWeight = round($costByWeight, 2);

                                    error_log("costByWeight: $costByWeight");

                                    $deliveryPrice += $costByWeight;
                                    $deliveryPrice = $deliveryPrice + ($deliveryPrice * $iva);
                                    $deliveryPrice = round($deliveryPrice, 2);

                                    $deliveryPriceSeller = $deliveryPrice + $costo_easy;
                                    // $deliveryPriceSeller = $deliveryPriceSeller + ($deliveryPriceSeller * $iva);
                                    $deliveryPriceSeller = round($deliveryPriceSeller, 2);


                                    error_log("costo_transporte: $deliveryPrice");
                                    error_log("costo_envio: $deliveryPriceSeller");
                                    $order->costo_transportadora = strval($deliveryPrice);
                                    $order->costo_envio = strval($deliveryPriceSeller);

                                    $variants = json_decode($orderData['variant_details'], true);

                                    error_log("Transaccion nueva");
                                    //transactions
                                    $SellerCreditFinalValue = $this->updateProductAndProviderBalanceInt(
                                        $variants,
                                        $orderData['precio_total'],
                                        1,  //generated_by puede usar el id upser de pruebalaarcourier.api
                                        $orderid,
                                        "ENTREGADO",
                                        $codigo_order,
                                    );


                                    if (isset($SellerCreditFinalValue['value_product_warehouse']) && $SellerCreditFinalValue['value_product_warehouse'] !== null) {
                                        $order->value_product_warehouse = $SellerCreditFinalValue['value_product_warehouse'];
                                    }

                                    $vendedor = Vendedore::where('id_master', $order->id_comercial)->first();
                                    if ($vendedor->referer != null) {
                                        $vendedorPrincipal = Vendedore::where('id_master', $vendedor->referer)->first();
                                        if ($vendedorPrincipal->referer_cost != 0) {
                                            $order->value_referer = $vendedorPrincipal->referer_cost;
                                        }
                                    }

                                    error_log("Registro de credit por pedido entregado");

                                    $comentarioCredit = 'Recaudo  de valor por pedido ' . $order->status;

                                    $monto = 0;
                                    if ($SellerCreditFinalValue['total'] != null) {
                                        $monto = $SellerCreditFinalValue['total'];
                                    }

                                    // ! parte uno de la transaccion global
                                    $newTransactionGlobal = new TransaccionGlobal();
                                    $previousValue = 0;
                                    // ! ****************************************

                                    $this->CreditInt(
                                        $order->id_comercial,
                                        $monto,
                                        $orderid,
                                        'recaudo',
                                        $codigo_order,
                                        $comentarioCredit,
                                        1   //generated_by
                                    );

                                    error_log("Registro de Debit por producto y por costo_envio");

                                    $productValues = [];
                                    $sumatoria = 0;
                                    // !*********
                                    if (!empty($SellerCreditFinalValue['valor_producto'])) {
                                        $productValues = $SellerCreditFinalValue['valor_producto'];
                                        error_log("productValues:");
                                        error_log(print_r($productValues, true));
                                        foreach ($productValues as $valueProduct) {
                                            $comentarioDebit = 'Costo de valor de Producto en Bodega ' . $order->status;
                                            $this->DebitInt(
                                                $order->id_comercial,
                                                $valueProduct,
                                                $orderid,
                                                'valor producto bodega',
                                                $codigo_order,
                                                $comentarioDebit,
                                                1   //generated_by
                                            );

                                            $sumatoria += $valueProduct;
                                        }

                                        $newTransactionGlobal->provider_cost = -$sumatoria;
                                    } else {
                                        $newTransactionGlobal->provider_cost = 0;
                                    }

                                    // ! parte dos de la transaccion global


                                    $this->DebitInt(
                                        $order->id_comercial,
                                        strval($deliveryPriceSeller),
                                        $orderid,
                                        'envio',
                                        $codigo_order,
                                        'Costo de envio por pedido ENTREGADO',
                                        1   //generated_by
                                    );

                                    $vendedor = Vendedore::where("id_master", $order->id_comercial)->get();
                                    $startDateFormatted = new DateTime();

                                    if ($vendedor[0]->referer != null) {
                                        $refered = Vendedore::where('id_master', $vendedor[0]->referer)->get();
                                        $vendedorId = $vendedor[0]->referer;
                                        $user = UpUser::where("id", $vendedorId)->with('vendedores')->first();
                                        $vendedor = $user['vendedores'][0];
                                        $saldo = $vendedor->saldo;
                                        $nuevoSaldo = $saldo + $refered[0]->referer_cost;
                                        $vendedor->saldo = $nuevoSaldo;

                                        // ! parte tres de la transaccion global

                                        $newTrans = new Transaccion();

                                        $newTrans->tipo = "credit";
                                        $newTrans->monto = $refered[0]->referer_cost;
                                        $newTrans->valor_actual = $nuevoSaldo;
                                        $newTrans->valor_anterior = $saldo;
                                        $newTrans->marca_de_tiempo = $startDateFormatted;
                                        $newTrans->id_origen = $orderid;
                                        $newTrans->codigo = $codigo_order;

                                        $newTrans->origen = "referido";
                                        $newTrans->comentario = "comision por referido";

                                        $newTrans->id_vendedor = $vendedorId;
                                        $newTrans->state = 1;
                                        $newTrans->generated_by = 1; //cambiar
                                        $newTrans->save();

                                        $vendedorencontrado = Vendedore::findOrFail($user['vendedores'][0]['id']);
                                        // Actualiza el saldo del vendedor
                                        $vendedorencontrado->saldo = $nuevoSaldo;
                                        $vendedorencontrado->save();

                                        // ! generacion de la transacicon_global para el referenciado
                                        // Obtener la transacción global previa
                                        $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $refered[0]->id_master)
                                            ->orderBy('id', 'desc')
                                            ->first();

                                        $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;



                                        $newTransactionGlobalReferer = new TransaccionGlobal();

                                        if ($existingTransaction == null) {

                                            $newTransactionGlobalReferer->admission_date = $marcaT;
                                            $newTransactionGlobalReferer->delivery_date = now()->format('Y-m-d');
                                            $newTransactionGlobalReferer->status = $order->status;
                                            $newTransactionGlobalReferer->return_state = null;
                                            $newTransactionGlobalReferer->id_order = $order->id;
                                            $newTransactionGlobalReferer->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                                            $newTransactionGlobalReferer->origin = 'Referenciado';
                                            $newTransactionGlobalReferer->withdrawal_price = 0;
                                            $newTransactionGlobalReferer->value_order = 0;
                                            $newTransactionGlobalReferer->return_cost = 0;
                                            $newTransactionGlobalReferer->delivery_cost = 0;
                                            $newTransactionGlobalReferer->notdelivery_cost = 0;
                                            $newTransactionGlobalReferer->provider_cost = 0;
                                            $newTransactionGlobalReferer->referer_cost = $refered[0]->referer_cost;
                                            $newTransactionGlobalReferer->total_transaction =
                                                $newTransactionGlobalReferer->value_order +
                                                $newTransactionGlobalReferer->return_cost +
                                                $newTransactionGlobalReferer->delivery_cost +
                                                $newTransactionGlobalReferer->notdelivery_cost +
                                                $newTransactionGlobalReferer->provider_cost +
                                                $newTransactionGlobalReferer->referer_cost;
                                            $newTransactionGlobalReferer->previous_value = $previousValue;
                                            $newTransactionGlobalReferer->current_value = $previousValue + $newTransactionGlobalReferer->total_transaction;
                                            $newTransactionGlobalReferer->state = true;
                                            // $newTransactionGlobalReferer->id_seller = $pedido->users[0]->vendedores[0]->id_master;
                                            $newTransactionGlobalReferer->id_seller = $refered[0]->id_master;
                                            // $newTransactionGlobalReferer->internal_transportation_cost = -$costoTransportadora; // Ajusta según necesites
                                            $newTransactionGlobalReferer->internal_transportation_cost = 0;
                                            $newTransactionGlobalReferer->external_transportation_cost = 0;
                                            $newTransactionGlobalReferer->external_return_cost = 0;
                                            $newTransactionGlobalReferer->save();
                                        }

                                        // ! **************************************
                                    }
                                    //



                                    $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                                        // ->where('id_seller', $order->users[0]->vendedores[0]->id_master)
                                        ->where('id_seller', $order->id_comercial)
                                        ->first();

                                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                                        ->orderBy('id', 'desc')
                                        ->first();

                                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;


                                    // $newTransactionGlobal = new TransaccionGlobal();

                                    if ($existingTransaction == null) {

                                        $newTransactionGlobal->admission_date = $marcaT;
                                        $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                                        // $newTransactionGlobal->status = $pedido->status;
                                        $newTransactionGlobal->status = 'ENTREGADO';
                                        // 'return_state' => $pedido->estado_devolucion,
                                        $newTransactionGlobal->return_state = null;
                                        $newTransactionGlobal->id_order = $order->id;
                                        $newTransactionGlobal->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                                        $newTransactionGlobal->origin = 'Pedido ' . $order->status;
                                        $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                                        $newTransactionGlobal->value_order = $order->precio_total; // Ajusta según necesites
                                        $newTransactionGlobal->return_cost = 0;
                                        $newTransactionGlobal->delivery_cost = -$order->costo_envio; // Ajusta según necesites
                                        $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                                        // $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                                        $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                                        $newTransactionGlobal->total_transaction =
                                            $newTransactionGlobal->value_order +
                                            $newTransactionGlobal->return_cost +
                                            $newTransactionGlobal->delivery_cost +
                                            $newTransactionGlobal->notdelivery_cost +
                                            $newTransactionGlobal->provider_cost +
                                            $newTransactionGlobal->referer_cost;
                                        $newTransactionGlobal->previous_value = $previousValue;
                                        $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                                        $newTransactionGlobal->state = true;
                                        $newTransactionGlobal->id_seller = $order->users[0]->vendedores[0]->id_master;
                                        $newTransactionGlobal->internal_transportation_cost = 0; // Ajusta según necesites
                                        $newTransactionGlobal->external_transportation_cost = -strval($deliveryPrice); // Ajusta según necesites
                                        $newTransactionGlobal->external_return_cost = 0;
                                        $newTransactionGlobal->save();
                                    }
                                    //
                                } else if ($name_local == "NOVEDAD") {
                                    //
                                    if (!empty($novedad)) {

                                        error_log("Novedad recibida: " . json_encode($novedad));

                                        $id_novedad = $novedad['codigoTipoNovedad'];
                                        $no_novedad = $novedad['nombreTipoNovedad'];
                                        $nombreDetalleNovedad = $novedad['nombreDetalleNovedad'];

                                        $commentText = "";
                                        $commentText = $no_novedad;

                                        error_log("commentText: $commentText");
                                        $commentHist = $commentText;

                                        $novedades = NovedadesPedidosShopifyLink::where('pedidos_shopify_id', $order->id)->get();
                                        $novedades_try = $novedades->isEmpty() ? 0 : $novedades->count();

                                        $novedad = new Novedade();
                                        $novedad->m_t_novedad = $currentDateTimeText;
                                        $novedad->try = $novedades_try + 1;
                                        $novedad->url_image = "";
                                        $novedad->comment =  $commentText;
                                        $novedad->external_id = $id_novedad;
                                        $novedad->published_at = $currentDateTime;
                                        $novedad->save();

                                        $novedad_pedido = new NovedadesPedidosShopifyLink();
                                        $novedad_pedido->novedad_id = $novedad->id;
                                        $novedad_pedido->pedidos_shopify_id = $order->id;
                                        $novedad_pedido->novedad_order = $novedades_try + 1;
                                        $novedad_pedido->save();

                                        $order->status = $name_local;
                                        $order->status_last_modified_at = $currentDateTime;
                                    } else {
                                        error_log("Novedad está vacía o no presente.");
                                    }
                                } else {
                                    $order->status = $name_local;
                                    $order->status_last_modified_at = $currentDateTime;
                                }
                            } else if ($key == "estado_devolucion") {
                                if ($order->status == "NOVEDAD") {
                                    //
                                    if ($name_local == "EN BODEGA") {
                                        $order->estado_devolucion = $name_local;
                                        $order->dl = $name_local;
                                        $order->marca_t_d_t = $currentDateTimeText;
                                        // $order->received_by = $idUser;

                                        //update stock de producto
                                        //caso de producto con dueño externo vuelve a reserva
                                        $seller_owned = $order['product']['seller_owned'];
                                        error_log("$seller_owned");

                                        $historyOrder = StockHistory::where('pedidos_shopify_id', $order->id)
                                            ->latest('id')
                                            ->first();
                                        $allow = true;

                                        // error_log("$historyOrder: $historyOrder");
                                        if ($historyOrder) {
                                            // El registro más reciente existe
                                            if ($historyOrder->type == 1) {
                                                $allow = false;
                                                error_log("$order->id $historyOrder->type YA existe");
                                            } else {
                                                $allow = true;
                                            }
                                        }

                                        if ($allow) {
                                            if ($seller_owned != 0 || $seller_owned != null) {
                                                error_log("IS seller_owned: $seller_owned");

                                                //reduccion del stock
                                                $variants = json_decode($orderData['variant_details'], true);

                                                $idComercial = $orderData['id_comercial'];
                                                $responses = [];
                                                if ($variants != null) {
                                                    foreach ($variants as $variant) {
                                                        $quantity = $variant['quantity'];
                                                        $skuProduct = $variant['sku'];
                                                        $type = 1;
                                                        $productapi = new ProductAPIController();

                                                        $result = $productapi->splitSku($skuProduct);

                                                        $onlySku = $result['sku'];
                                                        $productIdFromSKU = $result['id'];

                                                        $reserveController = new ReserveAPIController();

                                                        $response = $reserveController->findByProductAndSku($productIdFromSKU, $onlySku, $idComercial);
                                                        $searchResult = json_decode($response->getContent());

                                                        if ($searchResult && $searchResult->response) {
                                                            $reserve = $searchResult->reserve;
                                                            $previous_stock = $reserve->stock;
                                                            if ($type == 0 && $quantity > $reserve->stock) {
                                                                $responses[] = ['message' => 'No Dispone de Stock en la Reserva'];
                                                                continue;
                                                            }

                                                            // Actualizar el stock
                                                            $reserve->stock += $quantity;

                                                            // Assuming you have a 'Reserve' model that you want to save after updating
                                                            $reserveModel = Reserve::find($reserve->id);
                                                            if ($reserveModel) {
                                                                $reserveModel->stock = $reserve->stock;
                                                                $reserveModel->save();
                                                            }

                                                            $createHistory = new StockHistory();
                                                            $createHistory->product_id = $productIdFromSKU;
                                                            $createHistory->variant_sku = $onlySku;
                                                            $createHistory->type = 1;
                                                            $createHistory->date = $currentDateTime;
                                                            $createHistory->units = $quantity;
                                                            $createHistory->last_stock_reserve = $previous_stock;
                                                            $createHistory->current_stock_reserve = $reserve->stock;
                                                            $createHistory->pedidos_shopify_id = $order->id;
                                                            $createHistory->description = "Incremento de stock Reserva Pedido EN BODEGA $codigo_order";
                                                            $createHistory->save();

                                                            $responses[] = ['message' => 'Stock actualizado con éxito', 'reserve' => $reserveModel];
                                                        } else {
                                                            error_log("No found producto: $productIdFromSKU-$onlySku");
                                                        }
                                                    }
                                                }
                                            } else {
                                                //producto normal: reducir stock general
                                                error_log("stock general");
                                                $variants = json_decode($orderData['variant_details'], true);


                                                if ($variants != null) {
                                                    foreach ($variants as $variant) {
                                                        $quantity = $variant['quantity'];
                                                        $skuProduct = $variant['sku'];
                                                        $type = 1;

                                                        $productapi = new ProductAPIController();

                                                        $result = $productapi->splitSku($skuProduct);

                                                        $onlySku = $result['sku'];
                                                        $productIdFromSKU = $result['id'];

                                                        $product = Product::find($productIdFromSKU);

                                                        $result = $product->changeStockGen($productIdFromSKU, $onlySku, $quantity, $type);


                                                        $createHistory = new StockHistory();
                                                        $createHistory->product_id = $productIdFromSKU;
                                                        $createHistory->variant_sku = $onlySku;
                                                        $createHistory->type = $type;
                                                        $createHistory->date = $currentDateTime;
                                                        $createHistory->units = $quantity;
                                                        $createHistory->last_stock = $product->stock - $quantity;
                                                        $createHistory->current_stock = $product->stock;
                                                        $createHistory->pedidos_shopify_id = $order->id;
                                                        $createHistory->description = "Incremento de stock General Pedido EN BODEGA $codigo_order";

                                                        $createHistory->save();
                                                    }
                                                }
                                            }
                                        }
                                    } else if ($name_local == "ENTREGADO EN OFICINA") {
                                        $order->estado_devolucion = $name_local;
                                        $order->dt = $name_local;
                                        $order->marca_t_d_t = $currentDateTimeText;
                                        // $order->received_by = $idUser;
                                    } else if ($name_local == "DEVOLUCION EN RUTA") {
                                        $order->estado_devolucion = $name_local;
                                        $order->dt = $name_local;
                                        $order->marca_t_d_t = $currentDateTimeText;
                                        // $order->received_by = $idUser;
                                    }

                                    // ! integracion

                                    // Verificar si ya existe una transacción global para este pedido y vendedor
                                    $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                                        // ->where('id_seller', $order->users[0]->vendedores[0]->id_master)
                                        ->where('id_seller', $order->id_comercial)
                                        ->first();

                                    // Obtener la transacción global previa
                                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                                        ->orderBy('id', 'desc')
                                        ->first();


                                    // ! aqui deberia ir otra transaccion global pero seria (NOVEDAD, est_devolucion != pendiente para las externas)

                                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                                    error_log("-----------");
                                    error_log($existingTransaction);
                                    error_log("-----------");

                                    if ($existingTransaction != null) {
                                        $existingTransaction->return_state = $order->estado_devolucion;
                                        $existingTransaction->save();
                                    }

                                    if ($order->costo_devolucion == null) {
                                        //
                                        $deliveryPrice = 0;
                                        $isSameCity = ($city_destiny == $city_origen && ($city_destiny == 68 || $city_destiny == 32));
                                        $tipoDestino = "";

                                        if ($isSameCity) {
                                            $deliveryPrice = (float)($costs["local"]);
                                            $tipoDestino = "local";
                                            error_log("local $deliveryPrice");
                                        } else {
                                            //
                                            switch ($coverage_type) {
                                                case 'TP':
                                                    $deliveryPrice = (float)($costs["principal"]);
                                                    $tipoDestino = "principal";
                                                    error_log("principal $deliveryPrice");
                                                    break;
                                                case 'TS':
                                                    $deliveryPrice = (float)($costs["secundario"]);
                                                    $tipoDestino = "secundario";
                                                    error_log("secundario $deliveryPrice");
                                                    break;
                                                case "TE":
                                                    $deliveryPrice = (float)($costs["especial"]);
                                                    $tipoDestino = "especial";
                                                    error_log("especial $deliveryPrice");
                                                    break;
                                                case "TO":
                                                    $deliveryPrice = (float)($costs["oriente"]);
                                                    $tipoDestino = "oriente";
                                                    error_log("oriente $deliveryPrice");
                                                    break;
                                                default:
                                                    $deliveryPrice = 0;
                                                    $tipoDestino = "local";
                                                    error_log("Tipo de cobertura desconocido");
                                                    break;
                                            }
                                        }

                                        //COD no
                                        //
                                        $pesoRango = $costs["peso_rango"];

                                        $maxKg = (float)($pesoRango['max_kg']);
                                        $tarifaBase = (float)($pesoRango['tarifa_base']);
                                        $tarifaAdicionalPorKg = (float)$pesoRango['tarifa_adicional'][$tipoDestino];

                                        $pesoTotal = (float)$orderData['peso_total'];
                                        $costByWeight = 0;

                                        if ($pesoTotal <= $maxKg) {
                                            $costByWeight = 0;
                                            error_log("costByWeight_base:");
                                        } else {
                                            $pesoAdicional = $pesoTotal - $maxKg;
                                            $pesoRedondeado = ceil($pesoAdicional);
                                            $costByWeight = $pesoRedondeado * $tarifaAdicionalPorKg;
                                            error_log("pesoAdicional Redond:");
                                        }

                                        $costByWeight = round($costByWeight, 2);
                                        error_log("costByWeight: $costByWeight");

                                        $deliveryPrice += $costByWeight;
                                        $deliveryPrice = $deliveryPrice + ($deliveryPrice * $iva);
                                        $deliveryPrice = round($deliveryPrice, 2);

                                        $deliveryPriceSeller = $deliveryPrice + $costo_easy;
                                        // $deliveryPriceSeller = $deliveryPriceSeller + ($deliveryPriceSeller * $iva);
                                        $deliveryPriceSeller = round($deliveryPriceSeller, 2);

                                        $order->costo_transportadora = strval($deliveryPrice);
                                        $order->costo_envio = strval($deliveryPriceSeller);
                                        error_log("costo_transp: $deliveryPrice");
                                        error_log("costo_envio (seller): $deliveryPriceSeller");

                                        $refound_seller = 0;
                                        $refound_transp = 0;

                                        $order->costo_devolucion = round(((float)$refound_seller), 2);
                                        // $order->cost_refound_external = round(((float)$refound_transp), 2);
                                        $pedidoCarrier = PedidosShopifiesCarrierExternalLink::where('pedidos_shopify_id', $orderid)->first();
                                        $pedidoCarrier->cost_refound_external = round(((float)$refound_transp), 2);
                                        $pedidoCarrier->save();
                                        /*
                                        $comentarioDebitEnvio = 'Costo de envio por pedido en ' . $order->status . " y " . $order->estado_devolucion;
                                        /*
                                        $comentarioDebitEnvio = 'Costo de envio por pedido en ' . $order->status . " y " . $order->estado_devolucion;
                                        $this->DebitInt(
                                            $order->id_comercial,
                                            $deliveryPriceSeller,
                                            $orderid,
                                            'envio',
                                            $codigo_order,
                                            $comentarioDebitEnvio,
                                            1   //cambiar
                                        );

                                        $comentarioDebitDev = 'Costo de devolución por pedido en ' . $order->status . " y " . $order->estado_devolucion;
                                        $this->DebitInt(
                                            $order->id_comercial,
                                            round(((float)$refound_seller), 2),
                                            $orderid,
                                            'devolucion',
                                            $codigo_order,
                                            $comentarioDebitDev,
                                            1   //cambiar
                                        );
                                        */


                                        if ($existingTransaction == null) {

                                            error_log($existingTransaction);

                                            $newTransactionGlobal = new TransaccionGlobal();

                                            $newTransactionGlobal->admission_date = $marcaT;
                                            $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                                            $newTransactionGlobal->status = "NOVEDAD";
                                            $newTransactionGlobal->return_state = $order->estado_devolucion;
                                            // $newTransactionGlobal->return_state = null;
                                            $newTransactionGlobal->id_order = $order->id;
                                            $newTransactionGlobal->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                                            $newTransactionGlobal->origin = 'Pedido ' . 'NOVEDAD';
                                            $newTransactionGlobal->withdrawal_price = 0;
                                            $newTransactionGlobal->value_order = 0;
                                            $newTransactionGlobal->return_cost = -$order->costo_devolucion;
                                            $newTransactionGlobal->delivery_cost = -$order->costo_envio;
                                            $newTransactionGlobal->notdelivery_cost = 0;
                                            $newTransactionGlobal->provider_cost = 0;
                                            $newTransactionGlobal->referer_cost = 0;
                                            $newTransactionGlobal->total_transaction =
                                                $newTransactionGlobal->value_order +
                                                $newTransactionGlobal->return_cost +
                                                $newTransactionGlobal->delivery_cost +
                                                $newTransactionGlobal->notdelivery_cost +
                                                $newTransactionGlobal->provider_cost +
                                                $newTransactionGlobal->referer_cost;
                                            $newTransactionGlobal->previous_value = $previousValue;
                                            $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                                            $newTransactionGlobal->state = true;
                                            $newTransactionGlobal->id_seller = $order->users[0]->vendedores[0]->id_master;
                                            $newTransactionGlobal->internal_transportation_cost = 0;
                                            $newTransactionGlobal->external_transportation_cost =  -$order->costo_transportadora;
                                            $newTransactionGlobal->external_return_cost = -round(((float)$refound_transp), 2);
                                            $newTransactionGlobal->save();
                                        }
                                    } else {
                                        //
                                        error_log("ya existe registro costo_devolucion");
                                    }
                                } else {
                                    return response()->json(['message' => "Error, Order must be in NOVEDAD."], 400);
                                }
                            }

                            //new column
                            $newHistory = [
                                "area" => $key,
                                "status" => $name_local,
                                "timestap" => date('Y-m-d H:i:s'),
                                "comment" => $commentHist,
                                "path" => "",
                                "generated_by" => "5_LAAR" //podria definir por upuser
                            ];

                            if ($order->status_history === null || $order->status_history === '[]') {
                                $order->status_history = json_encode([$newHistory]);
                            } else {
                                $existingHistory = json_decode($order->status_history, true);

                                $existingHistory[] = $newHistory;

                                $order->status_history = json_encode($existingHistory);
                            }


                            if ($order->peso_total !=  $pesoKilos) {
                                error_log("updt_peso_local: " . $order->peso_total . " laar: " . $pesoKilos);
                                $order->peso_total = $pesoKilos;
                            } else {
                                // error_log("igual_local: " . $order->peso_total . " laar: " . $pesoKilos);
                            }

                            $order->save();

                            break;
                        }
                    }

                    DB::commit();
                    return response()->json(['message' => 'Order updated successfully.'], 200);
                } catch (\Exception $e) {
                    DB::rollback();
                    error_log("requestUpdateStateLaar_error: $e ");
                    return response()->json([
                        'error' => "There was an error processing your request. " . $e->getMessage()
                    ], 500);
                }
            }
        } catch (JWTException $e) {
            error_log("requestUpdateStateLaar_error_JWTException");
            return response()->json([], 401);
        }
    }
}
