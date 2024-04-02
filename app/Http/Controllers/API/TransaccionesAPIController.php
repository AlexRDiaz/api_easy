<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PedidosShopify;
use App\Models\Transaccion;
use App\Models\UpUser;
use App\Models\Vendedore;
use App\Models\Product;
use App\Models\ProviderTransaction;
use App\Models\StockHistory;
use App\Models\Provider;

use App\Http\Controllers\API\ProductAPIController;
use App\Models\OrdenesRetiro;
use App\Models\OrdenesRetirosUsersPermissionsUserLink;
use App\Models\TransaccionPedidoTransportadora;
use App\Models\TransportadorasShippingCost;
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
            if ($order->status == "NOVEDAD") {


                if ($order->costo_devolucion == null) { // Verifica si está vacío convirtiendo a un array
                    $order->costo_devolucion = $order->users[0]->vendedores[0]->costo_devolucion;
                    $this->DebitLocal($order->users[0]->vendedores[0]->id_master, $order->users[0]->vendedores[0]->costo_devolucion, $order->id, $order->users[0]->vendedores[0]->nombre_comercial . "-" . $order->numero_orden, "devolucion", "Costo de devolución desde operador por pedido en " . $order->status . " y " . $order->estado_devolucion, $data['generated_by']);



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


    public function paymentOrderDelivered(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->json()->all();

            $startDateFormatted = new DateTime();

            // $pedido = PedidosShopify::findOrFail($data['id_origen']);
            $pedido = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades', 'operadore', 'transactionTransportadora',])->findOrFail($data['id_origen']);

            // if ($pedido->costo_envio == null) {
            // error_log("Transaccion nueva");

            $transaccionSellerPrevious = Transaccion::
                // where('tipo', 'credit')
                where('id_origen', $data['id_origen'])
                ->get();

            if ($transaccionSellerPrevious->isNotEmpty()) {
                // Obtener el último registro de transacción
                $lastTransaction = $transaccionSellerPrevious->last();
                error_log($lastTransaction);
                if ($lastTransaction->origen !== 'reembolso'  && $lastTransaction->origen !== 'envio') {
                    return response()->json(["res" => "Transacciones ya Registradas"]);
                }
            }

            error_log("Transaccion nueva");

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

            $pedido->save();


            $request->merge(['comentario' => 'Recaudo  de valor por pedido ' . $pedido->status]);
            $request->merge(['origen' => 'recaudo']);

            if ($SellerCreditFinalValue['total'] != null) {
                $request->merge(['monto' => $SellerCreditFinalValue['total']]);
            }

            $this->Credit($request);

            $productValues = [];
            // !*********
            if (!empty($SellerCreditFinalValue['valor_producto'])) {
                $productValues = $SellerCreditFinalValue['valor_producto'];
                // No necesitas codificar a JSON si ya es un array
                // $decodevalues = json_encode($productValues);
                error_log(print_r($productValues, true));
                foreach ($productValues as $valueProduct) {
                    // Asumiendo que $valueProduct es un número, podrías necesitar validar o formatearlo según sea necesario
                    $request->merge(['comentario' => 'Costo de valor de Producto en Bodega ' . $pedido->status]);
                    $request->merge(['origen' => 'valor producto bodega']);
                    $request->merge(['monto' => $valueProduct]);

                    $this->Debit($request);
                }
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
            }

            // error_log("add en tpt");

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
                // error_log("new tpt");
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
                // error_log("upt tpt");
                TransaccionPedidoTransportadora::where('id', $pedido['transactionTransportadora']['id'])->update([
                    'status' => 'ENTREGADO',
                    'costo_transportadora' => $costoTransportadora,
                ]);
            }


            DB::commit(); // Confirma la transacción si todas las operaciones tienen éxito
            return response()->json([
                "res" => "transaccion exitosa"
            ]);
            // }else{
            //     error_log("Este pedido ya tiene marcado el costo_envio");

            //     return response()->json([
            //         'error' => 'Ocurrió un error al procesar la solicitud'
            //     ], 500); 
            // }

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
                    $newTrans->comentario = "Costo de devolucion por pedido en NOVEDAD y" . $order->estado_devolucion;

                    $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                    $newTrans->state = 1;
                    $newTrans->generated_by = $data['generated_by'];

                    $this->transaccionesRepository->create($newTrans);
                    $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);
                }
                $message = "transacción con debito por devolucion";
            } else {
                $message = "transacción sin debito por devolucion";
            }

            $order->status = "NOVEDAD";
            $order->status_last_modified_at = date('Y-m-d H:i:s');
            $order->status_last_modified_by = $data['generated_by'];
            $order->comentario = $data['comentario'];
            if ($order->novedades == []) {
                $order->fecha_entrega = now()->format('j/n/Y');
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
            $order->estado_devolucion = "ENTREGADO EN OFICINA";
            $order->do = "ENTREGADO EN OFICINA";
            $order->marca_t_d = date("d/m/Y H:i");
            $order->received_by = $data['generated_by'];
            if ($order->status == "NOVEDAD") {


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
            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
            $order->estado_devolucion = "EN BODEGA";
            $order->dl = "EN BODEGA";
            $order->marca_t_d_l = date("d/m/Y H:i");
            $order->received_by = $data['generated_by'];


            // ! suma stock  cuando pedido ya se encuentra "EN BODEGA" JP
            $productController = new ProductAPIController();

            // $searchResult = $productController->updateProductVariantStockInternal(
            //     $order->cantidad_total,
            //     $order->sku,
            //     1,
            //     $order->id_comercial,
            // );

            $searchResult = $productController->updateProductVariantStockInternal(
                $order->variant_details,
                1,
                $order->id_comercial,
            );

            // !

            if ($order->status == "NOVEDAD") {


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
                    $newTrans->comentario = "Costo de devolución desde logística por pedido en " . $order->status . " y " . $order->estado_devolucion;
                    $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                    $newTrans->state = 1;
                    $newTrans->generated_by = $data['generated_by'];
                    $this->transaccionesRepository->create($newTrans);
                    $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);

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



    public function paymentTransportByReturnStatus(Request $request, $id)
    {
        DB::beginTransaction();
        $message = "";
        $repetida = null;

        try {
            $data = $request->json()->all();
            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
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


            if ($order->status == "NOVEDAD") {
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
            $order = PedidosShopify::with(['users.vendedores', 'transportadora', 'novedades'])->find($id);
            if ($data["return_status"] == "ENTREGADO EN OFICINA") {
                $order->estado_devolucion = $data["return_status"];
                $order->do = $data["return_status"];
                $order->marca_t_d = date("d/m/Y H:i");
                $order->received_by = $data['generated_by'];
            }
            if ($data["return_status"] == "EN BODEGA") {
                $order->estado_devolucion = $data["return_status"];
                $order->dl = $data["return_status"];
                $order->marca_t_d_l = date("d/m/Y H:i");
                $order->received_by = $data['generated_by'];
            }


            if ($order->status == "NOVEDAD") {


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
                    $newTrans->comentario = "Costo de devolución desde logistica por pedido en " . $order->status . " y " . $order->estado_devolucion;
                    $newTrans->id_vendedor = $order->users[0]->vendedores[0]->id_master;
                    $newTrans->state = 1;
                    $newTrans->generated_by = $data['generated_by'];

                    $this->transaccionesRepository->create($newTrans);
                    $this->vendedorRepository->update($newSaldo, $order->users[0]->vendedores[0]->id);

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

        $firstIdTransaction = $ids[0];
        $transactionFounded = Transaccion::where("id", $firstIdTransaction)->first();
        $idTransFounded = $transactionFounded->id_origen;

        // $providerTransaction = ProviderTransaction::where("origin_id", $idTransFounded)->first();
        $providerTransactions = ProviderTransaction::where("origin_id", $idTransFounded)->get();
        // $totalIds = count($ids);
        error_log("-> pt -> $providerTransactions");
        // ! ↓ esto se usa
        // $shouldProcessProviderTransaction = $providerTransaction != null && $providerTransaction->state == 1;


        try {
            //code...
            $transaction = null;

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
                        $order->costo_devolucion = null;
                        $order->costo_envio = null;
                        $order->costo_transportadora = null;
                        $order->value_product_warehouse = null;
                        // $order->estado_interno = "PENDIENTE";
                        // $order->estado_logistico = "PENDIENTE";
                        // $order->estado_pagado = "PENDIENTE";
                        $order->save();
                    }



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


    public function debitWithdrawal(Request $request, $id)
    {
        DB::beginTransaction();

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
                $orden->save();

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
                            $data['generated_by']
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
                                $data['generated_by']
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
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function postWhitdrawalProviderAproved(Request $request, $id)
    {

        DB::beginTransaction();
        try {
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
            // $withdrawal->account_id = "EEEETEST";

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
                $data["id_vendedor"]
            );

            DB::commit();
            return response()->json(["response" => "solicitud generada exitosamente", "solicitud" => $withdrawal], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollback();

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
            $withdrawal->save();
            $monto = str_replace(',', '.', $withdrawal->monto);
            $this->DebitLocal($data["id_vendedor"], $monto, $withdrawal->id, "retiro-" . $withdrawal->id, "retiro", "debito por retiro solicitado", $data["id_vendedor"]);

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
}
