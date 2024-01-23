<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TransaccionPedidoTransportadora;
use App\Models\Transportadora;
use App\Models\TransportadorasShippingCost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;


class TransportadorasShippingCostAPIController extends Controller
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
        /// *userPerformTask
        $data = $request->json()->all();
        $idTransportadora = $data['id_transportadora'];
        // $currentDate = $data['fecha_entrega'];//deberia ser del diario el date actual pero ...
        $shipping_total = $data['shipping_total'];
        $total_proceeds = $data['total_proceeds'];
        $total_day = $data['total_day'];
        $proof_payment = $data['proof_payment'];
        // $paymentForDay = $data['fecha_pago'];

        // $currentDate = now()->format('j/n/Y');

        $currentDateTime = date('Y-m-d H:i:s');

        $newTransportadoraShippingCost = new TransportadorasShippingCost();
        $newTransportadoraShippingCost->status = 'PAGADO';
        $newTransportadoraShippingCost->time_stamp = $currentDateTime;
        $newTransportadoraShippingCost->daily_proceeds = $total_proceeds;
        $newTransportadoraShippingCost->daily_shipping_cost = $shipping_total;
        $newTransportadoraShippingCost->daily_total = $total_day;
        $newTransportadoraShippingCost->id_transportadora = $idTransportadora;
        $newTransportadoraShippingCost->url_proof_payment = $proof_payment;
        $newTransportadoraShippingCost->save();
        // return response()->json(["message" => "este registro NO existe", "fecha" => $dateFormatted, "id_transportadora" => $newTransportadoraShippingCost], 200);

        return response()->json($newTransportadoraShippingCost, 200);
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
        $transportadoraShipping = TransportadorasShippingCost::findOrFail($id);
        $transportadoraShipping->update($request->all());
        return response()->json($transportadoraShipping, Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getShippingCostPerDay()
    {

        $transportadoras = Transportadora::all();
        // $transportadoraId = 19;
        // $transportadora = Transportadora::find($transportadoraId);

        $shipping_total = 0;
        $count_orders = 0;
        $total_proceeds = 0;
        $total_day = 0;

        $currentDate = now()->format('j/n/Y');
        // $currentDate = '12/10/2023';
        // $currentDate =  Carbon::createFromFormat('j/n/Y', $currentDate)->format('j/n/Y');

        $currentDateTime = date('Y-m-d H:i:s');
        // $desiredTime = '01:13:13';
        // $currentDateTime = Carbon::createFromFormat('d/n/Y H:i:s', $currentDate . ' ' . $desiredTime)->format('Y-m-d H:i:s');

        foreach ($transportadoras as $transportadora) {

            $transportadoraId = $transportadora->id;
            $costo_transportadora = $transportadora->costo_transportadora;

            $dateFormatted = Carbon::createFromFormat('j/n/Y', $currentDate)->format('Y-m-d');
            $transportadoraShippingCost = TransportadorasShippingCost::where('id_transportadora', $transportadoraId)
                ->whereDate('time_stamp', $dateFormatted)
                ->get();

            if ($transportadoraShippingCost->count() === 0) {

                $pedidos = TransaccionPedidoTransportadora::where('id_transportadora', $transportadoraId)
                    ->where('fecha_entrega', $currentDate)
                    ->get();

                $total_proceeds = 0;
                foreach ($pedidos as $pedido) {
                    if ($pedido["status"] == "ENTREGADO") {
                        $precioTotal = floatval($pedido["precio_total"]);
                        $total_proceeds += $precioTotal;
                    }
                }

                $total_proceeds = round($total_proceeds, 2);
                $count_orders =  $pedidos->flatten()->count();
                $shipping_total = $costo_transportadora * $count_orders;
                $total_day = $total_proceeds - $shipping_total;

                $newTransportadoraShippingCost = new TransportadorasShippingCost();
                $newTransportadoraShippingCost->status = 'PENDIENTE';
                $newTransportadoraShippingCost->time_stamp = $currentDateTime;
                $newTransportadoraShippingCost->daily_proceeds = $total_proceeds;
                $newTransportadoraShippingCost->daily_shipping_cost = $shipping_total;
                $newTransportadoraShippingCost->daily_total = $total_day;
                $newTransportadoraShippingCost->id_transportadora = $transportadoraId;
                $newTransportadoraShippingCost->save();
                // return response()->json(["message" => "este registro NO existe", "fecha" => $dateFormatted, "id_transportadora" => $newTransportadoraShippingCost], 200);
            } else {
                //update
                $firstResult = $transportadoraShippingCost->first();
                $idTSC = $firstResult->id;
                $transportadoraShipping = TransportadorasShippingCost::findOrFail($idTSC);
                $pedidos = TransaccionPedidoTransportadora::where('id_transportadora', $transportadoraId)
                    ->where('fecha_entrega', $currentDate)
                    ->get();

                $total_proceeds = 0;
                foreach ($pedidos as $pedido) {
                    if ($pedido["status"] == "ENTREGADO") {
                        $precioTotal = floatval($pedido["precio_total"]);
                        $total_proceeds += $precioTotal;
                    }
                }

                $total_proceeds = round($total_proceeds, 2);
                $count_orders =  $pedidos->flatten()->count();
                $shipping_total = $costo_transportadora * $count_orders;
                $total_day = $total_proceeds - $shipping_total;

                $transportadoraShipping->update([
                    'status' => 'PENDIENTE',
                    'daily_proceeds' => $total_proceeds,
                    'daily_shipping_cost' => $shipping_total,
                    'daily_total' => $total_day
                ]);
                // return response()->json(["message" => "Updated este registro ya existe", "id" => $idTSC, "data" => $transportadoraShippingCost], 200);
            }
        }

        return response()->json([], 200);
    }

    public function getByTransportadora(Request $request, $idTransportadora)
    {
        $data = $request->json()->all();
        $monthToFind = $data['month'];
        $yearToFind = $data['year'];

        $shippingCostsMonthly = TransportadorasShippingCost::where('id_transportadora', $idTransportadora)
            ->selectRaw('id, status, DATE(time_stamp) as fecha, daily_proceeds, daily_shipping_cost, daily_total, rejected_reason, url_proof_payment')
            ->whereYear('time_stamp', $yearToFind)
            ->whereMonth('time_stamp', $monthToFind)
            ->get();

        if ($shippingCostsMonthly->isEmpty()) {
            // return response()->json(["message" => "No existen datos en este mes-año"], 200);
            return response()->json([], 204);

        }

        return response()->json(['data' => $shippingCostsMonthly], 200);
    }

    public function byDate(Request $request)
    {
        $data = $request->json()->all();
        $idTransportadora = $data['id_transportadora'];
        $fecha = $data['fecha'];
        $dateFormatted = Carbon::createFromFormat('j/n/Y', $fecha)->format('Y-m-d');

        $dailyCosts = TransportadorasShippingCost::where('id_transportadora', $idTransportadora)
            ->whereDate('time_stamp', $dateFormatted)
            ->get();

        if ($dailyCosts->isEmpty()) {
            return response()->json(["message" => "No existe un registro anterior"], 200);
        }

        return response()->json(['message' => "Ya existe un registro", "dailyCosts" => $dailyCosts], 200);
    }

    public function uploadFile0(Request $request) {
        if ($request->hasFile('files')) {
            $file = $request->file('files');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('uploads', $fileName, 'public'); // Almacenar en la carpeta 'public/uploads'
            // Puedes guardar la ubicación del archivo en una base de datos o retornarla como respuesta
            $fileUrl = asset('storage/uploads/' . $fileName);
            // return response()->json(['message' => 'Archivo subido correctamente', 'url' => $fileUrl]);
            return response()->json([$fileUrl], 200);

        } else {
            return response()->json(['error' => 'No se proporcionó un archivo válido.'], 400);
        }
    }

    public function uploadFile(Request $request) {
        if ($request->hasFile('files')) {
            $file = $request->file('files');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('uploads', $fileName, 'public'); // Almacenar en la carpeta 'public/uploads'
            
            // Construye la URL que comienza desde "/uploads"
            $fileUrl = asset('storage/uploads/' . $fileName);
    
            // Devuelve solo la parte de la URL después de "/uploads"
            $relativePath = str_replace(asset('storage/'), '', $fileUrl);
    
            return response()->json($relativePath, 200);
        } else {
            return response()->json(['error' => 'No se proporcionó un archivo válido.'], 400);
        }
    }
    
}
