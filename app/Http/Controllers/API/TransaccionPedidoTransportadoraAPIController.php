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
}
