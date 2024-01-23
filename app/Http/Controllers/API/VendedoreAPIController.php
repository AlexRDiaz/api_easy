<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\UserValidation;
use App\Models\OrdenesRetiro;
use App\Models\PedidosShopify;
use App\Models\UpUser;
use App\Models\UpUsersRoleLink;
use App\Models\UpUsersRolesFrontLink;
use App\Models\Vendedore;

use App\Repositories\vendedorRepository;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class VendedoreAPIController extends Controller
{

    public function index()
    {
        $pedidos = Vendedore::all();
        return response()->json($pedidos);
    }

    public function show($id)
    {
        $pedido = Vendedore::findOrFail($id);

        return response()->json($pedido);
    }

    public function getSaldo($id)
    {
        $saldo = Vendedore::whereHas('up_users', function ($query) use ($id) {
            $query->where('up_users.id', $id);
        })->first();
        if (!$saldo) {
            return response()->json(['message' => 'UpUser not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['saldo' => $saldo['saldo']], Response::HTTP_OK);
    }

    public function getVendedores()
    {
        $vendedores = Vendedore::whereNotNull('id_master')
            ->where('id_master', '<>', '') // Agrega esta condición para excluir registros con id_master en blanco
            ->select(DB::raw('CONCAT(nombre_comercial, "-", id_master) as id_nombre'))
            ->distinct()
            ->get()
            ->pluck('id_nombre');

        return response()->json(['vendedores' => $vendedores]);
    }

    public function getSaldoPorId(Request $request)
{
    $id_master = $request->input('id_master');
    $vendedor = Vendedore::where('id_master', $id_master)->first();

    if (!$vendedor) {
        return response()->json(['message' => 'Vendedor con id_master no encontrado'], 404);
    }

    $saldo = $vendedor->saldo;

    return response()->json(['saldo' => $saldo]);
}

    public function getRefereds($id)
    {
        // Valida los datos de entrada (puedes agregar reglas de validación aquí)
        $referedSellers = Vendedore::where('referer', $id)->with('up_users')->get();

        return response()->json($referedSellers, 200);

    }






    public function update(Request $request, $id)
    {
        $seller = Vendedore::find($id);

        if (!$seller) {
            return response()->json(['error' => 'Vendedor no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $seller->fill($request->all());
        $seller->save();

        return response()->json(['message' => 'Usuario actualizado con éxito', 'Vendedor' => $seller], Response::HTTP_OK);
    }

    public function mybalanceVF()
    {
        $values = [];

        // Obtén todos los vendedores
        $searchGeneralSellers = Vendedore::take(10)->get();

        foreach ($searchGeneralSellers as $seller) {
            $sumaEntregados = PedidosShopify::where('id_comercial', $seller->id)
                ->where('status', 'ENTREGADO')
                ->sum('precio_total');

            $sumaCostoInicial = floatval($seller->costo_envio);

            $sumaCosto = DB::table('pedidos_shopifies')
                ->where('id_comercial', $seller->id)
                ->whereIn('status', ['ENTREGADO', 'NO ENTREGADO'])
                ->count() * $sumaCostoInicial;

            $sumaDevolucionInicial = floatval($seller->costo_devolucion);

            $pedidos = DB::table('pedidos_shopifies')
                ->where('id_comercial', $seller->id)
                ->where(function ($query) {
                    $query->where('estado_devolucion', 'ENTREGADO EN OFICINA')
                        ->orWhere('estado_devolucion', 'DEVOLUCION EN RUTA')
                        ->orWhere('estado_devolucion', 'EN BODEGA')
                        ->orWhere('estado_devolucion', 'EN BODEGA PROVEEDOR');
                })
                ->where('status', 'NOVEDAD')->get();

            $sumaDevolucion = $pedidos->count() * $sumaDevolucionInicial;




            $sumaRetiros = OrdenesRetiro::with('users_permissions_user')
                ->whereHas('users_permissions_user', function ($query) use ($seller) {
                    $query->where('user_id', $seller->id);
                })
                ->where('estado', 'REALIZADO')
                ->sum('monto');
            $values[] = [
                'seller' => $seller,
                'sumaEntregados' => $sumaEntregados,
                'sumaCosto' => $sumaCosto,
                'sumaDevolucion' => $sumaDevolucion,

                //  "tedt"=> $searchWithDrawal

                'sumaRetiros' => $sumaRetiros,
            ];
        }

        return [
            'code' => 200,
            'value' => $values
        ];
    }




    // public function credit(Request $req){
    //     $vendedorId=$req['vendedor_id'];
    //     $tipo=$req['tipo']; //credito por defecto
    //     $monto=$req['monto'];
    //     $idOrigen=$req['id_origen'];//id de pedido o retiro
    //     $origen=$req['origen'];//tipo de pedido o retiro

    //     $vendedor = Vendedore::findOrFail($vendedorId);
    //     $vendedor->saldo+$monto;

    //     $trans=new Transaction();
    //     $trans->tipo=$tipo;
    //     $trans->monto=$monto;
    //     $trans->$saldo;
    //     $trans->new Date();
    //     $trans->idOrigen;
    //     $trans->origen;
    //     $trans->vendedorId;

    //     $insetedData =$this->TransactionRepository->create($trans);
    //     $updatedData= $this->VendedorRepository->update($vendedor, $id);

    // }

}