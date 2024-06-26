<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PedidosShopify;
use App\Models\TransaccionPedidoTransportadora;
use App\Models\Transportadora;
use App\Models\TransportadorasShippingCost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TransaccionPedidoTransportadoraAPIController extends Controller
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
        //
        $status = $request->input('status');
        $fecha_entrega = $request->input('fecha_entrega');
        $precio_total = $request->input('precio_total');
        $costo_transportadora = $request->input('costo_transportadora');

        $id_pedido = $request->input('id_pedido');
        $id_transportadora = $request->input('id_transportadora');
        $id_operador = $request->input('id_operador');

        $transaccion = new TransaccionPedidoTransportadora();
        $transaccion->status = $status;
        $transaccion->fecha_entrega = $fecha_entrega;
        $transaccion->precio_total = $precio_total;
        $transaccion->costo_transportadora = $costo_transportadora;
        $transaccion->id_pedido = $id_pedido;
        $transaccion->id_transportadora = $id_transportadora;
        $transaccion->id_operador = $id_operador;

        $transaccion->save();

        return response()->json(['data' => $transaccion], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
        $transaccion = TransaccionPedidoTransportadora::findOrFail($id);
        $transaccion->update($request->all());
        return response()->json($transaccion, Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $transaccion = TransaccionPedidoTransportadora::find($id);

        if ($transaccion) {
            $transaccion->delete();
            return response()->json($transaccion, Response::HTTP_OK);
        } else {
            return response()->json(["message" => "La transaccion no fue encontrada"], 200);
        }
    }

    public function getByTransportadoraDates(Request $request)
    {
        //for excel
        $data = $request->json()->all();
        $idTransportadora = $data['id_transportadora'];
        $fechasEntrega = $data['fechas_entrega'];
        $datesFormatted = [];

        foreach ($fechasEntrega as $fecha) {
            $dateFormatted = Carbon::createFromFormat('Y-m-d', $fecha)->format('j/n/Y');
            $datesFormatted[] = $dateFormatted;
        }

        $transacciones = TransaccionPedidoTransportadora::with('pedidos_shopify', 'transportadora', 'operadore.up_users')
            ->where('id_transportadora', $idTransportadora)
            ->whereIn('fecha_entrega', $datesFormatted)
            ->orderByRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y')")
            ->get();

        if ($transacciones->isEmpty()) {
            // return response()->json(["message" => "No existen datos en este mes-año"], 200);
            return response()->json([], 204);
        }

        $costo_transportadora = $transacciones->first()->costo_transportadora;
        $count_orders =  $transacciones->flatten()->count();
        $totalShippingCost = $costo_transportadora * $count_orders;

        // $totalCost = TransportadorasShippingCost::where('id_transportadora', $idTransportadora)
        //     // ->selectRaw('daily_total')
        //     ->selectRaw('daily_shipping_cost')
        //     ->whereIn(DB::raw('DATE(time_stamp)'), $fechasEntrega)
        //     ->get();
        // $totalShippingCost = $totalCost->sum('daily_shipping_cost');

        return response()->json(['data' => $transacciones, "total" => $totalShippingCost], 200);
    }
    // ! nueva funcion para descargar reporte
    public function getByTransportadoraDatesExternal(Request $request)
    {
        //for excel
        $data = $request->json()->all();
        $idTransportadora = $data['id_transportadora'];
        $fechasEntrega = $data['fechas_entrega'];
        $datesFormatted = [];

        foreach ($fechasEntrega as $fecha) {
            $dateFormatted = Carbon::createFromFormat('Y-m-d', $fecha)->format('j/n/Y');
            $datesFormatted[] = $dateFormatted;
        }

        // $query = PedidosShopify::query()
        // ->with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta', 'product.warehouse.provider', 'carrierExternal','pedidoCarrier'])
        // // ->where('carrier_external_id', $idUser)  // ! <---- se pretende usar el id de la transportadora externa que seleccione
        // // ->where('carrier_external_id',1)
        // ->whereHas('pedidoCarrier', function ($query) use ($idUser) {
        //     $query->where('carrier_id', $idUser);
        // })
        // ->whereRaw("STR_TO_DATE(" . $selectedFilter . ", '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])

        $transacciones = PedidosShopify::with(['transportadora', 'operadore.up_users','pedidoCarrier','users.vendedores'])
            ->whereHas('pedidoCarrier', function ($query) use ($idTransportadora) {
                $query->where('carrier_id', $idTransportadora);
            })
            ->whereIn('fecha_entrega', $datesFormatted)
            ->where('status', "ENTREGADO")
            ->where('estado_interno', "CONFIRMADO")
            ->where('estado_logistico', "ENVIADO")
            ->orderByRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y')")
            ->get();

        if ($transacciones->isEmpty()) {
            // return response()->json(["message" => "No existen datos en este mes-año"], 200);
            return response()->json([], 204);
        }

        $costo_transportadora = $transacciones->first()->costo_transportadora;
        $count_orders =  $transacciones->flatten()->count();
        $totalShippingCost = $costo_transportadora * $count_orders;

        // $totalCost = TransportadorasShippingCost::where('id_transportadora', $idTransportadora)
        //     // ->selectRaw('daily_total')
        //     ->selectRaw('daily_shipping_cost')
        //     ->whereIn(DB::raw('DATE(time_stamp)'), $fechasEntrega)
        //     ->get();
        // $totalShippingCost = $totalCost->sum('daily_shipping_cost');

        return response()->json(['data' => $transacciones, "total" => $totalShippingCost], 200);
    }

    public function getByDate(Request $request)
    {
        $data = $request->json()->all();
        $idPedido = $data['id_pedido'];
        $idTransportadora = $data['id_transportadora'];
        $fechaEntrega = $data['fecha_entrega'];
        $dateFormatted = Carbon::createFromFormat('Y-m-d', $fechaEntrega)->format('j/n/Y');

        $transaccion = TransaccionPedidoTransportadora::where('id_pedido', $idPedido)
            ->where('id_transportadora', $idTransportadora)
            ->where('fecha_entrega', $dateFormatted)
            ->get();

        if ($transaccion->isEmpty()) {
            return response()->json(["message" => "No existe un registro anterior"], 200);
        }

        return response()->json(['message' => "Ya existe un registro", "transaccion" => $transaccion], 200);
    }
    
    public function getOrdersPerDayExternal(Request $request)
    {
        //for show orders per day
        $data = $request->json()->all();
        // $startDate = $data['start'];
        // $endDate = $data['end'];
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        // $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $idTransportadora = $data['id_transportadora'];
        $deliveredDate = $data['fecha_entrega'];
        $populate = $data['populate'];
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $sort = $data['sort'];
        $searchTerm = $data['search'];

        // if ($searchTerm != "") {
        //     $filteFields = $data['or'];
        // } else {
        //     $filteFields = [];
        // }

        // ! *************
        // $orConditions = $data['or_multiple'];
        $andCondition = $data['and'];
        // $not = $data['not'];
        // ! *************

        $dateFormatted = Carbon::createFromFormat('Y-m-d', $deliveredDate)->format('j/n/Y');
        // $orders = TransaccionPedidoTransportadora::with('pedidos_shopify', 'transportadora', 'operadore.up_users')
        $orders = TransaccionPedidoTransportadora::with($populate)
            ->where('id_carrierexternal', $idTransportadora)
            ->where('fecha_entrega', $dateFormatted)
            ->where((function ($pedidos) use ($andCondition) {
                foreach ($andCondition as $condition) {
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
        // ->get();


        // ! Sort
        $orderByText = null;
        $orderByDate = null;
        $sortParts = explode(':', $sort);

        $pt1 = $sortParts[0];

        $type = (stripos($pt1, 'fecha') !== false || stripos($pt1, 'marca') !== false) ? 'date' : 'text';

        $dataSort = [
            [
                'field' => $sortParts[0],
                'type' => $type,
                'direction' => $sortParts[1],
            ],
        ];

        foreach ($dataSort as $value) {
            $field = $value['field'];
            $direction = $value['direction'];
            $type = $value['type'];

            if ($type === "text") {
                $orderByText = [$field => $direction];
            } else {
                $orderByDate = [$field => $direction];
            }
        }

        if ($orderByText !== null) {
            $orders->orderBy(key($orderByText), reset($orderByText));
        } else {
            $orders->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! ******************
        $orders = $orders->paginate($pageSize, ['*'], 'page', $pageNumber);

        if ($orders->isEmpty()) {
            // return response()->json(["message" => "No existen datos en este mes-año"], 200);
            return response()->json([], 204);
        }

        $total_proceeds = 0;
        $count_orders = 0;
        foreach ($orders as $pedido) {
            $count_orders++;
            if ($pedido["status"] == "ENTREGADO") {
                $precioTotal = floatval($pedido["precio_total"]);
                $total_proceeds += $precioTotal;
            }
        }

        $transportadora = Transportadora::find($idTransportadora);
        $costo_transportadora = $transportadora->costo_transportadora;

        $total_proceeds = round($total_proceeds, 2);
        error_log("total_proceeds $total_proceeds");
        $shipping_total = $costo_transportadora * $count_orders;
        error_log("shipping_total $shipping_total");
        $total_day = $total_proceeds - $shipping_total;
        error_log("total_day $total_day");

        // return response()->json($orders);
        // return response()->json($orders, 200);
        return response()->json([
            'data' => $orders,
            'total_proceeds' => $total_proceeds,
            'shipping_total' => $shipping_total,
            'total_day' => $total_day

        ]);
    }


    public function getOrdersPerDay(Request $request)
    {
        //for show orders per day
        $data = $request->json()->all();
        // $startDate = $data['start'];
        // $endDate = $data['end'];
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        // $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $idTransportadora = $data['id_transportadora'];
        $deliveredDate = $data['fecha_entrega'];
        $populate = $data['populate'];
        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];
        $sort = $data['sort'];
        $searchTerm = $data['search'];

        // if ($searchTerm != "") {
        //     $filteFields = $data['or'];
        // } else {
        //     $filteFields = [];
        // }

        // ! *************
        // $orConditions = $data['or_multiple'];
        $andCondition = $data['and'];
        // $not = $data['not'];
        // ! *************

        $dateFormatted = Carbon::createFromFormat('Y-m-d', $deliveredDate)->format('j/n/Y');
        // $orders = TransaccionPedidoTransportadora::with('pedidos_shopify', 'transportadora', 'operadore.up_users')
        $orders = TransaccionPedidoTransportadora::with($populate)
            ->where('id_transportadora', $idTransportadora)
            ->where('fecha_entrega', $dateFormatted)
            ->where((function ($pedidos) use ($andCondition) {
                foreach ($andCondition as $condition) {
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
        // ->get();


        // ! Sort
        $orderByText = null;
        $orderByDate = null;
        $sortParts = explode(':', $sort);

        $pt1 = $sortParts[0];

        $type = (stripos($pt1, 'fecha') !== false || stripos($pt1, 'marca') !== false) ? 'date' : 'text';

        $dataSort = [
            [
                'field' => $sortParts[0],
                'type' => $type,
                'direction' => $sortParts[1],
            ],
        ];

        foreach ($dataSort as $value) {
            $field = $value['field'];
            $direction = $value['direction'];
            $type = $value['type'];

            if ($type === "text") {
                $orderByText = [$field => $direction];
            } else {
                $orderByDate = [$field => $direction];
            }
        }

        if ($orderByText !== null) {
            $orders->orderBy(key($orderByText), reset($orderByText));
        } else {
            $orders->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
        }
        // ! ******************
        $orders = $orders->paginate($pageSize, ['*'], 'page', $pageNumber);

        if ($orders->isEmpty()) {
            // return response()->json(["message" => "No existen datos en este mes-año"], 200);
            return response()->json([], 204);
        }

        $total_proceeds = 0;
        $count_orders = 0;
        foreach ($orders as $pedido) {
            $count_orders++;
            if ($pedido["status"] == "ENTREGADO") {
                $precioTotal = floatval($pedido["precio_total"]);
                $total_proceeds += $precioTotal;
            }
        }

        $transportadora = Transportadora::find($idTransportadora);
        $costo_transportadora = $transportadora->costo_transportadora;

        $total_proceeds = round($total_proceeds, 2);
        error_log("total_proceeds $total_proceeds");
        $shipping_total = $costo_transportadora * $count_orders;
        error_log("shipping_total $shipping_total");
        $total_day = $total_proceeds - $shipping_total;
        error_log("total_day $total_day");

        // return response()->json($orders);
        // return response()->json($orders, 200);
        return response()->json([
            'data' => $orders,
            'total_proceeds' => $total_proceeds,
            'shipping_total' => $shipping_total,
            'total_day' => $total_day

        ]);
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
}
