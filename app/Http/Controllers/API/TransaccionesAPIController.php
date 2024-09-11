<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PedidosShopifiesOperadoreLink;
use App\Models\PedidosShopifiesRutaLink;
use App\Models\PedidosShopifiesSubRutaLink;
use App\Models\PedidosShopifiesTransportadoraLink;
use App\Models\PedidosShopify;
use App\Models\Transaccion;
use App\Models\TransactionsGlobal;
use App\Models\UpUser;
use App\Models\Vendedore;
use App\Models\Product;
use App\Models\ProviderTransaction;
use App\Models\StockHistory;
use App\Models\Provider;

use App\Http\Controllers\API\ProductAPIController;
use App\Models\CarrierCoverage;
use App\Models\OrdenesRetiro;
use App\Models\OrdenesRetirosUsersPermissionsUserLink;
use App\Models\PedidosShopifiesCarrierExternalLink;
use App\Models\TransaccionPedidoTransportadora;
use App\Models\TransportadorasShippingCost;
use App\Models\TransaccionGlobal;
use App\Repositories\transaccionesRepository;
use App\Repositories\vendedorRepository;
use App\Repositories\providerRepository;
use App\Repositories\providerTransactionRepository;

use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\error;
use function PHPUnit\Framework\isEmpty;

use Illuminate\Support\Facades\Log;

class TransaccionesAPIController extends Controller
{
    protected $transaccionesRepository;
    protected $vendedorRepository;
    protected $providerTransactionRepository;
    protected $providerRepository;

    public function __construct(
        transaccionesRepository $transaccionesRepository,
        vendedorRepository $vendedorRepository,
        providerTransactionRepository $providerTransactionRepository,
        providerRepository $providerRepository
    ) {
        $this->transaccionesRepository = $transaccionesRepository;
        $this->vendedorRepository = $vendedorRepository;
        $this->providerTransactionRepository = $providerTransactionRepository;
        $this->providerRepository = $providerRepository;
    }



    public function getExistTransaction(Request $request)
    {
        $data = $request->json()->all();
        $tipo = $data['tipo'];
        $idOrigen = $data['id_origen'];
        $origen = $data['origen'];
        $idVendedor = $data['id_vendedor'];



        $pedido = Transaccion::where('tipo', $tipo)
            ->where('id_origen', $idOrigen)
            ->where('origen', $origen)->where('id_vendedor', $idVendedor)
            ->get();

        return response()->json($pedido);
    }

    public function getTransactionsByDate(Request $request)
    {
        $data = $request->json()->all();
        $search = $data['search'];
        $and = $data['and'];
        if ($data['start'] == null) {
            $data['start'] = "2023-01-10 00:00:00";
        }
        if ($data['end'] == null) {
            $data['end'] = "2223-01-10 00:00:00";
        }
        $startDate = Carbon::parse($data['start'])->startOfDay();
        $endDate = Carbon::parse($data['end'])->endOfDay();




        $filteredData = Transaccion::whereBetween('marca_de_tiempo', [$startDate, $endDate]);
        if ($search != "") {
            $filteredData->where("codigo", 'like', '%' . $search . '%');
        }
        if ($and != []) {
            $filteredData->where((function ($pedidos) use ($and) {
                foreach ($and as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }));
        }



        return response()->json($filteredData->get());
    }

    private function recursiveWhereHas($query, $relation, $property, $searchTerm)
    {
        if ($searchTerm == "null") {
            $searchTerm = null;
        }
        if (strpos($property, '.') !== false) {

            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm) {
                $this->recursiveWhereHas($q, $nestedRelation, $nestedProperty, $searchTerm);
            });
        } else {
            $query->whereHas($relation, function ($q) use ($property, $searchTerm) {
                $q->where($property, '=', $searchTerm);
            });
        }
    }

    public function last30rows()
    {
        $ultimosRegistros = Transaccion::orderBy('id', 'desc')
            ->limit(300)
            ->get();

        return response()->json($ultimosRegistros);
    }
    public function index()
    {
        $transacciones = Transaccion::all();
        return response()->json($transacciones);
    }

    public function show($id)
    {
        $transaccion = Transaccion::findOrFail($id);
        return response()->json($transaccion);
    }

    public function Credit(Request $request)
    {
        $data = $request->json()->all();
        $startDateFormatted = new DateTime();
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y H:i', $startDate)->format('Y-m-d H:i');
        $vendedorId = $data['id'];
        $tipo = "credit";
        $monto = $data['monto'];
        $idOrigen = $data['id_origen'];
        $codigo = $data['codigo'];
        $origen = $data['origen'];
        $comentario = $data['comentario'];
        $comentario = $data['comentario'];
        $generated_by = $data['generated_by'];


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
        $this->transaccionesRepository->create($newTrans);
        $this->vendedorRepository->update($nuevoSaldo, $user['vendedores'][0]['id']);

        return response()->json("Monto acreditado");
    }

    public function DebitLocalProvider(
        $idWithdrawal,
        $code,
        $vendedorId,
        $monto,
        $transaction_type,
        $comment,
        $status,
        $description,
        $generatedby,
        $skuProductReference
    ) {
        $startDateFormatted = new DateTime();
        $user = UpUser::where("id", $vendedorId)->with('providers')->first();

        $provider = $user['providers'][0];
        $saldo = $provider->saldo;
        $nuevoSaldo = $saldo - $monto;
        $user->saldo = $nuevoSaldo;

        $newTrans = new ProviderTransaction();

        // $newTrans->transaction_type = "Retiro";
        $newTrans->transaction_type = $transaction_type;
        $newTrans->amount = $monto;
        $newTrans->previous_value = $saldo;
        $newTrans->current_value = $nuevoSaldo;
        // $newTrans->timestamp = $startDateFormatted;
        $newTrans->timestamp = now();
        $newTrans->origin_id = $idWithdrawal;
        $newTrans->origin_code = $code;
        $newTrans->provider_id = $user['providers'][0]['id'];
        // $newTrans->provider_id = $user['id'];
        // $newTrans->comment = "retiro proveedor";
        $newTrans->comment = $comment;
        $newTrans->generated_by = $generatedby;
        // $newTrans->generated_by = $user['providers'][0]['id'];
        // $newTrans->provider_id = $user['id'];
        // $newTrans->status = "APROBADO";
        $newTrans->status = $status;
        // $newTrans->description = "Retiro de billetera APROBADO";
        $newTrans->description = $description;
        $newTrans->sku_product_reference = $skuProductReference;

        $this->providerTransactionRepository->create($newTrans);
        $this->providerRepository->update($nuevoSaldo, $user['providers'][0]['id']);

        return response()->json("Monto debitado");
    }

    public function TransactionGlobal(
        // $admission_date, 
        // $delivery_date, 
        // $status,
        // $return_state,
        // $id_order, 
        // $code, 
        // $origin, 
        // $withdrawal_price, 
        // $value_order, 
        // $return_cost, 
        // $delivery_cost, 
        // $notdelivery_cost, 
        // $provider_cost, 
        // $referer_cost, 
        // $total_transaction, 
        // $previous_value, 
        // $current_value, 
        // $state, 
        // $id_seller,
        // $internal_transportation_cost, 
        // $external_transportation_cost, 
        // $external_return_cost

        $idforsearch
    ) {
        // // * actualizacion de saldo vendedor 
        // $user = UpUser::where("id", $id_seller)->with('vendedores')->first();
        // $vendedor = $user['vendedores'][0];
        // $saldo = $vendedor->saldo;
        // $nuevoSaldo = $saldo - $total_transaction;
        // $vendedor->saldo = $nuevoSaldo;
        // $vendedor->save();


        $searchTransactionGlobal = TransactionsGlobal::where('id_order', $idforsearch)->get();

        // * generacion transaccion global

        // $newTransGlobal = new TransactionsGlobal();
        // $newTransGlobal->admission_date = $admission_date;
        // $newTransGlobal->delivery_date = $delivery_date ; 
        // $newTransGlobal->status = $status;
        // $newTransGlobal->return_state = $return_state;
        // $newTransGlobal->id_order = $id_order ; 
        // $newTransGlobal->code = $code ; 
        // $newTransGlobal->origin = $origin ; 
        // $newTransGlobal->withdrawal_price = $withdrawal_price ; 
        // $newTransGlobal->value_order = $value_order ; 
        // $newTransGlobal->return_cost = $return_cost ; 
        // $newTransGlobal->delivery_cost = $delivery_cost ; 
        // $newTransGlobal->notdelivery_cost = $notdelivery_cost ; 
        // $newTransGlobal->provider_cost = $provider_cost ; 
        // $newTransGlobal->referer_cost = $referer_cost ; 
        // $newTransGlobal->total_transaction = $total_transaction ; 
        // $newTransGlobal->previous_value = $previous_value ; 
        // $newTransGlobal->current_value =$current_value ; 
        // $newTransGlobal->state = $state ; 
        // $newTransGlobal->id_seller = $id_seller;
        // $newTransGlobal->internal_transportation_cost = $internal_transportation_cost ; 
        // $newTransGlobal->external_transportation_cost = $external_transportation_cost ; 
        // $newTransGlobal->external_return_cost = $external_return_cost;
        // $newTransGlobal->save();


        return response()->json("Monto debitado");
    }

    // public function TransactionGlobalLocal($vendedirId,$monto, $idOrigen, $codigo, $origen,){
    // }
    public function DebitLocal($vendedorId, $monto, $idOrigen, $codigo, $origen, $comentario, $generated_by)
    {
        $startDateFormatted = new DateTime();
        $user = UpUser::where("id", $vendedorId)->with('vendedores')->first();
        $vendedor = $user['vendedores'][0];
        $saldo = $vendedor->saldo;
        $nuevoSaldo = $saldo - $monto;
        $vendedor->saldo = $nuevoSaldo;


        $newTrans = new Transaccion();

        $newTrans->tipo = "debit";
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
        $this->transaccionesRepository->create($newTrans);
        $this->vendedorRepository->update($nuevoSaldo, $user['vendedores'][0]['id']);

        return response()->json("Monto debitado");
    }

    public function CreditLocal($vendedorId, $monto, $idOrigen, $codigo, $origen, $comentario, $generated_by)
    {
        $startDateFormatted = new DateTime();
        $user = UpUser::where("id", $vendedorId)->with('vendedores')->first();
        $vendedor = $user['vendedores'][0];
        $saldo = $vendedor->saldo;
        $nuevoSaldo = $saldo + $monto;
        $vendedor->saldo = $nuevoSaldo;


        $newTrans = new Transaccion();

        $newTrans->tipo = "credit";
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
        $this->transaccionesRepository->create($newTrans);
        $this->vendedorRepository->update($nuevoSaldo, $user['vendedores'][0]['id']);

        return response()->json("Monto acreditado");
    }

    public function CreditLocalProvider($vendedorId, $monto, $idOrigen, $codigo, $comentario, $generated_by)
    {
        $startDateFormatted = new DateTime();
        $user = UpUser::where("id", $vendedorId)->with('providers')->first();
        $provider = $user['providers'][0];
        $saldo = $provider->saldo;
        $nuevoSaldo = $saldo + $monto;
        $provider->saldo = $nuevoSaldo;


        // $newTrans = new Transaccion();
        $newTrans = new ProviderTransaction();

        $newTrans->transaction_type = "Reembolso";
        $newTrans->amount = $monto;
        $newTrans->previous_value = $saldo;

        $newTrans->current_value = $nuevoSaldo;
        // $newTrans->marca_de_tiempo = $startDateFormatted;
        $newTrans->timestamp = now();
        $newTrans->origin_id = $idOrigen;
        $newTrans->origin_code = $codigo;
        $newTrans->provider_id = $user['providers'][0]['id'];
        $newTrans->comment = $comentario;
        $newTrans->generated_by = $generated_by;
        $newTrans->status = "RECHAZADO";
        $newTrans->description = "Retiro cancelado, generado por error";



        $this->providerTransactionRepository->create($newTrans);
        $this->providerRepository->update($nuevoSaldo, $user['providers'][0]['id']);

        return response()->json("Monto acreditado");
    }

    public function Debit(Request $request)
    {
        $data = $request->json()->all();
        $startDateFormatted = new DateTime();
        //  $startDateFormatted = Carbon::createFromFormat('j/n/Y H:i', $startDate)->format('Y-m-d H:i');
        $vendedorId = $data['id'];
        $tipo = "debit";
        $monto = $data['monto'];
        $idOrigen = $data['id_origen'];
        $codigo = $data['codigo'];

        $origen = $data['origen'];
        $comentario = $data['comentario'];
        $generated_by = $data['generated_by'];


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

        $insertedData = $this->transaccionesRepository->create($newTrans);
        $updatedData = $this->vendedorRepository->update($nuevoSaldo, $user['vendedores'][0]['id']);

        return response()->json("Monto debitado");
    }

    // public function updateProductAndProviderBalance($skuProduct, $totalPrice, $quantity, $generated_by, $id_origin, $orderStatus, $codeOrder)
    public function updateProductAndProviderBalance($variants, $totalPrice, $generated_by, $id_origin, $orderStatus, $codeOrder)
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

                error_log("ak-> $providerTransactionPrevious");

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

    private function dateFormatter($dateOfOrder)
    {
        // Agregar ceros a las fechas/hora de un solo dígito en el formato 9/9/2024 9:9
        $marca_t_i = preg_replace_callback('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{1,2})/', function ($matches) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
            $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
            return "$day/$month/$year $hour:$minute";
        }, $dateOfOrder);

        // Intentar crear la fecha con el formato esperado
        return  Carbon::createFromFormat('d/m/Y H:i', $marca_t_i)->format('Y-m-d');
    }

    public function paymentOrderInWarehouseProvider(Request $request, $id)
    {
        DB::beginTransaction();
        $message = "";
        $repetida = null;

        try {
            $data = $request->json()->all();
            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
            $order->estado_devolucion = "EN BODEGA PROVEEDOR";
            // $order->marca_t_d = date("d/m/Y H:i");
            $order->marca_t_d_l = date("d/m/Y H:i");
            $order->received_by = $data['generated_by'];

            // $marca_t_i = $order->marca_t_i;

            $marcaT = $this->dateFormatter($order->marca_t_i);

            // // Agregar ceros a las fechas/hora de un solo dígito en el formato 9/9/2024 9:9
            // $marca_t_i = preg_replace_callback('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{1,2})/', function ($matches) {
            //     $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            //     $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            //     $year = $matches[3];
            //     $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
            //     $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
            //     return "$day/$month/$year $hour:$minute";
            // }, $marca_t_i);

            // // Intentar crear la fecha con el formato esperado
            // $marcaT = Carbon::createFromFormat('d/m/Y H:i', $marca_t_i)->format('Y-m-d');

            if ($order->status == "NOVEDAD") {

                // ! integracion

                // Verificar si ya existe una transacción global para este pedido y vendedor
                $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                    ->where('id_seller', $order->users[0]->vendedores[0]->id_master)

                    ->first();

                // Obtener la transacción global previa
                $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                    ->orderBy('id', 'desc')
                    ->first();

                $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                if ($existingTransaction != null) {
                    $existingTransaction->return_state = $order->estado_devolucion;
                    $existingTransaction->save();
                }

                if ($order->costo_devolucion == null) { // Verifica si está vacío convirtiendo a un array
                    $order->costo_devolucion = $order->users[0]->vendedores[0]->costo_devolucion;
                    $this->DebitLocal($order->users[0]->vendedores[0]->id_master, $order->users[0]->vendedores[0]->costo_devolucion, $order->id, $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden, "devolucion", "Costo de devolución desde operador por pedido en " . $order->status . " y " . $order->estado_devolucion, $data['generated_by']);



                    if ($existingTransaction == null) {
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
                        $newTransactionGlobal->return_cost = -$order->users[0]->vendedores[0]->costo_devolucion;
                        $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
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
                        $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_return_cost = 0;
                        $newTransactionGlobal->save();

                        $message = "Transacción con débito por estado " . $order->status;
                    }

                    // ! integracion 
                    // ! ----------------------------------------------

                    // // Verificar si ya existe una transacción global para este pedido y vendedor
                    // $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                    //     ->where('id_seller', $order->users[0]->vendedores[0]->id_master)
                    //     ->first();


                    // if ($existingTransaction == null) {
                    //     // Crear la nueva transacción global
                    //     TransaccionGlobal::create([
                    //         'admission_date' => now(), // Ajusta según necesites
                    //         'delivery_date' => null, // Ajusta según necesites
                    //         'status' => $order->status,
                    //         'return_state' => $order->estado_devolucion,
                    //         'id_order' => $order->id,
                    //         'code' => $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden,
                    //         'origin' => 'Pedido' . $order->status,
                    //         'withdrawal_price' => 0, // Ajusta según necesites
                    //         'value_order' => 0, // Ajusta según necesites
                    //         'return_cost' => $order->costo_devolucion,
                    //         'delivery_cost' => 0, // Ajusta según necesites
                    //         'notdelivery_cost' => 0, // Ajusta según necesites
                    //         'provider_cost' => 0, // Ajusta según necesites
                    //         'referer_cost' => 0, // Ajusta según necesites
                    //         'total_transaction' => 0, // Ajusta según necesites
                    //         'previous_value' => 0, // Ajusta según necesites
                    //         'current_value' => 0, // Ajusta según necesites
                    //         'state' => true,
                    //         'id_seller' => $order->users[0]->vendedores[0]->id_master,
                    //         'internal_transportation_cost' => 0, // Ajusta según necesites
                    //         'external_transportation_cost' => 0, // Ajusta según necesites
                    //         'external_return_cost' => 0
                    //     ]);

                    //     $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion;
                    // }
                    //  else {
                    //     $existingTransaction->update([
                    //         'status' => $order->status,
                    //         'return_state' => $order->estado_devolucion,
                    //         'cost_return' => $order->users[0]->vendedores[0]->costo_devolucion,
                    //         'origin' => 'Pedido ' . $order->status
                    //     ]);
                    // }
                    // ! ----------------------------------------------


                    $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion;
                } else {
                    $message = "Transacción ya cobrada";
                }
            } else {
                $message = "Transacción sin débito por estado" . $order->status . " y " . $order->estado_devolucion;
            }

            //new column
            $user = UpUser::where('id',  $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "estado_devolucion",
                "status" => "EN BODEGA PROVEEDOR",
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => "",
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($order->status_history === null || $order->status_history === '[]') {
                $order->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($order->status_history, true);

                $existingHistory[] = $newHistory;

                $order->status_history = json_encode($existingHistory);
            }

            $order->save();
            DB::commit();

            return response()->json([
                "res" => $message,
                "transaccion_repetida" => $repetida
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }


    public function paymentOrderDelivered(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->json()->all();

            $startDateFormatted = new DateTime();

            // $pedido = PedidosShopify::findOrFail($data['id_origen']);
            $pedido = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades', 'operadore', 'transactionTransportadora', 'pedidoCarrier'])->findOrFail($data['id_origen']);
            $marcaT = "";

            if (!empty($pedido->marca_t_i)) {
                try {
                    $marca_t_i = $pedido->marca_t_i;

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
                    Log::error('Error al convertir marca_t_i: ' . $pedido->marca_t_i . ' - ' . $e->getMessage());
                    // Asignar una fecha por defecto si la conversión falla
                    $marcaT = Carbon::now()->format('Y-m-d H:i:s');
                }
            } else {
                // Asignar una fecha por defecto
                $marcaT = Carbon::now()->format('Y-m-d H:i:s');
            }


            // if ($pedido->costo_envio == null) {
            // error_log("Transaccion nueva");

            $transaccionSellerPrevious = Transaccion::
                // where('tipo', 'credit')
                where('id_origen', $data['id_origen'])
                ->get();

            if ($transaccionSellerPrevious->isNotEmpty()) {
                // Obtener el último registro de transacción
                $lastTransaction = $transaccionSellerPrevious->last();
                Log::info($lastTransaction);
                if ($lastTransaction->origen !== 'reembolso' && $lastTransaction->origen !== 'envio') {
                    return response()->json(["res" => "Transacciones ya Registradas"]);
                }
            }

            Log::info("Transaccion nueva");

            $pedido->status = "ENTREGADO";
            $pedido->fecha_entrega = now()->format('j/n/Y');
            $pedido->status_last_modified_at = date('Y-m-d H:i:s');
            $pedido->status_last_modified_by = $data['generated_by'];
            $pedido->comentario = $data["comentario"];
            $pedido->tipo_pago = $data["tipo"];
            $pedido->costo_envio = $data['monto_debit'];
            if ($data["archivo"] != "") {
                $pedido->archivo = $data["archivo"];
            }

            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "status",
                "status" => "ENTREGADO",
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => $data["comentario"],
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($pedido->status_history === null || $pedido->status_history === '[]') {
                $pedido->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($pedido->status_history, true);

                $existingHistory[] = $newHistory;

                $pedido->status_history = json_encode($existingHistory);
            }

            $costoTransportadora = $pedido['transportadora'][0]['costo_transportadora'];
            $pedido->costo_transportadora = $costoTransportadora;
            // $pedido->save();

            // $SellerCreditFinalValue = $this->updateProductAndProviderBalance(
            //     // "TEST2C1003",
            //     $pedido->sku,
            //     $pedido->precio_total,
            //     $pedido->cantidad_total,
            //     $data['generated_by'],
            //     $data['id_origen'],
            //     $pedido->status,
            //     $data['codigo'], 

            //     // 22.90,
            // );
            $variants = json_decode($pedido->variant_details, true);
            // error_log("->> $variants");

            $SellerCreditFinalValue = $this->updateProductAndProviderBalance(
                // "TEST2C1003",
                $variants,
                $pedido->precio_total,
                // $pedido->cantidad_total,
                $data['generated_by'],
                $data['id_origen'],
                $pedido->status,
                $data['codigo'],

                // 22.90,
            );


            if (isset($SellerCreditFinalValue['value_product_warehouse']) && $SellerCreditFinalValue['value_product_warehouse'] !== null) {
                $pedido->value_product_warehouse = $SellerCreditFinalValue['value_product_warehouse'];
            }

            $vendedor = Vendedore::where('id_master', $pedido->id_comercial)->first();
            if ($vendedor->referer != null) {
                $vendedorPrincipal = Vendedore::where('id_master', $vendedor->referer)->first();
                if ($vendedorPrincipal->referer_cost != 0) {
                    $pedido->value_referer = $vendedorPrincipal->referer_cost;
                }
            }

            if ($pedido['operadore']->isNotEmpty()) {
                $operCost = $pedido['operadore'][0]['costo_operador'];
                Log::info("operadore_cost d: " . $operCost);
                $pedido->costo_operador = $operCost;
            }
            $pedido->save();

            // * inicio de la transaccion global
            Log::info("inicio trans global");


            // Verificar si ya existe una transacción global para este pedido y vendedor
            $existingTransaction = TransaccionGlobal::where('id_order', $pedido->id)
                ->where('id_seller', $pedido->users[0]->vendedores[0]->id_master)

                ->first();

            // Obtener la transacción global previa
            $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $pedido->users[0]->vendedores[0]->id_master)
                ->orderBy('id', 'desc')
                ->first();

            $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;



            $newTransactionGlobal = new TransaccionGlobal();



            if ($existingTransaction == null) {

                $newTransactionGlobal->admission_date = $marcaT;
                $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                // $newTransactionGlobal->status = $pedido->status;
                $newTransactionGlobal->status = 'ENTREGADO';
                // 'return_state' => $pedido->estado_devolucion,
                $newTransactionGlobal->return_state = null;
                $newTransactionGlobal->id_order = $pedido->id;
                $newTransactionGlobal->code = $pedido->users[0]->vendedores[0]->nombre_comercial . "-" . $pedido->numero_orden;
                $newTransactionGlobal->origin = 'Pedido ' . $pedido->status;
                $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                $newTransactionGlobal->value_order = $pedido->precio_total; // Ajusta según necesites
                $newTransactionGlobal->return_cost = 0;
                $newTransactionGlobal->delivery_cost = -$pedido->costo_envio; // Ajusta según necesites
                $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                // $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                // $newTransactionGlobal->total_transaction =
                //     $newTransactionGlobal->value_order +
                //     $newTransactionGlobal->return_cost +
                //     $newTransactionGlobal->delivery_cost +
                //     $newTransactionGlobal->notdelivery_cost +
                //     $newTransactionGlobal->provider_cost +
                //     $newTransactionGlobal->referer_cost;
                // $newTransactionGlobal->previous_value = $previousValue;
                // $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                $newTransactionGlobal->state = true;
                $newTransactionGlobal->id_seller = $pedido->users[0]->vendedores[0]->id_master;
                $newTransactionGlobal->internal_transportation_cost = -$costoTransportadora; // Ajusta según necesites
                $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->external_return_cost = 0;

                $message = "Transacción con débito por estado " . $pedido->status;
            }










            // $newTransGlobal = new TransactionsGlobal();
            // $newTransGlobal->admission_date = $pedido->marca_t_i;
            // $newTransGlobal->delivery_date = $pedido->fecha_entrega;
            // $newTransGlobal->status = $pedido->status;
            // $newTransGlobal->return_state = null;
            // // $newTransGlobal->return_state = $return_state;
            // $newTransGlobal->id_order = $pedido->id;
            // $newTransGlobal->code = $pedido['users'][0]['vendedores']['nombre_comercial'] . $pedido->numero_orden;
            // $newTransGlobal->origin = "Pedido " . $pedido->status;
            // $newTransGlobal->withdrawal_price = 0;
            // $newTransGlobal->value_order = $pedido->precio_total;
            // $newTransGlobal->return_cost = 0;
            // *********************************************************


            $request->merge(['comentario' => 'Recaudo  de valor por pedido ' . $pedido->status]);
            $request->merge(['origen' => 'recaudo']);

            if ($SellerCreditFinalValue['total'] != null) {
                $request->merge(['monto' => $SellerCreditFinalValue['total']]);
                // *********************************************************
                // $newTransGlobal->delivery_cost = -$SellerCreditFinalValue['total'];
                // $newTransGlobal->notdelivery_cost = 0;
                Log::info("entregado");

                // *********************************************************

            }

            $this->Credit($request);

            $productValues = [];
            $sumatoria = 0;
            // !*********
            if (!empty($SellerCreditFinalValue['valor_producto'])) {
                $productValues = $SellerCreditFinalValue['valor_producto'];
                // No necesitas codificar a JSON si ya es un array
                // $decodevalues = json_encode($productValues);
                Log::info(print_r($productValues, true));
                foreach ($productValues as $valueProduct) {
                    // Asumiendo que $valueProduct es un número, podrías necesitar validar o formatearlo según sea necesario
                    $request->merge(['comentario' => 'Costo de valor de Producto en Bodega ' . $pedido->status]);
                    $request->merge(['origen' => 'valor producto bodega']);
                    $request->merge(['monto' => $valueProduct]);
                    $this->Debit($request);
                    $sumatoria += $valueProduct;
                }

                // *********************************************************            
                $newTransactionGlobal->provider_cost = -$sumatoria; // Ajusta según necesites
                // $newTransGlobal->provider_cost = -$valueProduct ;
                Log::info("valor producto bodega");
            } else {
                $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites

            }



            // $request->merge(['comentario' => 'Costo de de valor de Producto en Bodega ' . $pedido->status]);
            // $request->merge(['origen' => 'valor producto bodega']);
            // $request->merge(['monto' => $SellerCreditFinalValue['valor_producto']]);

            // $this->Debit($request);
            // !*********

            $request->merge(['comentario' => 'Costo de envio por pedido ' . $pedido->status]);
            $request->merge(['origen' => 'envio']);
            $request->merge(['monto' => $data['monto_debit']]);

            $this->Debit($request);



            $vendedor = Vendedore::where("id_master", $data['id'])->get();

            if ($vendedor[0]->referer != null) {
                $refered = Vendedore::where('id_master', $vendedor[0]->referer)->get();
                $vendedorId = $vendedor[0]->referer;
                $generated_by = $data['generated_by'];
                $user = UpUser::where("id", $vendedorId)->with('vendedores')->first();
                $vendedor = $user['vendedores'][0];
                $saldo = $vendedor->saldo;
                $nuevoSaldo = $saldo + $refered[0]->referer_cost;
                $vendedor->saldo = $nuevoSaldo;

                $newTrans = new Transaccion();

                $newTrans->tipo = "credit";
                $newTrans->monto = $refered[0]->referer_cost;
                $newTrans->valor_actual = $nuevoSaldo;
                $newTrans->valor_anterior = $saldo;
                $newTrans->marca_de_tiempo = $startDateFormatted;
                $newTrans->id_origen = $data['id_origen'];
                $newTrans->codigo = $data['codigo'];

                $newTrans->origen = "referido";
                $newTrans->comentario = "comision por referido";

                $newTrans->id_vendedor = $vendedorId;
                $newTrans->state = 1;
                $newTrans->generated_by = $generated_by;

                $this->transaccionesRepository->create($newTrans);
                $this->vendedorRepository->update($nuevoSaldo, $user['vendedores'][0]['id']);



                // ! transaccion global para el referido --------------------------------------


                // Verificar si ya existe una transacción global para este pedido y vendedor
                // $existingTransaction = TransaccionGlobal::where('id_order', $pedido->id)
                //     ->where('id_seller', $pedido->users[0]->vendedores[0]->id_master)
                //     ->where('status', '!=', 'ROLLBACK')
                //     ->first();

                // Obtener la transacción global previa
                $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $refered[0]->id_master)
                    ->orderBy('id', 'desc')
                    ->first();

                $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;



                $newTransactionGlobalReferer = new TransaccionGlobal();

                error_log($existingTransaction);

                if ($existingTransaction->state  == 0) {

                    $newTransactionGlobalReferer->admission_date = $marcaT;
                    $newTransactionGlobalReferer->delivery_date = now()->format('Y-m-d');
                    $newTransactionGlobalReferer->status = $pedido->status;
                    // 'return_state' => $pedido->estado_devolucion,
                    $newTransactionGlobalReferer->return_state = null;
                    $newTransactionGlobalReferer->id_order = $pedido->id;
                    $newTransactionGlobalReferer->code = $pedido->users[0]->vendedores[0]->nombre_comercial . "-" . $pedido->numero_orden;
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


                // ! --------------------------------------------------------------------------

                // *********************************************************            
                //  ! aqui tmbn debe generar una nueva transaccion_global al referido 
                //  ? esta linea aun no esta validada  
                // $newTransGlobal->referer_cost =  $refered[0]->referer_cost;
                Log::info("referido");

                //  ? ***********************************
                // *********************************************************            

            }


            // *********************************************************  
            Log::info("inicio total");
            // $newTransGlobal->total_transaction = $newTransGlobal->value_order + $newTransGlobal->return_cost + $newTransGlobal->delivery_cost + $newTransGlobal->notdelivery_cost + $newTransGlobal->provider_cost + $newTransGlobal->referer_cost;
            // error_log("fin total");
            // // *********************************************************            

            // $previousTransactionGlobal = TransactionsGlobal::where('id_seller', $pedido->id_comercial)
            //     ->where('order_entry', (($newTrans->order_entry) - 1))
            //     ->get();

            // if (!$previousTransactionGlobal) {
            //     $newTransGlobal->previous_value = 0;
            // } else {
            //     $newTransGlobal->previous_value = $previousTransactionGlobal->current_value;
            // }
            // // $newTransGlobal->previous_value = $previous_value ; 
            // $newTransGlobal->current_value = ($newTransGlobal->total_transaction + $newTransGlobal->previous_value);
            // $newTransGlobal->state = 1;
            // $newTransGlobal->id_seller = $pedido->id_comercial;
            // $newTransGlobal->internal_transportation_cost = $pedido->costo_trnasportadora;
            // // $newTransGlobal->external_transportation_cost = $external_transportation_cost ; 
            // $newTransGlobal->external_transportation_cost = 0;
            // // $newTransGlobal->external_return_cost = $pedido['pedidoCarrier'][0]['cost_refound_external'];
            // $newTransGlobal->external_return_cost = 0;
            // $newTransGlobal->save();
            $newTransactionGlobal->total_transaction =
            $newTransactionGlobal->value_order +
            $newTransactionGlobal->return_cost +
            $newTransactionGlobal->delivery_cost +
            $newTransactionGlobal->notdelivery_cost +
            $newTransactionGlobal->provider_cost +
            $newTransactionGlobal->referer_cost;
            $newTransactionGlobal->previous_value = $previousValue;
            $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
            $newTransactionGlobal->save();

            Log::info("fin creacion nueva transaccion global");
            // *********************************************************
            $idTransportadora = $pedido['transportadora'][0]['id'];
            $fechaEntrega = now()->format('j/n/Y');

            $precioTotal = $pedido['precio_total'];
            $idOper = null;
            if ($pedido['operadore']->isEmpty()) {
                error_log("operadore vacio");
                // error_log("idO: " . $idOper);
            } else {
                error_log("operadore NO vacio");
                $idOper = $pedido['operadore'][0]['id'];
                error_log("idO: " . $idOper);
            }

            if ($pedido['transactionTransportadora'] == null) {
                $transaccionNew = new TransaccionPedidoTransportadora();
                $transaccionNew->status = "ENTREGADO";
                $transaccionNew->fecha_entrega = $fechaEntrega;
                $transaccionNew->precio_total = $precioTotal;
                $transaccionNew->costo_transportadora = $costoTransportadora;
                $transaccionNew->id_pedido = $data['id_origen'];
                $transaccionNew->id_transportadora = $idTransportadora;
                $transaccionNew->id_operador = $idOper;

                $transaccionNew->save();
            } else {
                TransaccionPedidoTransportadora::where('id', $pedido['transactionTransportadora']['id'])->update([
                    'status' => 'ENTREGADO',
                    'costo_transportadora' => $costoTransportadora,
                ]);
            }
            DB::commit(); // Confirma la transacción si todas las operaciones tienen éxito
            return response()->json([
                "res" => "transaccion exitosa"
            ]);
        } catch (\Exception $e) {
            DB::rollback(); // En caso de error, revierte todos los cambios realizados en la transacción
            // Maneja el error aquí si es necesario
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }


    public function paymentOrderNotDelivered(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->json()->all();
            // $pedido = PedidosShopify::findOrFail($data['id_origen']);
            $pedido = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades', 'operadore', 'transactionTransportadora'])->findOrFail($data['id_origen']);
            $marca_t_i = $pedido->marca_t_i;

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


            $pedido->status = "NO ENTREGADO";
            $pedido->fecha_entrega = now()->format('j/n/Y');
            $pedido->status_last_modified_at = date('Y-m-d H:i:s');
            $pedido->status_last_modified_by = $data['generated_by'];
            $pedido->comentario = $data["comentario"];
            $pedido->archivo = $data["archivo"];

            if ($pedido->costo_envio == null) {
                $pedido->costo_envio = $data['monto_debit'];
                $request->merge(['comentario' => 'Costo de envio por pedido ' . $pedido->status]);
                $request->merge(['origen' => 'envio']);
                $request->merge(['monto' => $data['monto_debit']]);
                $this->Debit($request);
            }
            $costoTransportadora = $pedido['transportadora'][0]['costo_transportadora'];
            $pedido->costo_transportadora = $costoTransportadora;
            if ($pedido['operadore']->isNotEmpty()) {
                $operCost = $pedido['operadore'][0]['costo_operador'];
                error_log("operadore_cost nd: " . $operCost);
                $pedido->costo_operador = $operCost;
            }

            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "status",
                "status" => "NO ENTREGADO",
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => $data["comentario"],
                "path" => $data["archivo"],
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($pedido->status_history === null || $pedido->status_history === '[]') {
                $pedido->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($pedido->status_history, true);

                $existingHistory[] = $newHistory;

                $pedido->status_history = json_encode($existingHistory);
            }

            $pedido->save();

            // error_log("NO ENTREGADO add en tpt");

            $idTransportadora = $pedido['transportadora'][0]['id'];
            $fechaEntrega = now()->format('j/n/Y');

            $precioTotal = $pedido['precio_total'];
            $idOper = null;
            if ($pedido['operadore']->isEmpty()) {
                // error_log("operadore vacio");
                // error_log("idO: " . $idOper);
            } else {
                // error_log("operadore NO vacio");
                $idOper = $pedido['operadore'][0]['id'];
                // error_log("idO: " . $idOper);
            }

            if ($pedido['transactionTransportadora'] == null) {
                // error_log("new tpt");
                $transaccionNew = new TransaccionPedidoTransportadora();
                $transaccionNew->status = "NO ENTREGADO";
                $transaccionNew->fecha_entrega = $fechaEntrega;
                $transaccionNew->precio_total = $precioTotal;
                $transaccionNew->costo_transportadora = $costoTransportadora;
                $transaccionNew->id_pedido = $data['id_origen'];
                $transaccionNew->id_transportadora = $idTransportadora;
                $transaccionNew->id_operador = $idOper;

                $transaccionNew->save();
            } else {
                // error_log("upt tpt");
                TransaccionPedidoTransportadora::where('id', $pedido['transactionTransportadora']['id'])->update([
                    'status' => 'NO ENTREGADO',
                    'costo_transportadora' => $costoTransportadora,
                ]);
            }


            // Verificar si ya existe una transacción global para este pedido y vendedor
            $existingTransaction = TransaccionGlobal::where('id_order', $pedido->id)
                ->where('id_seller', $pedido->users[0]->vendedores[0]->id_master)

                ->first();

            // Obtener la transacción global previa
            $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $pedido->users[0]->vendedores[0]->id_master)
                ->orderBy('id', 'desc')
                ->first();

            $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

            error_log($existingTransaction);

            if ($existingTransaction == null) {
                $newTransactionGlobal = new TransaccionGlobal();

                $newTransactionGlobal->admission_date = $marcaT;
                $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                $newTransactionGlobal->status = $pedido->status;
                // 'return_state' => $pedido->estado_devolucion,
                $newTransactionGlobal->return_state = null;
                $newTransactionGlobal->id_order = $pedido->id;
                $newTransactionGlobal->code = $pedido->users[0]->vendedores[0]->nombre_comercial . "-" . $pedido->numero_orden;
                $newTransactionGlobal->origin = 'Pedido ' . $pedido->status;
                $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                $newTransactionGlobal->return_cost = 0;
                $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->notdelivery_cost = -$pedido->costo_envio; // Ajusta según necesites
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
                $newTransactionGlobal->id_seller = $pedido->users[0]->vendedores[0]->id_master;
                $newTransactionGlobal->internal_transportation_cost = -$costoTransportadora; // Ajusta según necesites
                $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->external_return_cost = 0;
                $newTransactionGlobal->save();

                $message = "Transacción con débito por estado " . $pedido->status;
            }
            // else {
            //     $existingTransaction->update([
            //         'status' => $pedido->status,
            //         'notdelivery_cost' =>  -$pedido->costo_envio,
            //         'origin' => 'Pedido ' . $pedido->status,
            //         'previous_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0,
            //         'current_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value + $existingTransaction->total_transaction : $existingTransaction->total_transaction,
            //     ]);
            // }

            DB::commit(); // Confirma la transacción si todas las operaciones tienen éxito
            return response()->json([
                "res" => "transaccion exitosa"
            ]);
        } catch (\Exception $e) {
            DB::rollback(); // En caso de error, revierte todos los cambios realizados en la transacción
            // Maneja el error aquí si es necesario
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function paymentOrderWithNovelty(Request $request, $id)
    {
        DB::beginTransaction();
        $message = "";


        try {
            $data = $request->json()->all();


            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
            // $marcaT = Carbon::createFromFormat('d/m/Y H:i', $order->marca_t_i)->format('Y-m-d H:i:s');

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



            if (
                $order->estado_devolucion ==
                "ENTREGADO EN OFICINA" ||
                $order->estado_devolucion ==
                "DEVOLUCION EN RUTA" ||
                $order->estado_devolucion == "EN BODEGA" ||
                $order->estado_devolucion == "EN BODEGA PROVEEDOR"
            ) {


                if ($order->costo_devolucion == null) {
                    $order->costo_devolucion = $order->users[0]->vendedores[0]->costo_devolucion;
                    $newSaldo = $order->users[0]->vendedores[0]->saldo - $order->users[0]->vendedores[0]->costo_devolucion;

                    $newTrans = new Transaccion();
                    $newTrans->tipo = "debit";
                    $newTrans->monto = $order->users[0]->vendedores[0]->costo_devolucion;
                    $newTrans->valor_actual = $newSaldo;
                    $newTrans->valor_anterior = $order->users[0]->vendedores[0]->saldo;
                    $newTrans->marca_de_tiempo = new DateTime();
                    $newTrans->id_origen = $order->id;
                    $newTrans->codigo = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;

                    $newTrans->origen = "devolucion";
                    $newTrans->comentario = "Costo de devolucion por pedido en   y" . $order->estado_devolucion;

                    $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                    $newTrans->state = 1;
                    $newTrans->generated_by = $data['generated_by'];

                    $this->transaccionesRepository->create($newTrans);
                    $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);



                    // ! integracion

                    // Verificar si ya existe una transacción global para este pedido y vendedor
                    $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                        ->where('id_seller', $order->users[0]->vendedores[0]->id_master)

                        ->first();

                    // Obtener la transacción global previa
                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                        ->orderBy('id', 'desc')
                        ->first();

                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                    error_log($existingTransaction);

                    if ($existingTransaction == null) {
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
                        $newTransactionGlobal->return_cost = -$order->users[0]->vendedores[0]->costo_devolucion;
                        $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
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
                        $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_return_cost = 0;
                        $newTransactionGlobal->save();

                        $message = "Transacción con débito por estado " . $order->status;
                    }
                    //  else {
                    //     $existingTransaction->update([
                    //         'status' => 'NOVEDAD',
                    //         'return_state' => $order->estado_devolucion,
                    //         'return_cost' => $order->users->vendedores->costo_devolucion,
                    //         'origin' => 'Pedido ' . 'NOVEDAD',
                    //         'previous_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0,
                    //         'current_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value + $existingTransaction->total_transaction : $existingTransaction->total_transaction,
                    //     ]);
                    // }
                }
                $message = "transacción con debito por devolucion";
            } else {
                $message = "transacción sin debito por devolucion";
            }

            $order->status = "NOVEDAD";
            $order->status_last_modified_at = date('Y-m-d H:i:s');
            $order->status_last_modified_by = $data['generated_by'];
            $order->comentario = $data['comentario'];
            $order->fecha_entrega = now()->format('j/n/Y');
            if ($order->novedades == []) {
                $order->fecha_entrega = now()->format('j/n/Y');
            }

            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "status",
                "status" => "NOVEDAD",
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => $data["comentario"],
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($order->status_history === null || $order->status_history === '[]') {
                $order->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($order->status_history, true);

                $existingHistory[] = $newHistory;

                $order->status_history = json_encode($existingHistory);
            }

            $order->save();

            // error_log("delete from tpt");

            // // * if it exists, delete from transaccion_pedidos_transportadora
            $idTransportadora = $order['transportadora'][0]['id'];
            $fechaEntrega = now()->format('j/n/Y');

            $transaccion = TransaccionPedidoTransportadora::where('id_pedido', $id)
                ->where('id_transportadora', $idTransportadora)
                ->where('fecha_entrega', $fechaEntrega)
                ->get();

            $transaccionFound = $transaccion->first();

            if ($transaccionFound !== null) {
                error_log($transaccionFound->id);
                $transaccionFound->delete();
                //     error_log("deleted");

            }




            DB::commit();

            return response()->json([
                "res" => $message,
                // "pedido" => $order
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function paymentOrderOperatorInOffice(Request $request, $id)
    {
        DB::beginTransaction();
        $message = "";
        $repetida = null;

        try {
            $data = $request->json()->all();

            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
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


            $order->estado_devolucion = "ENTREGADO EN OFICINA";
            $order->do = "ENTREGADO EN OFICINA";
            $order->marca_t_d = date("d/m/Y H:i");
            $order->received_by = $data['generated_by'];

            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "estado_devolucion",
                "status" => "ENTREGADO EN OFICINA",
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => "",
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($order->status_history === null || $order->status_history === '[]') {
                $order->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($order->status_history, true);

                $existingHistory[] = $newHistory;

                $order->status_history = json_encode($existingHistory);
            }

            if ($order->status == "NOVEDAD") {


                // ! integracion

                // Verificar si ya existe una transacción global para este pedido y vendedor
                $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                    ->where('id_seller', $order->users[0]->vendedores[0]->id_master)

                    ->first();

                // Obtener la transacción global previa
                $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                    ->orderBy('id', 'desc')
                    ->first();

                $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                if ($existingTransaction != null) {
                    $existingTransaction->return_state = $order->estado_devolucion;
                    $existingTransaction->save();
                }

                if ($order->costo_devolucion == null) { // Verifica si está vacío convirtiendo a un array
                    $order->costo_devolucion = $order->users[0]->vendedores[0]->costo_devolucion;

                    $newSaldo = $order->users[0]->vendedores[0]->saldo - $order->users[0]->vendedores[0]->costo_devolucion;

                    $newTrans = new Transaccion();
                    $newTrans->tipo = "debit";
                    $newTrans->monto = $order->users[0]->vendedores[0]->costo_devolucion;
                    $newTrans->valor_actual = $newSaldo;
                    $newTrans->valor_anterior = $order->users[0]->vendedores[0]->saldo;
                    $newTrans->marca_de_tiempo = new DateTime();
                    $newTrans->id_origen = $order->id;
                    $newTrans->codigo = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                    $newTrans->origen = "devolucion";
                    $newTrans->comentario = "Costo de devolución desde operador por pedido en " . $order->status . " y " . $order->estado_devolucion;
                    $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                    $newTrans->state = 1;
                    $newTrans->generated_by = $data['generated_by'];

                    $this->transaccionesRepository->create($newTrans);
                    $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);



                    if ($existingTransaction == null) {
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
                        $newTransactionGlobal->return_cost = -$order->users[0]->vendedores[0]->costo_devolucion;
                        $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
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
                        $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_return_cost = 0;
                        $newTransactionGlobal->save();

                        $message = "Transacción con débito por estado " . $order->status;
                    }
                    // else {
                    //     $existingTransaction->update([
                    //         'status' => 'NOVEDAD',
                    //         'return_state' => $order->estado_devolucion,
                    //         'return_cost' => $order->users->vendedores->costo_devolucion,
                    //         'origin' => 'Pedido ' . 'NOVEDAD',
                    //         'previous_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0,
                    //         'current_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value + $existingTransaction->total_transaction : $existingTransaction->total_transaction,
                    //     ]);
                    // }

                    $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion;
                } else {
                    $message = "Transacción ya cobrada";
                }
            } else {
                $message = "Transacción sin débito por estado" . $order->status . " y " . $order->estado_devolucion;
            }
            $order->save();
            DB::commit();

            return response()->json([
                "res" => $message,
                "transaccion_repetida" => $repetida
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function paymentLogisticInWarehouse(Request $request, $id)
    {
        DB::beginTransaction();
        $message = "";
        $repetida = null;

        try {
            $data = $request->json()->all();
            // $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades', 'product.warehouses', 'pedidoCarrierCity.carrierCosts',])->find($id);
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


            $order->estado_devolucion = "EN BODEGA";
            $order->dl = "EN BODEGA";
            $order->marca_t_d_l = date("d/m/Y H:i");
            $order->received_by = $data['generated_by'];

            $nombreComercial = $order->users[0]->vendedores[0]->nombre_comercial;
            $codigo_order = $nombreComercial . "-" . $order->numero_orden;
            // ! suma stock  cuando pedido ya se encuentra "EN BODEGA" JP
            $productController = new ProductAPIController();

            // $searchResult = $productController->updateProductVariantStockInternal(
            //     $order->cantidad_total,
            //     $order->sku,
            //     1,
            //     $order->id_comercial,
            // );

            if ($order->variant_details != null) {
                $searchResult = $productController->updateProductVariantStockInternal(
                    $order->variant_details,
                    1,
                    $order->id_comercial,
                    $order->id,
                    $codigo_order,
                );
            }

            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "estado_devolucion",
                "status" => "EN BODEGA",
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => "",
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($order->status_history === null || $order->status_history === '[]') {
                $order->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($order->status_history, true);

                $existingHistory[] = $newHistory;

                $order->status_history = json_encode($existingHistory);
            }

            // !

            if ($order->status == "NOVEDAD") {
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
                    $existingTransaction->return_state = 'EN BODEGA';
                    $existingTransaction->save();
                }

                if ($order->costo_devolucion == null) { // Verifica si está vacío convirtiendo a un array

                    if (empty($order->pedidoCarrierCity)) {
                        $order->costo_devolucion = $order->users[0]->vendedores[0]->costo_devolucion;

                        $newSaldo = $order->users[0]->vendedores[0]->saldo - $order->users[0]->vendedores[0]->costo_devolucion;

                        $newTrans = new Transaccion();
                        $newTrans->tipo = "debit";
                        $newTrans->monto = $order->users[0]->vendedores[0]->costo_devolucion;
                        $newTrans->valor_actual = $newSaldo;
                        $newTrans->valor_anterior = $order->users[0]->vendedores[0]->saldo;
                        $newTrans->marca_de_tiempo = new DateTime();
                        $newTrans->id_origen = $order->id;
                        $newTrans->codigo = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                        $newTrans->origen = "devolucion";
                        $newTrans->comentario = "Costo de devolución desde logística por pedido en " . $order->status . " y " . $order->estado_devolucion;
                        $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                        $newTrans->state = 1;
                        $newTrans->generated_by = $data['generated_by'];
                        $this->transaccionesRepository->create($newTrans);
                        $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);


                        if ($existingTransaction == null) {
                            $newTransactionGlobal = new TransaccionGlobal();

                            $newTransactionGlobal->admission_date = $marcaT;
                            $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                            $newTransactionGlobal->status = $order->status;
                            $newTransactionGlobal->return_state = 'EN BODEGA';
                            // $newTransactionGlobal->return_state = null;
                            $newTransactionGlobal->id_order = $order->id;
                            $newTransactionGlobal->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                            $newTransactionGlobal->origin = 'Pedido ' . 'NOVEDAD';
                            $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                            $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                            $newTransactionGlobal->return_cost = -$order->users[0]->vendedores[0]->costo_devolucion;
                            $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
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
                            $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                            $newTransactionGlobal->external_return_cost = 0;
                            $newTransactionGlobal->save();

                            $message = "Transacción con débito por estado " . $order->status;
                        }

                        $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion;
                    } else {
                        // El campo pedidoCarrier no está vacío
                        error_log("El campo pedidoCarrierCity tiene datos.");

                        $warehouses = $order['product']['warehouses'];
                        $prov_origen = $this->getWarehouseProv($warehouses);

                        $orderData = json_decode($order, true);
                        $prov_destiny = $orderData["pedido_carrier_city"][0]["city_external"]['id_provincia'];
                        $city_destiny = $orderData["pedido_carrier_city"][0]["city_external"]['id'];

                        // error_log("prov_destiny: $prov_destiny");
                        // error_log("city_destiny: $city_destiny");

                        $carrierExternal = $orderData["pedido_carrier_city"][0]["carrier_costs"];
                        $costs = json_decode($carrierExternal['costs'], true);
                        $costo_seguro = $costs['costo_seguro'];

                        $city_type = CarrierCoverage::where('id_carrier', 1)->where('id_coverage', $city_destiny)->first();
                        $coverage_type = $city_type['type'];

                        $iva = 0.15; //15%
                        $costo_easy = 2.3;

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
                        $pedidoCarrier = PedidosShopifiesCarrierExternalLink::where('pedidos_shopify_id', $id)->first();
                        $pedidoCarrier->cost_refound_external = round(((float)$refound_transp), 2);
                        $pedidoCarrier->save();



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

                        $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion; //creo
                    }
                } else {
                    $message = "Transacción ya cobrada";
                }
            } else {
                $message = "Transacción sin débito por estado" . $order->status . " y " . $order->estado_devolucion;
            }
            $order->save();

            DB::commit();

            return response()->json([
                "res" => $message,
                "transaccion_repetida" => $repetida
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function paymentTransportByReturnStatus(Request $request, $id)
    {
        DB::beginTransaction();
        $message = "";
        $repetida = null;

        try {
            $data = $request->json()->all();
            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
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



            if ($data["return_status"] == "ENTREGADO EN OFICINA") {
                $order->estado_devolucion = $data["return_status"];
                $order->dt = $data["return_status"];
                $order->marca_t_d = date("d/m/Y H:i");
                $order->received_by = $data['generated_by'];
            }
            if ($data["return_status"] == "DEVOLUCION EN RUTA") {
                $order->estado_devolucion = $data["return_status"];
                $order->dt = $data["return_status"];
                $order->marca_t_d_t = date("d/m/Y H:i");
                $order->received_by = $data['generated_by'];
            }


            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "estado_devolucion",
                "status" => $data["return_status"],
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => "",
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($order->status_history === null || $order->status_history === '[]') {
                $order->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($order->status_history, true);

                $existingHistory[] = $newHistory;

                $order->status_history = json_encode($existingHistory);
            }

            if ($order->status == "NOVEDAD") {

                // ! integracion

                // Verificar si ya existe una transacción global para este pedido y vendedor
                $existingTransaction = TransaccionGlobal::where('id_order', $order->id)
                    ->where('id_seller', $order->users[0]->vendedores[0]->id_master)

                    ->first();

                // Obtener la transacción global previa
                $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $order->users[0]->vendedores[0]->id_master)
                    ->orderBy('id', 'desc')
                    ->first();

                $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                if ($existingTransaction != null) {
                    $existingTransaction->return_state = $data["return_status"];
                    $existingTransaction->save();
                }

                if ($order->costo_devolucion == null) { // Verifica si está vacío convirtiendo a un array
                    $order->costo_devolucion = $order->users[0]->vendedores[0]->costo_devolucion;

                    $newSaldo = $order->users[0]->vendedores[0]->saldo - $order->users[0]->vendedores[0]->costo_devolucion;

                    $newTrans = new Transaccion();
                    $newTrans->tipo = "debit";
                    $newTrans->monto = $order->users[0]->vendedores[0]->costo_devolucion;
                    $newTrans->valor_actual = $newSaldo;
                    $newTrans->valor_anterior = $order->users[0]->vendedores[0]->saldo;
                    $newTrans->marca_de_tiempo = new DateTime();
                    $newTrans->id_origen = $order->id;
                    $newTrans->codigo = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                    $newTrans->origen = "devolucion";
                    $newTrans->comentario = "Costo de devolución desde transportadora por pedido en " . $order->status . " y " . $order->estado_devolucion;
                    $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                    $newTrans->state = 1;
                    $newTrans->generated_by = $data['generated_by'];

                    $this->transaccionesRepository->create($newTrans);
                    $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);



                    if ($existingTransaction == null) {
                        $newTransactionGlobal = new TransaccionGlobal();

                        $newTransactionGlobal->admission_date = $marcaT;
                        $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                        $newTransactionGlobal->status = $order->status;
                        $newTransactionGlobal->return_state = $data["return_status"];
                        // $newTransactionGlobal->return_state = null;
                        $newTransactionGlobal->id_order = $order->id;
                        $newTransactionGlobal->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                        $newTransactionGlobal->origin = 'Pedido ' . 'NOVEDAD';
                        $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                        $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                        $newTransactionGlobal->return_cost = -$order->users[0]->vendedores[0]->costo_devolucion;
                        $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
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
                        $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_return_cost = 0;
                        $newTransactionGlobal->save();

                        $message = "Transacción con débito por estado " . $order->status;
                    }
                    // else {
                    //     $existingTransaction->update([
                    //         'status' => 'NOVEDAD',
                    //         'return_state' => $order->estado_devolucion,
                    //         'return_cost' => $order->users->vendedores->costo_devolucion,
                    //         'origin' => 'Pedido ' . 'NOVEDAD',
                    //         'previous_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0,
                    //         'current_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value + $existingTransaction->total_transaction : $existingTransaction->total_transaction,
                    //     ]);
                    // }






                    $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion;
                } else {
                    $message = "Transacción sin débito, ya ha sido cobrada";
                }
            } else {
                $message = "Transacción sin débito por estado " . $order->status . " y " . $order->estado_devolucion;
            }
            $order->save();

            DB::commit();

            return response()->json([
                "res" => $message,
                "transaccion_repetida" => $repetida,
                "pedido" => $order
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function paymentLogisticByReturnStatus(Request $request, $id)
    {
        DB::beginTransaction();
        $message = "";
        $repetida = null;

        try {
            $data = $request->json()->all();
            // $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades', 'product.warehouses', 'pedidoCarrierCity.carrierCosts',])->find($id);
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



            // Verificar si ya existe una transacción global para este pedido y vendedor
            // $ETransG = TransaccionGlobal::where('id_order', $id)->first();

            if ($data["return_status"] == "ENTREGADO EN OFICINA") {
                $order->estado_devolucion = $data["return_status"];
                $order->do = $data["return_status"];
                $order->marca_t_d = date("d/m/Y H:i");
                $order->received_by = $data['generated_by'];

                // ! update transaction-global
                // $ETransG->return_state = 'ENTREGADO EN OFICINA';
                // $ETransG->save();
            }
            if ($data["return_status"] == "EN BODEGA") {
                $order->estado_devolucion = $data["return_status"];
                $order->dl = $data["return_status"];
                $order->marca_t_d_l = date("d/m/Y H:i");
                $order->received_by = $data['generated_by'];

                // ! update transaction-global
                // $ETransG->return_state = 'EN BODEGA';
                // $ETransG->save();
            }


            //new column
            $user = UpUser::where('id', $data['generated_by'])->first();
            $username = $user ? $user->username : null;

            $newHistory = [
                "area" => "estado_devolucion",
                "status" => $data["return_status"],
                "timestap" => date('Y-m-d H:i:s'),
                "comment" => "",
                "path" => "",
                "generated_by" => $data['generated_by'] . "_" . $username
            ];

            if ($order->status_history === null || $order->status_history === '[]') {
                $order->status_history = json_encode([$newHistory]);
            } else {
                $existingHistory = json_decode($order->status_history, true);

                $existingHistory[] = $newHistory;

                $order->status_history = json_encode($existingHistory);
            }

            if ($order->status == "NOVEDAD") {


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
                    $existingTransaction->return_state = $data["return_status"];
                    $existingTransaction->save();
                }

                if ($order->costo_devolucion == null) { // Verifica si está vacío convirtiendo a un array

                    if (empty($order->pedidoCarrierCity)) {

                        $order->costo_devolucion = $order->users[0]->vendedores[0]->costo_devolucion;

                        $newSaldo = $order->users[0]->vendedores[0]->saldo - $order->users[0]->vendedores[0]->costo_devolucion;

                        $newTrans = new Transaccion();
                        $newTrans->tipo = "debit";
                        $newTrans->monto = $order->users[0]->vendedores[0]->costo_devolucion;
                        $newTrans->valor_actual = $newSaldo;
                        $newTrans->valor_anterior = $order->users[0]->vendedores[0]->saldo;
                        $newTrans->marca_de_tiempo = new DateTime();
                        $newTrans->id_origen = $order->id;
                        $newTrans->codigo = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                        $newTrans->origen = "devolucion";
                        $newTrans->comentario = "Costo de devolución desde logistica por pedido en " . $order->status . " y " . $order->estado_devolucion;
                        $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                        $newTrans->state = 1;
                        $newTrans->generated_by = $data['generated_by'];

                        $this->transaccionesRepository->create($newTrans);
                        $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);


                        if ($existingTransaction  == null) {
                            $newTransactionGlobal = new TransaccionGlobal();

                            $newTransactionGlobal->admission_date = $marcaT;
                            $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                            $newTransactionGlobal->status = $order->status;
                            $newTransactionGlobal->return_state = $data["return_status"];
                            // $newTransactionGlobal->return_state = null;
                            $newTransactionGlobal->id_order = $order->id;
                            $newTransactionGlobal->code = $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden;
                            $newTransactionGlobal->origin = 'Pedido ' . 'NOVEDAD';
                            $newTransactionGlobal->withdrawal_price = 0; // Ajusta según necesites
                            $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                            $newTransactionGlobal->return_cost = -$order->users[0]->vendedores[0]->costo_devolucion;
                            $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
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
                            $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                            $newTransactionGlobal->external_return_cost = 0;
                            $newTransactionGlobal->save();

                            $message = "Transacción con débito por estado " . $order->status;
                        }
                        // else {
                        //     $existingTransaction->update([
                        //         'status' => 'NOVEDAD',
                        //         'return_state' => $order->estado_devolucion,
                        //         'return_cost' => $order->users->vendedores->costo_devolucion,
                        //         'origin' => 'Pedido ' . 'NOVEDAD',
                        //         'previous_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0,
                        //         'current_value' => $previousTransactionGlobal ? $previousTransactionGlobal->current_value + $existingTransaction->total_transaction : $existingTransaction->total_transaction,
                        //     ]);
                        // }


                        $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion;
                    } else {
                        // El campo pedidoCarrier no está vacío
                        error_log("El campo pedidoCarrierCity tiene datos.");

                        $warehouses = $order['product']['warehouses'];
                        $prov_origen = $this->getWarehouseProv($warehouses);

                        $orderData = json_decode($order, true);
                        $prov_destiny = $orderData["pedido_carrier_city"][0]["city_external"]['id_provincia'];
                        $city_destiny = $orderData["pedido_carrier_city"][0]["city_external"]['id'];

                        // error_log("prov_destiny: $prov_destiny");
                        // error_log("city_destiny: $city_destiny");

                        $carrierExternal = $orderData["pedido_carrier_city"][0]["carrier_costs"];
                        $costs = json_decode($carrierExternal['costs'], true);
                        $costo_seguro = $costs['costo_seguro'];

                        $city_type = CarrierCoverage::where('id_carrier', 1)->where('id_coverage', $city_destiny)->first();
                        $coverage_type = $city_type['type'];

                        $iva = 0.15; //15%
                        $costo_easy = 2.3;

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
                        $pedidoCarrier = PedidosShopifiesCarrierExternalLink::where('pedidos_shopify_id', $id)->first();
                        $pedidoCarrier->cost_refound_external = round(((float)$refound_transp), 2);
                        $pedidoCarrier->save();


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

                        $message = "Transacción con débito por estado " . $order->status . " y " . $order->estado_devolucion; //creo
                    }
                } else {
                    $message = "Transacción sin débito, ya ha sido cobrada";
                }
            } else {
                $message = "Transacción sin débito por estado " . $order->status . " y " . $order->estado_devolucion;
            }
            $order->save();

            DB::commit();

            return response()->json([
                "res" => $message,
                "transaccion_repetida" => $repetida,
                "pedido" => $order
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFieldTime(Request $request, $id)
    {
        $data = $request->all();

        $keyvalue = $data['keyvalue'];

        // $key = $data['key'];
        // $value = $data['value'];
        $idUser = $data['iduser'];
        $from = $data['from'];
        $datarequest = $data['datarequest'];

        $parts = explode(":", $keyvalue);
        if (count($parts) === 2) {
            $key = $parts[0];
            $value = $parts[1];
        }

        $currentDateTime = date('Y-m-d H:i:s');
        // "${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year}"
        $date = now()->format('j/n/Y');
        //"${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year} ${DateTime.now().hour}:${DateTime.now().minute} ";
        $currentDateTimeText = date("d/m/Y H:i");

        $pedido = PedidosShopify::findOrFail($id);
        if ($key == "estado_logistico") {
            if ($value == "IMPRESO") {  //from log,sell
                $pedido->estado_logistico = $value;
                $pedido->printed_at = $currentDateTime;
                $pedido->printed_by = $idUser;
            }
            if ($value == "ENVIADO") {  //from log,sell
                $pedido->estado_logistico = $value;
                $pedido->sent_at = $currentDateTime;
                $pedido->sent_by = $idUser;
                $pedido->marca_tiempo_envio = $date;
                $pedido->estado_interno = "CONFIRMADO";
                $pedido->fecha_entrega = $date;
            }
        }
        if ($key == "estado_devolucion") {
            if ($value == "EN BODEGA") { //from logistic
                $pedido->estado_devolucion = $value;
                $pedido->dl = $value;
                $pedido->marca_t_d_l = $currentDateTimeText;
                $pedido->received_by = $idUser;
            }
            if ($from == "carrier") {
                if ($value == "ENTREGADO EN OFICINA") {
                    $pedido->estado_devolucion = $value;
                    $pedido->dt = $value;
                    $pedido->marca_t_d = $currentDateTimeText;
                    $pedido->received_by = $idUser;
                }
                if ($value == "DEVOLUCION EN RUTA") {
                    $pedido->estado_devolucion = $value;
                    $pedido->dt = $value;
                    $pedido->marca_t_d_t = $currentDateTimeText;
                    $pedido->received_by = $idUser;
                }
            } elseif ($from == "operator") {
                if ($value == "ENTREGADO EN OFICINA") { //from operator, logistica
                    $pedido->estado_devolucion = $value;
                    $pedido->do = $value;
                    $pedido->marca_t_d = $currentDateTimeText;
                    $pedido->received_by = $idUser;
                }
            }
        }


        if ($key == "status") {
            if ($value != "NOVEDAD_date") {
                $pedido->status = $value;
            }
            $pedido->fill($datarequest);
            if ($value == "ENTREGADO" || $value == "NO ENTREGADO") {
                $pedido->fecha_entrega = $date;
            }
            if ($value == "NOVEDAD_date") {
                $pedido->status = "NOVEDAD";
                $pedido->fecha_entrega = $date;
            }
            $pedido->status_last_modified_at = $currentDateTime;
            $pedido->status_last_modified_by = $idUser;
        }

        //v0
        if ($key == "estado_interno") {
            $pedido->confirmed_by = $idUser;
            $pedido->confirmed_at = $currentDateTime;
        }

        $pedido->save();
        return response()->json([$pedido], 200);
    }
    public function cleanTransactionsFailed($id)
    {
        $transaccions = Transaccion::where("id_origen", $id)->where('state', '1')->whereNot("origen", "reembolso")->get();
        foreach ($transaccions as $transaction) {
            if ($transaction->state == 1) {
                $vendedor = UpUser::find($transaction->id_vendedor)->vendedores;
                if ($transaction->tipo == "credit") {
                    $vendedor[0]->saldo = $vendedor[0]->saldo - $transaction->monto;
                }
                if ($transaction->tipo == "debit") {
                    $vendedor[0]->saldo = $vendedor[0]->saldo + $transaction->monto;
                }

                $this->vendedorRepository->update($vendedor[0]->saldo, $vendedor[0]->id);
                $transaction->delete();
            }
        }
        return response()->json("ok");
    }
    public function getTransactionsById($id)
    {
        $transaccions = Transaccion::where("id_vendedor", $id)->orderBy('id', 'desc')->get();

        return response()->json($transaccions);
    }

    public function getTransactions(Request $request)
    {
        $data = $request->json()->all();
        $startDate = Carbon::parse($data['start'] . " 00:00:00");
        $endDate = Carbon::parse($data['end'] . " 23:59:59");

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $searchTerm = $data['search'];

        if ($searchTerm != "") {
            $filteFields = $data['or']; // && SOLO QUITO  ((||)&&())
        } else {
            $filteFields = [];
        }

        // ! *************************************
        $and = $data['and'];
        $not = $data['not'];
        // ! *************************************
        // ! ordenamiento ↓
        $orderBy = null;
        if (isset($data['sort'])) {
            $sort = $data['sort'];
            $sortParts = explode(':', $sort);
            if (count($sortParts) === 2) {
                $field = $sortParts[0];
                $direction = strtoupper($sortParts[1]) === 'DESC' ? 'DESC' : 'ASC';
                $orderBy = [$field => $direction];
            }
        }

        // ! *************************************

        $transactions = Transaccion::with(['user']);

        $transactions->whereRaw("marca_de_tiempo BETWEEN ? AND ?", [$startDate, $endDate]);

        $transactions->where(function ($transactions) use ($searchTerm, $filteFields) {
            foreach ($filteFields as $field) {
                if (strpos($field, '.') !== false) {
                    $relacion = substr($field, 0, strpos($field, '.'));
                    $propiedad = substr($field, strpos($field, '.') + 1);
                    $this->recursiveWhereHas($transactions, $relacion, $propiedad, $searchTerm);
                } else {
                    $transactions->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                }
            }
        })
            ->where((function ($transactions) use ($and) {
                foreach ($and as $condition) {
                    foreach ($condition as $key => $valor) {
                        $parts = explode("/", $key);
                        $type = $parts[0];
                        $filter = $parts[1];
                        if (strpos($filter, '.') !== false) {
                            $relacion = substr($filter, 0, strpos($filter, '.'));
                            $propiedad = substr($filter, strpos($filter, '.') + 1);
                            $this->recursiveWhereHas($transactions, $relacion, $propiedad, $valor);
                        } else {
                            if ($type == "equals") {
                                $transactions->where($filter, '=', $valor);
                            } else {
                                $transactions->where($filter, 'LIKE', '%' . $valor . '%');
                            }
                        }
                    }
                }
            }))
            ->where((function ($transactions) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($transactions, $relacion, $propiedad, $valor);
                        } else {
                            $transactions->where($key, '!=', $valor);
                        }
                    }
                }
            }));
        // ! Ordena
        if ($orderBy !== null) {
            $transactions->orderBy(key($orderBy), reset($orderBy));
        }
        // ! **************************************************
        $transactions = $transactions->paginate($pageSize, ['*'], 'page', $pageNumber);

        return response()->json($transactions);
    }

    public function getTransactionToRollback($id)
    {
        $transaccion = Transaccion::where("id_origen", $id)->where('state', '1')->whereNot("origen", "reembolso")->get();



        return response()->json($transaccion);
    }

    public function getTransactionGlobalToRollback($id)
    {
        $transaccion = TransaccionGlobal::where("id_order", $id)->where('state', '1')->whereNot("status", "ROLLBACK")->get();
        return response()->json($transaccion);
    }

    // public function rollbackTransaction(Request $request)
    // {
    //     DB::beginTransaction();


    //     $data = $request->json()->all();
    //     $generated_by = $data['generated_by'];

    //     $ids = $data['ids'];
    //     $idOrigen = $data["id_origen"];
    //     $reqTrans = [];
    //     $reqPedidos = [];

    //     $firstIdTransaction = $ids[0];
    //     $transactionFounded = Transaccion::where("id", $firstIdTransaction)->first();
    //     $idTransFounded = $transactionFounded->id_origen;
    //     $providerTransaction = ProviderTransaction::where("origin_id", $idTransFounded)->first();
    //     // $totalIds = count($ids);

    //     // $shouldProcessProviderTransaction = $totalIds == 3 || $totalIds == 6;
    //     // $shouldProcessProviderTransaction = $totalIds == 3;
    //     $shouldProcessProviderTransaction = $providerTransaction != null && $providerTransaction->state == 1;


    //     try {
    //         //code...
    //         $transaction = null;

    //         foreach ($ids as $id) {

    //             $transaction = Transaccion::where("id", $id)->first();

    //             if ($transaction->origen == "retiro") {
    //                 $retiro = OrdenesRetiro::find($transaction->id_origen);
    //                 $retiro->estado = "RECHAZADO";

    //                 if ($transaction->tipo == "debit") {
    //                     $this->CreditLocal(
    //                         $transaction->id_vendedor,
    //                         $transaction->monto,
    //                         $transaction->id_origen,
    //                         $transaction->codigo,
    //                         "reembolso",
    //                         "reembolso por retiro cancelado",
    //                         $generated_by
    //                     );
    //                 }
    //             } else {

    //                 $order = PedidosShopify::find($transaction->id_origen);
    //                 if ($order->status != "PEDIDO PROGRAMADO") {
    //                     $order->status = "PEDIDO PROGRAMADO";
    //                     $order->estado_devolucion = "PENDIENTE";
    //                     $order->costo_devolucion = null;
    //                     $order->costo_envio = null;
    //                     $order->costo_transportadora = null;
    //                     $order->estado_interno = "PENDIENTE";
    //                     $order->estado_logistico = "PENDIENTE";
    //                     $order->estado_pagado = "PENDIENTE";
    //                     $order->save();
    //                 }



    //                 array_push($reqTrans, $transaction);
    //                 $pedido = PedidosShopify::where("id", $transaction->id_origen)->first();

    //                 if ($transaction->state == 1) {

    //                     array_push($reqPedidos, $pedido);

    //                     $vendedor = UpUser::find($transaction->id_vendedor)->vendedores;
    //                     if ($transaction->tipo == "credit") {
    //                         $this->DebitLocal(
    //                             $transaction->id_vendedor,
    //                             $transaction->monto,
    //                             $transaction->id_origen,
    //                             $transaction->codigo,
    //                             "reembolso",
    //                             "reembolso por restauracion de pedido",
    //                             $generated_by
    //                         );
    //                     }
    //                     if ($transaction->tipo == "debit") {
    //                         $this->CreditLocal(
    //                             $transaction->id_vendedor,
    //                             $transaction->monto,
    //                             $transaction->id_origen,
    //                             $transaction->codigo,
    //                             "reembolso",
    //                             "reembolso por restauracion de pedido",
    //                             $generated_by
    //                         );
    //                     }
    //                     $transaction->state = 0;
    //                     $transaction->save();
    //                     $this->vendedorRepository->update($vendedor[0]->saldo, $vendedor[0]->id);
    //                 }
    //             }
    //         }
    //         if ($shouldProcessProviderTransaction) {
    //             if (isset($transaction)) { // Verifica si $transaction está definida
    //                 $idOriginOfTransaction = $transaction->id_origen;

    //                 $providerTransaction = ProviderTransaction::where("origin_id", $idOriginOfTransaction)->first();

    //                 if ($providerTransaction && $providerTransaction->state == 1) {
    //                     // error_log("$transaction->id_vendedor");
    //                     $productId = substr($providerTransaction->origin_code, strrpos($providerTransaction->origin_code, 'C') + 1);

    //                     // Buscar el producto por ID
    //                     $product = Product::with('warehouse')->find($productId);

    //                     // if ($product === null) {
    //                     //     DB::commit();
    //                     //     return ["total" => null, "valor_producto" => null, "error" => "Product Not Found!"];
    //                     // }

    //                     $providerId = $product->warehouse->provider_id;

    //                     $user = Provider::with("user")->where("id", $providerId)->first();
    //                     $userId = $user->user->id;

    //                     error_log("1.$providerTransaction->origin_id");
    //                     error_log("2.$providerTransaction->origin_code");
    //                     error_log("3.$userId");
    //                     error_log("4.$providerTransaction->amount");

    //                     $this->DebitLocalProvider(
    //                         $providerTransaction->origin_id,
    //                         $providerTransaction->origin_code,
    //                         $userId,
    //                         $providerTransaction->amount,
    //                         "Restauracion",
    //                         "Restauracion de Guia",
    //                         "RESTAURACION",
    //                         "Restauracion de Valores de Guia",
    //                         $generated_by
    //                     );
    //                     $providerTransaction->state = 0;
    //                     $providerTransaction->save();
    //                 }
    //             }
    //         }


    //         //  *
    //         $transactionOrderCarrier = TransaccionPedidoTransportadora::where("id_pedido", $idOrigen)->first();
    //         // error_log("transactionOrderCarrier");
    //         if ($transactionOrderCarrier != null) {
    //             error_log("exist data tpt");
    //             // error_log("delete from tpt $transactionOrderCarrier->id");
    //             $deliveredDate = $transactionOrderCarrier->fecha_entrega;
    //             $tptCarrierId = $transactionOrderCarrier->id_transportadora;
    //             $tpt = new TransaccionPedidoTransportadoraAPIController();
    //             $tpt->destroy($transactionOrderCarrier->id);
    //             // error_log("**** need to recal tsc ****");
    //             $dateFormatted = Carbon::createFromFormat('j/n/Y', $deliveredDate)->format('Y-m-d');

    //             $transportadoraShippingCost = TransportadorasShippingCost::where('id_transportadora', $tptCarrierId)
    //                 ->whereDate('time_stamp', $dateFormatted)
    //                 ->first();
    //             // error_log("upt from tsc $transportadoraShippingCost");

    //             if ($transportadoraShippingCost != null) {
    //                 // error_log("exists data transShippingCost");
    //                 $tsc = new TransportadorasShippingCostAPIController();
    //                 $tsc->recalculateValues($transportadoraShippingCost->id, $deliveredDate, $tptCarrierId);
    //                 // error_log("updated data transShippingCost");
    //             } else {
    //                 error_log("no data tsc");
    //             }
    //         } else {
    //             error_log("no data tpt");
    //         }
    //         //  **
    //         $pedidos = !empty($ids) ? $ids[0] : null;

    //         DB::commit();
    //         return response()->json([
    //             "transacciones" => $transaction,
    //             "pedidos" => $pedidos
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return response()->json([
    //             'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
    //             "req" => $reqTrans
    //         ], 500);
    //     }
    // }


    public function rollbackTransaction(Request $request)
    {
        DB::beginTransaction();


        $data = $request->json()->all();
        $generated_by = $data['generated_by'];

        $ids = $data['ids'];
        $idOrigen = $data["id_origen"];
        $reqTrans = [];
        $reqPedidos = [];

        // if (!empty($ids)) {
        $firstIdTransaction = $ids[0];
        if (!empty($firstIdTransaction)) {

            $transactionFounded = Transaccion::where("id", $firstIdTransaction)->first();
            $idTransFounded = $transactionFounded->id_origen;

            // $providerTransaction = ProviderTransaction::where("origin_id", $idTransFounded)->first();
            $providerTransactions = ProviderTransaction::where("origin_id", $idTransFounded)->get();
            // $totalIds = count($ids);
            error_log("-> pt -> $providerTransactions");
        }
        // ! ↓ esto se usa
        // $shouldProcessProviderTransaction = $providerTransaction != null && $providerTransaction->state == 1;
        // ! deberia dejarle pasar 

        try {
            //code...
            $transaction = null;

            if (empty($firstIdTransaction)) {
                $order = PedidosShopify::find($idOrigen);
                if ($order->status != "PEDIDO PROGRAMADO") {

                    $order->status = "PEDIDO PROGRAMADO";
                    $order->estado_devolucion = "PENDIENTE";
                    // $order->estado_interno = "PENDIENTE";
                    // $order->estado_logistico = "PENDIENTE";

                    $order->costo_devolucion = null;
                    $order->costo_envio = null; //5.5
                    $order->costo_transportadora = null; //2.75
                    $order->value_product_warehouse = null;
                    $order->value_referer = null;
                    $order->costo_operador = null;

                    $order->save();
                }

                // $pedidosShopifyRutaLink = PedidosShopifiesRutaLink::where('pedidos_shopify_id', $order->id)->delete();
                // $pedidosDhopifyTransportadoraLink = PedidosShopifiesTransportadoraLink::where('pedidos_shopify_id', $order->id)->delete();
                // $pedidosDhopifySubrutaLink = PedidosShopifiesSubRutaLink::where('pedidos_shopify_id', $order->id)->delete();
                // $pedidosDhopifyOperadoreLink = PedidosShopifiesOperadoreLink::where('pedidos_shopify_id', $order->id)->delete();


                // if (
                //     $pedidosShopifyRutaLink > 0 &&
                //     $pedidosDhopifyTransportadoraLink > 0 &&
                //     $pedidosDhopifySubrutaLink > 0 &&
                //     $pedidosDhopifyOperadoreLink > 0
                // ) {
                //     error_log("ok! er");
                // }
            } else {
                foreach ($ids as $id) {

                    $transaction = Transaccion::where("id", $id)->first();
                    if ($transaction->origen == "retiro") {
                        $retiro = OrdenesRetiro::find($transaction->id_origen);
                        $retiro->estado = "RECHAZADO";

                        if ($transaction->tipo == "debit") {
                            $this->CreditLocal(
                                $transaction->id_vendedor,
                                $transaction->monto,
                                $transaction->id_origen,
                                $transaction->codigo,
                                "reembolso",
                                "reembolso por retiro cancelado",
                                $generated_by
                            );
                        }
                    } else {

                        $order = PedidosShopify::find($transaction->id_origen);
                        if ($order->status != "PEDIDO PROGRAMADO") {

                            $order->status = "PEDIDO PROGRAMADO";
                            $order->estado_devolucion = "PENDIENTE";
                            // $order->estado_interno = "PENDIENTE";
                            // $order->estado_logistico = "PENDIENTE";

                            $order->costo_devolucion = null;
                            $order->costo_envio = null; //5.5
                            $order->costo_transportadora = null; //2.75
                            $order->value_product_warehouse = null;
                            $order->value_referer = null;
                            $order->costo_operador = null;

                            $order->save();
                        }



                        // $pedidosShopifyRutaLink = PedidosShopifiesRutaLink::where('pedidos_shopify_id', $order->id)->delete();
                        // $pedidosDhopifyTransportadoraLink = PedidosShopifiesTransportadoraLink::where('pedidos_shopify_id', $order->id)->delete();
                        // $pedidosDhopifySubrutaLink = PedidosShopifiesSubRutaLink::where('pedidos_shopify_id', $order->id)->delete();
                        // $pedidosDhopifyOperadoreLink = PedidosShopifiesOperadoreLink::where('pedidos_shopify_id', $order->id)->delete();


                        // if (
                        //     $pedidosShopifyRutaLink > 0 &&
                        //     $pedidosDhopifyTransportadoraLink > 0 &&
                        //     $pedidosDhopifySubrutaLink > 0 &&
                        //     $pedidosDhopifyOperadoreLink > 0
                        // ) {
                        //     error_log("ok! er");
                        // }

                        array_push($reqTrans, $transaction);
                        $pedido = PedidosShopify::where("id", $transaction->id_origen)->first();

                        if ($transaction->state == 1) {

                            array_push($reqPedidos, $pedido);

                            $vendedor = UpUser::find($transaction->id_vendedor)->vendedores;
                            if ($transaction->tipo == "credit") {
                                $this->DebitLocal(
                                    $transaction->id_vendedor,
                                    $transaction->monto,
                                    $transaction->id_origen,
                                    $transaction->codigo,
                                    "reembolso",
                                    "reembolso por restauracion de pedido",
                                    $generated_by
                                );
                            }
                            if ($transaction->tipo == "debit") {
                                $this->CreditLocal(
                                    $transaction->id_vendedor,
                                    $transaction->monto,
                                    $transaction->id_origen,
                                    $transaction->codigo,
                                    "reembolso",
                                    "reembolso por restauracion de pedido",
                                    $generated_by
                                );
                            }
                            $transaction->state = 0;
                            $transaction->save();
                            $this->vendedorRepository->update($vendedor[0]->saldo, $vendedor[0]->id);
                        }
                    }
                }

                $principalTransactionGlobal = TransaccionGlobal::where('id_order', $idOrigen)->orderBy('id', 'desc')->first();
                if ($principalTransactionGlobal->origin == 'Retiro de Efectivo' && $principalTransactionGlobal->status == "APROBADO") {

                    // ! integracion

                    $existingTransaction = TransaccionGlobal::where('origin', 'Retiro de Efectivo')
                        ->where('id_seller', $retiro->id_vendedor)
                        ->where('code', 'Retiro-' . $retiro->id)
                        ->first();

                    // Obtener la transacción global previa
                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $retiro->id_vendedor)
                        ->orderBy('id', 'desc')
                        ->first();

                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;


                    if ($existingTransaction->state == 1) {

                        $existingTransaction->state = 0;
                        $existingTransaction->save();



                        $newTransactionGlobal = new TransaccionGlobal();

                        $newTransactionGlobal->admission_date = now()->format('Y-m-d');
                        // $newTransactionGlobal->delivery_date = $marcaTtransferencia;
                        $newTransactionGlobal->status = "REEMBOLSO";
                        // $newTransactionGlobal->return_state = $order->estado_devolucion;
                        $newTransactionGlobal->return_state = null;
                        $newTransactionGlobal->id_order = $retiro->id;
                        $newTransactionGlobal->code = 'RETIRO' . "-" . $retiro->id;
                        $newTransactionGlobal->origin = 'Retiro de ' . 'Efectivo';
                        // $retiro->monto = str_replace(',', '.', $retiro->monto);
                        $newTransactionGlobal->withdrawal_price = $retiro->monto; // Ajusta según necesites
                        $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                        $newTransactionGlobal->return_cost = 0;
                        $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->total_transaction = $retiro->monto;
                        $newTransactionGlobal->previous_value = $previousValue;
                        $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                        $newTransactionGlobal->state = true;
                        $newTransactionGlobal->id_seller = $retiro->id_vendedor;
                        $newTransactionGlobal->internal_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_return_cost = 0;
                        $newTransactionGlobal->save();
                    }
                } else {

                    $transactionsGlobal = TransaccionGlobal::where('id_order', $pedido->id)->get();
                    $idSeller = $transactionsGlobal->first()->id_seller;

                    // Obtener la última transacción del mismo vendedor
                    $ultimaTransaccion = TransaccionGlobal::where('id_seller', $idSeller)
                        ->orderBy('id', 'desc')
                        ->first();


                    // Actualizar el estado de las transacciones globales existentes a 0
                    foreach ($transactionsGlobal as $transaction) {
                        $transaction->state = 0; // Cambiar el estado a 0  
                        $transaction->save();
                    }

                    foreach ($transactionsGlobal as $transaction) {
                        // Crear una nueva instancia de TransaccionGlobal para la transacción opuesta
                        $newTransaction = $transaction->replicate(); // Clonar la transacción actual

                        $newTransaction->state = 1;
                        $newTransaction->status = 'REEMBOLSO';
                        $newTransaction->value_order = $transaction->value_order != 0 ? -$transaction->value_order : 0;
                        $newTransaction->return_cost = $transaction->return_cost != 0 ? -$transaction->return_cost : 0;
                        $newTransaction->delivery_cost = $transaction->delivery_cost != 0 ? -$transaction->delivery_cost : 0;
                        $newTransaction->notdelivery_cost = $transaction->notdelivery_cost != 0 ? -$transaction->notdelivery_cost : 0;
                        $newTransaction->provider_cost = $transaction->provider_cost != 0 ? -$transaction->provider_cost : 0;
                        $newTransaction->referer_cost = $transaction->referer_cost != 0 ? -$transaction->referer_cost : 0;
                        $newTransaction->total_transaction = $transaction->total_transaction != 0 ? -$transaction->total_transaction : 0;
                        $newTransaction->previous_value = $ultimaTransaccion->current_value;
                        $newTransaction->current_value = $ultimaTransaccion->current_value + $newTransaction->total_transaction;
                        // ! last 3 columns in transactions_global
                        $newTransaction->internal_transportation_cost = $transaction->internal_transportation_cost != 0 ? -$transaction->internal_transportation_cost : 0;
                        $newTransaction->external_transportation_cost = $transaction->external_transportation_cost != 0 ? -$transaction->external_transportation_cost : 0;
                        $newTransaction->external_return_cost = $transaction->external_return_cost != 0 ? -$transaction->external_return_cost : 0;

                        $newTransaction->save();
                    }
                }
            }


            // $providerTransactions
            if (!empty($providerTransactions)) {
                foreach ($providerTransactions as $providerT) {
                    // $shouldProcessProviderTransaction = $providerT != null && $providerT['state'] == 1;

                    // if ($shouldProcessProviderTransaction) {
                    // if (isset($transaction)) { // Verifica si $transaction está definida
                    //     $idOriginOfTransaction = $transaction->id_origen;

                    $providerTransaction = ProviderTransaction::where("origin_id", $providerT->origin_id)
                        ->where('sku_product_reference', $providerT->sku_product_reference)->first();

                    if ($providerTransaction && $providerTransaction->state == 1) {
                        // error_log("$transaction->id_vendedor");
                        $productId = substr($providerTransaction->sku_product_reference, strrpos($providerTransaction->sku_product_reference, 'C') + 1);

                        // Buscar el producto por ID
                        $product = Product::with('warehouse')->find($productId);

                        // if ($product === null) {
                        //     DB::commit();
                        //     return ["total" => null, "valor_producto" => null, "error" => "Product Not Found!"];
                        // }

                        $providerId = $product->warehouse->provider_id;

                        $user = Provider::with("user")->where("id", $providerId)->first();
                        $userId = $user->user->id;

                        error_log("1.$providerTransaction->origin_id");
                        error_log("2.$providerTransaction->origin_code");
                        error_log("3.$userId");
                        error_log("4.$providerTransaction->amount");

                        $this->DebitLocalProvider(
                            $providerTransaction->origin_id,
                            $providerTransaction->origin_code,
                            $userId,
                            $providerTransaction->amount,
                            "Restauracion",
                            "Restauracion de Guia",
                            "RESTAURACION",
                            "Restauracion de Valores de Guia",
                            $generated_by,
                            $providerTransaction->sku_product_reference
                        );
                        $providerTransaction->state = 0;
                        $providerTransaction->save();
                        // }
                        // }
                        // }
                    }
                    // ! ----------------
                    // if ($shouldProcessProviderTransaction) {
                    //     if (isset($transaction)) { // Verifica si $transaction está definida
                    //         $idOriginOfTransaction = $transaction->id_origen;

                    //         $providerTransaction = ProviderTransaction::where("origin_id", $idOriginOfTransaction)->first();

                    //         if ($providerTransaction && $providerTransaction->state == 1) {
                    //             // error_log("$transaction->id_vendedor");
                    //             $productId = substr($providerTransaction->origin_code, strrpos($providerTransaction->origin_code, 'C') + 1);

                    //             // Buscar el producto por ID
                    //             $product = Product::with('warehouse')->find($productId);

                    //             // if ($product === null) {
                    //             //     DB::commit();
                    //             //     return ["total" => null, "valor_producto" => null, "error" => "Product Not Found!"];
                    //             // }

                    //             $providerId = $product->warehouse->provider_id;

                    //             $user = Provider::with("user")->where("id", $providerId)->first();
                    //             $userId = $user->user->id;

                    //             error_log("1.$providerTransaction->origin_id");
                    //             error_log("2.$providerTransaction->origin_code");
                    //             error_log("3.$userId");
                    //             error_log("4.$providerTransaction->amount");

                    //             $this->DebitLocalProvider(
                    //                 $providerTransaction->origin_id,
                    //                 $providerTransaction->origin_code,
                    //                 $userId,
                    //                 $providerTransaction->amount,
                    //                 "Restauracion",
                    //                 "Restauracion de Guia",
                    //                 "RESTAURACION",
                    //                 "Restauracion de Valores de Guia",
                    //                 $generated_by
                    //             );
                    //             $providerTransaction->state = 0;
                    //             $providerTransaction->save();
                    // }
                }
            }

            // ! ----------------


            //  *
            $transactionOrderCarrier = TransaccionPedidoTransportadora::where("id_pedido", $idOrigen)->first();
            // error_log("transactionOrderCarrier");
            if ($transactionOrderCarrier != null) {
                error_log("exist data tpt");
                // error_log("delete from tpt $transactionOrderCarrier->id");
                $deliveredDate = $transactionOrderCarrier->fecha_entrega;
                $tptCarrierId = $transactionOrderCarrier->id_transportadora;
                $tpt = new TransaccionPedidoTransportadoraAPIController();
                $tpt->destroy($transactionOrderCarrier->id);
                // error_log("**** need to recal tsc ****");
                $dateFormatted = Carbon::createFromFormat('j/n/Y', $deliveredDate)->format('Y-m-d');

                $transportadoraShippingCost = TransportadorasShippingCost::where('id_transportadora', $tptCarrierId)
                    ->whereDate('time_stamp', $dateFormatted)
                    ->first();
                // error_log("upt from tsc $transportadoraShippingCost");

                if ($transportadoraShippingCost != null) {
                    // error_log("exists data transShippingCost");
                    $tsc = new TransportadorasShippingCostAPIController();
                    $tsc->recalculateValues($transportadoraShippingCost->id, $deliveredDate, $tptCarrierId);
                    // error_log("updated data transShippingCost");
                } else {
                    error_log("no data tsc");
                }
            } else {
                error_log("no data tpt");
            }
            //  **
            $pedidos = !empty($ids) ? $ids[0] : null;

            DB::commit();
            return response()->json([
                "transacciones" => $transaction,
                "pedidos" => $pedidos
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
                "req" => $reqTrans
            ], 500);
        }
    }

    // public function rollbackTransactionGLobal(Request $request)
    // {
    //     DB::beginTransaction();


    //     $data = $request->json()->all();
    //     $generated_by = $data['generated_by'];

    //     $ids = $data['ids'];
    //     $idOrigen = $data["id_origen"];
    //     $reqTrans = [];
    //     $reqPedidos = [];

    //     // if (!empty($ids)) {
    //     $firstIdTransaction = $ids[0];

    //     if (!empty($firstIdTransaction)) {

    //         $transactionFounded = Transaccion::where("id", $firstIdTransaction)->first();
    //         $idTransFounded = $transactionFounded->id_origen;

    //         // $providerTransaction = ProviderTransaction::where("origin_id", $idTransFounded)->first();
    //         $providerTransactions = ProviderTransaction::where("origin_id", $idTransFounded)->get();
    //         // $totalIds = count($ids);
    //         error_log("-> pt -> $providerTransactions");
    //     }
    //     // ! ↓ esto se usa
    //     // $shouldProcessProviderTransaction = $providerTransaction != null && $providerTransaction->state == 1;
    //     // ! deberia dejarle pasar 

    //     try {
    //         //code...
    //         $transaction = null;

    //         if (empty($firstIdTransaction)) {
    //             $order = PedidosShopify::find($idOrigen);
    //             if ($order->status != "PEDIDO PROGRAMADO") {

    //                 $order->status = "PEDIDO PROGRAMADO";
    //                 $order->estado_devolucion = "PENDIENTE";
    //                 $order->costo_devolucion = null;
    //                 $order->costo_envio = null; //5.5
    //                 $order->costo_transportadora = null; //2.75
    //                 $order->value_product_warehouse = null;
    //                 $order->value_referer = null;
    //                 $order->costo_operador = null;

    //                 $order->save();
    //             }

    //             // $pedidosShopifyRutaLink = PedidosShopifiesRutaLink::where('pedidos_shopify_id', $order->id)->delete();
    //             // $pedidosDhopifyTransportadoraLink = PedidosShopifiesTransportadoraLink::where('pedidos_shopify_id', $order->id)->delete();
    //             // $pedidosDhopifySubrutaLink = PedidosShopifiesSubRutaLink::where('pedidos_shopify_id', $order->id)->delete();
    //             // $pedidosDhopifyOperadoreLink = PedidosShopifiesOperadoreLink::where('pedidos_shopify_id', $order->id)->delete();


    //             // if (
    //             //     $pedidosShopifyRutaLink > 0 &&
    //             //     $pedidosDhopifyTransportadoraLink > 0 &&
    //             //     $pedidosDhopifySubrutaLink > 0 &&
    //             //     $pedidosDhopifyOperadoreLink > 0
    //             // ) {
    //             //     error_log("ok! er");
    //             // }
    //         } else {
    //             foreach ($ids as $id) {

    //                 $transaction = Transaccion::where("id", $id)->first();
    //                 if ($transaction->origen == "retiro") {
    //                     $retiro = OrdenesRetiro::find($transaction->id_origen);
    //                     $retiro->estado = "RECHAZADO";

    //                     if ($transaction->tipo == "debit") {
    //                         $this->CreditLocal(
    //                             $transaction->id_vendedor,
    //                             $transaction->monto,
    //                             $transaction->id_origen,
    //                             $transaction->codigo,
    //                             "reembolso",
    //                             "reembolso por retiro cancelado",
    //                             $generated_by
    //                         );
    //                     }

    //                 } else {

    //                     $order = PedidosShopify::find($transaction->id_origen);
    //                     if ($order->status != "PEDIDO PROGRAMADO") {

    //                         $order->status = "PEDIDO PROGRAMADO";
    //                         $order->estado_devolucion = "PENDIENTE";
    //                         // $order->estado_interno = "PENDIENTE";
    //                         // $order->estado_logistico = "PENDIENTE";

    //                         $order->costo_devolucion = null;
    //                         $order->costo_envio = null; //5.5
    //                         $order->costo_transportadora = null; //2.75
    //                         $order->value_product_warehouse = null;
    //                         $order->value_referer = null;
    //                         $order->costo_operador = null;

    //                         $order->save();
    //                     }



    //                     // $pedidosShopifyRutaLink = PedidosShopifiesRutaLink::where('pedidos_shopify_id', $order->id)->delete();
    //                     // $pedidosDhopifyTransportadoraLink = PedidosShopifiesTransportadoraLink::where('pedidos_shopify_id', $order->id)->delete();
    //                     // $pedidosDhopifySubrutaLink = PedidosShopifiesSubRutaLink::where('pedidos_shopify_id', $order->id)->delete();
    //                     // $pedidosDhopifyOperadoreLink = PedidosShopifiesOperadoreLink::where('pedidos_shopify_id', $order->id)->delete();


    //                     // if (
    //                     //     $pedidosShopifyRutaLink > 0 &&
    //                     //     $pedidosDhopifyTransportadoraLink > 0 &&
    //                     //     $pedidosDhopifySubrutaLink > 0 &&
    //                     //     $pedidosDhopifyOperadoreLink > 0
    //                     // ) {
    //                     //     error_log("ok! er");
    //                     // }

    //                     // array_push($reqTrans, $transaction);
    //                     $pedido = PedidosShopify::where("id", $transaction->id_origen)->first();

    //                     // if ($transaction->state == 1) {

    //                     //     array_push($reqPedidos, $pedido);

    //                     //     $vendedor = UpUser::find($transaction->id_vendedor)->vendedores;
    //                     //     if ($transaction->tipo == "credit") {
    //                     //         $this->DebitLocal(
    //                     //             $transaction->id_vendedor,
    //                     //             $transaction->monto,
    //                     //             $transaction->id_origen,
    //                     //             $transaction->codigo,
    //                     //             "reembolso",
    //                     //             "reembolso por restauracion de pedido",
    //                     //             $generated_by
    //                     //         );
    //                     //     }
    //                     //     if ($transaction->tipo == "debit") {
    //                     //         $this->CreditLocal(
    //                     //             $transaction->id_vendedor,
    //                     //             $transaction->monto,
    //                     //             $transaction->id_origen,
    //                     //             $transaction->codigo,
    //                     //             "reembolso",
    //                     //             "reembolso por restauracion de pedido",
    //                     //             $generated_by
    //                     //         );
    //                     //     }
    //                     //     $transaction->state = 0;
    //                     //     $transaction->save();
    //                     //     $this->vendedorRepository->update($vendedor[0]->saldo, $vendedor[0]->id);
    //                     // }
    //                 }
    //             }

    //             $transactionsGlobal = TransaccionGlobal::where('id_order', $pedido->id)->get();

    //             // Actualizar el estado de las transacciones globales existentes a 0
    //             foreach ($transactionsGlobal as $transaction) {
    //                 $transaction->state = 0; // Cambiar el estado a 0  
    //                 $transaction->save();
    //             }

    //             foreach ($transactionsGlobal as $transaction) {
    //                 // Crear una nueva instancia de TransaccionGlobal para la transacción opuesta
    //                 $newTransaction = $transaction->replicate(); // Clonar la transacción actual

    //                 $newTransaction->status = 1;
    //                 $newTransaction->status = 'ROLLBACK';
    //                 $newTransaction->value_order = $transaction->value_order != 0 ? -$transaction->value_order : 0;
    //                 $newTransaction->return_cost = $transaction->return_cost != 0 ? -$transaction->return_cost : 0;
    //                 $newTransaction->delivery_cost = $transaction->delivery_cost != 0 ? -$transaction->delivery_cost : 0;
    //                 $newTransaction->notdelivery_cost = $transaction->notdelivery_cost != 0 ? -$transaction->notdelivery_cost : 0;
    //                 $newTransaction->provider_cost = $transaction->provider_cost != 0 ? -$transaction->provider_cost : 0;
    //                 $newTransaction->referer_cost = $transaction->referer_cost != 0 ? -$transaction->referer_cost : 0;
    //                 $newTransaction->total_transaction = $transaction->total_transaction != 0 ? -$transaction->total_transaction : 0;
    //                 $newTransaction->previous_value = $transaction->current_value;
    //                 $newTransaction->current_value = $transaction->current_value + $newTransaction->total_transaction;
    //                 // ! last 3 columns in transactions_global
    //                 $newTransaction->internal_transportation_cost = $transaction->internal_transportation_cost != 0 ? -$transaction->internal_transportation_cost : 0;
    //                 $newTransaction->external_transportation_cost = $transaction->external_transportation_cost != 0 ? -$transaction->external_transportation_cost : 0;
    //                 $newTransaction->external_return_cost = $transaction->external_return_cost != 0 ? -$transaction->external_return_cost : 0;

    //                 $newTransaction->save();
    //             }
    //         }


    //         // $providerTransactions
    //         if (!empty($providerTransactions)) {
    //             foreach ($providerTransactions as $providerT) {
    //                 $providerTransaction = ProviderTransaction::where("origin_id", $providerT->origin_id)
    //                     ->where('sku_product_reference', $providerT->sku_product_reference)->first();

    //                 if ($providerTransaction && $providerTransaction->state == 1) {
    //                     $productId = substr($providerTransaction->sku_product_reference, strrpos($providerTransaction->sku_product_reference, 'C') + 1);

    //                     // Buscar el producto por ID
    //                     $product = Product::with('warehouse')->find($productId);

    //                     $providerId = $product->warehouse->provider_id;

    //                     $user = Provider::with("user")->where("id", $providerId)->first();
    //                     $userId = $user->user->id;

    //                     error_log("1.$providerTransaction->origin_id");
    //                     error_log("2.$providerTransaction->origin_code");
    //                     error_log("3.$userId");
    //                     error_log("4.$providerTransaction->amount");

    //                     $this->DebitLocalProvider(
    //                         $providerTransaction->origin_id,
    //                         $providerTransaction->origin_code,
    //                         $userId,
    //                         $providerTransaction->amount,
    //                         "Restauracion",
    //                         "Restauracion de Guia",
    //                         "RESTAURACION",
    //                         "Restauracion de Valores de Guia",
    //                         $generated_by,
    //                         $providerTransaction->sku_product_reference
    //                     );
    //                     $providerTransaction->state = 0;
    //                     $providerTransaction->save();
    //                 }
    //             }
    //         }

    //         $transactionOrderCarrier = TransaccionPedidoTransportadora::where("id_pedido", $idOrigen)->first();
    //         // error_log("transactionOrderCarrier");
    //         if ($transactionOrderCarrier != null) {
    //             error_log("exist data tpt");
    //             // error_log("delete from tpt $transactionOrderCarrier->id");
    //             $deliveredDate = $transactionOrderCarrier->fecha_entrega;
    //             $tptCarrierId = $transactionOrderCarrier->id_transportadora;
    //             $tpt = new TransaccionPedidoTransportadoraAPIController();
    //             $tpt->destroy($transactionOrderCarrier->id);
    //             // error_log("**** need to recal tsc ****");
    //             $dateFormatted = Carbon::createFromFormat('j/n/Y', $deliveredDate)->format('Y-m-d');

    //             $transportadoraShippingCost = TransportadorasShippingCost::where('id_transportadora', $tptCarrierId)
    //                 ->whereDate('time_stamp', $dateFormatted)
    //                 ->first();
    //             // error_log("upt from tsc $transportadoraShippingCost");

    //             if ($transportadoraShippingCost != null) {
    //                 // error_log("exists data transShippingCost");
    //                 $tsc = new TransportadorasShippingCostAPIController();
    //                 $tsc->recalculateValues($transportadoraShippingCost->id, $deliveredDate, $tptCarrierId);
    //                 // error_log("updated data transShippingCost");
    //             } else {
    //                 error_log("no data tsc");
    //             }
    //         } else {
    //             error_log("no data tpt");
    //         }
    //         //  **
    //         $pedidos = !empty($ids) ? $ids[0] : null;

    //         DB::commit();
    //         return response()->json([
    //             "transacciones" => $transaction,
    //             "pedidos" => $pedidos
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return response()->json([
    //             'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
    //             "req" => $reqTrans
    //         ], 500);
    //     }
    // }


    public function pedidoProgramado(Request $request)
    {
        DB::beginTransaction();


        $data = $request->json()->all();
        // $generated_by = $data['generated_by'];

        // $ids = $data['ids'];
        $idOrigen = $data["id_origen"];
        $comentario = $data["comentario"];
        $reqTrans = [];
        $reqPedidos = [];

        // if (!empty($ids)) {
        // $firstIdTransaction = $ids[0];

        // if (!empty($firstIdTransaction)) {

        //     $transactionFounded = Transaccion::where("id", $firstIdTransaction)->first();
        //     $idTransFounded = $transactionFounded->id_origen;

        //     // $providerTransaction = ProviderTransaction::where("origin_id", $idTransFounded)->first();
        //     $providerTransactions = ProviderTransaction::where("origin_id", $idTransFounded)->get();
        //     // $totalIds = count($ids);
        //     error_log("-> pt -> $providerTransactions");
        // }
        // // ! ↓ esto se usa
        // // $shouldProcessProviderTransaction = $providerTransaction != null && $providerTransaction->state == 1;
        // // ! deberia dejarle pasar 

        try {
            //code...
            $transaction = null;

            // if (empty($firstIdTransaction)) {
            $order = PedidosShopify::find($idOrigen);
            if ($order->status != "PEDIDO PROGRAMADO") {

                $order->status = "PEDIDO PROGRAMADO";
                $order->estado_devolucion = "PENDIENTE";
                $order->estado_interno = "PENDIENTE";
                $order->estado_logistico = "PENDIENTE";
                $order->comentario = $comentario;

                $order->costo_devolucion = null;
                $order->costo_envio = null; //5.5
                $order->costo_transportadora = null; //2.75
                $order->value_product_warehouse = null;
                $order->value_referer = null;
                $order->costo_operador = null;


                $order->confirmed_at = null;

                //new column
                $user = UpUser::where('id', $data['generated_by'])->first();
                $username = $user ? $user->username : null;

                $newHistory = [
                    "area" => "status",
                    "status" => "PEDIDO PROGRAMADO",
                    "timestap" => date('Y-m-d H:i:s'),
                    "comment" => "",
                    "path" => "",
                    "generated_by" => $data['generated_by'] . "_" . $username
                ];

                if ($order->status_history === null || $order->status_history === '[]') {
                    $order->status_history = json_encode([$newHistory]);
                } else {
                    $existingHistory = json_decode($order->status_history, true);

                    $existingHistory[] = $newHistory;

                    $order->status_history = json_encode($existingHistory);
                }

                $order->save();
            }

            $pedidosShopifyRutaLink = PedidosShopifiesRutaLink::where('pedidos_shopify_id', $order->id)->delete();
            $pedidosDhopifyTransportadoraLink = PedidosShopifiesTransportadoraLink::where('pedidos_shopify_id', $order->id)->delete();
            $pedidosDhopifySubrutaLink = PedidosShopifiesSubRutaLink::where('pedidos_shopify_id', $order->id)->delete();
            $pedidosDhopifyOperadoreLink = PedidosShopifiesOperadoreLink::where('pedidos_shopify_id', $order->id)->delete();


            if (
                $pedidosShopifyRutaLink > 0 &&
                $pedidosDhopifyTransportadoraLink > 0 &&
                $pedidosDhopifySubrutaLink > 0 &&
                $pedidosDhopifyOperadoreLink > 0
            ) {
                error_log("ok! er");
            }


            $pedidos = !empty($ids) ? $ids[0] : null;

            DB::commit();
            return response()->json([
                "transacciones" => $transaction,
                "pedidos" => $pedidos
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
                "req" => $reqTrans
            ], 500);
        }
    }

    public function debitWithdrawal(Request $request, $id)
    {
        DB::beginTransaction();
        error_log("debitWithdrawal_logistic");
        try {
            $data = $request->json()->all();
            // $pedido = PedidosShopify::findOrFail($data['id_origen']);
            $orden = OrdenesRetiro::findOrFail($id);
            if ($orden->estado == "APROBADO") {
                $orden->estado = "REALIZADO";
                $orden->comprobante = $data['comprobante'];
                $orden->comentario = $data['comentario'];
                // $orden->fecha_transferencia = $data['fecha_transferencia'];
                $orden->fecha_transferencia = date("d/m/Y H:i:s");
                $orden->updated_at = new DateTime();
                $orden->monto = str_replace(',', '.', $orden->monto);
                $orden->updated_by_id = $data['generated_by'];
                $orden->paid_by = $data['generated_by'];
                $orden->save();


                $marcaT = Carbon::createFromFormat('d/m/Y H:i:s', $orden->fecha)->format('Y-m-d');
                $marcaTtransferencia = Carbon::createFromFormat('d/m/Y H:i:s', $orden->fecha_transferencia)->format('Y-m-d');


                $rolInvoke = $data['rol_id'];

                if ($rolInvoke != 5) {

                    $lastTransaccion = Transaccion::where('id_origen', $id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($lastTransaccion == null) {
                        error_log("Nuevo registro");
                        $trans = $this->DebitLocal($orden->id_vendedor, $orden->monto, $orden->id, "retiro-" . $orden->id, 'retiro', 'debito por retiro ' . $orden->estado, $data['generated_by']);
                        error_log($trans);
                    } else {
                        if ($lastTransaccion->tipo != "debit" && $lastTransaccion->origen != "retiro") {
                            error_log("El ultimo registro con id_origen:$id se encuentra en $lastTransaccion->origen");

                            $this->DebitLocal($orden->id_vendedor, $orden->monto, $orden->id, "retiro-" . $orden->id, 'retiro', 'debito por retiro ' . $orden->estado, $data['generated_by']);
                        } else {
                            error_log("El ultimo registro con id_origen:$id se encuentra en debit just update comment");
                            $lastTransaccion->comentario = 'debito por retiro ' . $orden->estado;
                            $lastTransaccion->save();
                        }
                    }


                    // ! integracion

                    // // Verificar si ya existe una transacción global para este pedido y vendedor
                    $existingTransaction = TransaccionGlobal::where('origin', 'Retiro de Efectivo')
                        ->where('id_seller', $orden->id_vendedor)
                        ->where('code', 'Retiro-' . $orden->id)
                        ->first();

                    // Obtener la transacción global previa
                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $orden->id_vendedor)
                        ->orderBy('id', 'desc')
                        ->first();

                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                    // error_log($existingTransaction);

                    if ($existingTransaction == null) {
                        $newTransactionGlobal = new TransaccionGlobal();

                        $newTransactionGlobal->admission_date = $marcaT;
                        $newTransactionGlobal->delivery_date = $marcaTtransferencia;
                        $newTransactionGlobal->status = "REALIZADO";
                        // $newTransactionGlobal->return_state = $order->estado_devolucion;
                        $newTransactionGlobal->return_state = null;
                        $newTransactionGlobal->id_order = $orden->id;
                        $newTransactionGlobal->code = 'Retiro' . "-" . $orden->id;
                        $newTransactionGlobal->origin = 'Retiro de ' . 'Efectivo';
                        // $orden->monto = str_replace(',', '.', $orden->monto);
                        $newTransactionGlobal->withdrawal_price = -$orden->monto; // Ajusta según necesites
                        $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                        $newTransactionGlobal->return_cost = 0;
                        $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->total_transaction = -$orden->monto;
                        $newTransactionGlobal->previous_value = $previousValue;
                        $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                        $newTransactionGlobal->state = true;
                        $newTransactionGlobal->id_seller = $orden->id_vendedor;
                        $newTransactionGlobal->internal_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_return_cost = 0;
                        $newTransactionGlobal->save();
                    } else {
                        $existingTransaction->status = "REALIZADO";
                        $existingTransaction->delivery_date = now()->format('Y-m-d');
                        $existingTransaction->save();
                    }
                } else {
                    $lastTransaccion = ProviderTransaction::where('origin_id', $id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($lastTransaccion == null) {
                        error_log("Nuevo registro");
                        $this->DebitLocalProvider(
                            $orden->id,
                            "Retiro-" . $orden->id,
                            $orden->id_vendedor,
                            $orden->monto,
                            "Retiro",
                            "retiro proveedor",
                            "APROBADO",
                            "Retiro de billetera APROBADO",
                            $data['generated_by'],
                            ""
                        );
                    } else {
                        // ! falta validar la condicion del if
                        if (
                            // $lastTransaccion->tipo != "debit"
                            // && $lastTransaccion->origen != "retiro"
                            $lastTransaccion->transaction_type != "Retiro"
                        ) {
                            error_log("El ultimo registro con id_origen:$id se encuentra en $lastTransaccion->origen");
                            $this->DebitLocalProvider(
                                $orden->id,
                                "Retiro-" . $orden->id,
                                $orden->id_vendedor,
                                $orden->monto,
                                "Retiro",
                                "retiro proveedor",
                                "APROBADO",
                                "Retiro de billetera APROBADO",
                                $data['generated_by'],
                                ""
                            );
                        } else {
                            error_log("El ultimo registro con id_origen:$id se encuentra en debit just update comment");
                            $lastTransaccion->comment = 'debito por retiro ' . $orden->estado;
                            $lastTransaccion->save();
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            error_log("debitWithdrawal_error: $e ");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function postWhitdrawalProviderAproved(Request $request, $id)
    {

        DB::beginTransaction();
        try {
            error_log("postWhitdrawalProviderAproved");
            //code...

            $data = $request->json()->all();
            $withdrawal = new OrdenesRetiro();
            $withdrawal->monto = $data["monto"];
            // $withdrawal->fecha = new  DateTime();
            $withdrawal->fecha = date("d/m/Y H:i:s");
            $withdrawal->codigo_generado = $data["codigo"];
            $withdrawal->estado = 'APROBADO';
            $withdrawal->id_vendedor = $data["id_vendedor"];
            $withdrawal->account_id = $data["id_account"];
            $withdrawal->rol_id = 5;
            // $withdrawal->account_id = "EEEETEST";
            $withdrawal->created_by_id = $data["generated_by"];

            $withdrawal->save();

            $ordenUser = new OrdenesRetirosUsersPermissionsUserLink();
            $ordenUser->ordenes_retiro_id = $withdrawal->id;
            $ordenUser->user_id = $id;
            $ordenUser->save();

            // $this->DebitLocal($data["id_vendedor"], $data["monto"], $withdrawal->id, "retiro-" . $withdrawal->id, "retiro", "debito por retiro solicitado", $data["id_vendedor"]);

            $this->DebitLocalProvider(
                $withdrawal->id,
                "Retiro-" . $withdrawal->id,
                $data["id_vendedor"],
                $data["monto"],
                "Retiro",
                "retiro proveedor",
                "APROBADO",
                "Retiro de billetera APROBADO",
                $data["id_vendedor"],
                ""
            );

            DB::commit();
            return response()->json(["response" => "solicitud generada exitosamente", "solicitud" => $withdrawal], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("postWhitdrawalProviderAproved_error: $e");
            return response()->json(["response" => "error al cambiar de estado", "error" => $e], Response::HTTP_BAD_REQUEST);
        }
    }

    public function approveWhitdrawal(Request $request, $id)
    {
        //for old version: approve and debit
        error_log("rw-TransportadorasAPIController-approveWhitdrawal");
        DB::beginTransaction();

        try {
            $data = $request->json()->all();
            // $pedido = PedidosShopify::findOrFail($data['id_origen']);
            $withdrawal = OrdenesRetiro::findOrFail($id);
            $withdrawal->estado = "APROBADO";
            $withdrawal->updated_at = new DateTime();
            $withdrawal->codigo = $withdrawal->codigo_generado;
            $withdrawal->updated_by_id = $data["generated_by"];
            $withdrawal->save();
            $monto = str_replace(',', '.', $withdrawal->monto);
            // $this->DebitLocal($data["id_vendedor"], $monto, $withdrawal->id, "retiro-" . $withdrawal->id, "retiro", "debito por retiro solicitado", $data["id_vendedor"]);
            $this->DebitLocal($data["id_vendedor"], $monto, $withdrawal->id, "retiro-" . $withdrawal->id, "retiro", "debito por retiro solicitado", $data["generated_by"]);

            $marcaT = Carbon::createFromFormat('d/m/Y H:i:s', $withdrawal->fecha)->format('Y-m-d H:i:s');

            // ! integracion

            // // Verificar si ya existe una transacción global para este pedido y vendedor
            $existingTransaction = TransaccionGlobal::where('origin', 'Retiro de Efectivo')
                ->where('id_seller', $withdrawal->id_vendedor)
                ->where('code', 'Retiro-' . $withdrawal->id)
                ->first();

            // Obtener la transacción global previa
            $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $withdrawal->id_vendedor)
                ->orderBy('id', 'desc')
                ->first();

            $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

            if ($existingTransaction === null) {
                $newTransactionGlobal = new TransaccionGlobal();

                $newTransactionGlobal->admission_date = $marcaT;
                // $newTransactionGlobal->delivery_date = now()->format('Y-m-d');
                $newTransactionGlobal->status = "APROBADO";
                // $newTransactionGlobal->return_state = $order->estado_devolucion;
                $newTransactionGlobal->return_state = null;
                $newTransactionGlobal->id_order = $withdrawal->id;
                $newTransactionGlobal->code = 'Retiro' . "-" . $withdrawal->id;
                $newTransactionGlobal->origin = 'Retiro de ' . 'Efectivo';
                // $withdrawal->monto = str_replace(',', '.', $withdrawal->monto);
                $newTransactionGlobal->withdrawal_price = -$withdrawal->monto; // Ajusta según necesites
                $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                $newTransactionGlobal->return_cost = 0;
                $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->total_transaction = -$withdrawal->monto;
                $newTransactionGlobal->previous_value = $previousValue;
                $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                $newTransactionGlobal->state = true;
                $newTransactionGlobal->id_seller = $withdrawal->id_vendedor;
                $newTransactionGlobal->internal_transportation_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                $newTransactionGlobal->external_return_cost = 0;
                $newTransactionGlobal->save();
            }
            // else {
            //     $existingTransaction->status = "REALIZADO";
            //     $existingTransaction->delivery_date = now()->format('Y-m-d');
            //     $existingTransaction->save();
            // }



            DB::commit();
            return response()->json(["response" => "cambio de estado y debit exitoso", "solicitud" => $withdrawal], 200);
        } catch (\Exception $e) {
            DB::rollback(); // En caso de error, revierte todos los cambios realizados en la transacción
            error_log("$e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
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
}
