<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrdenesRetiro;
use App\Models\PedidosShopify;
use App\Models\Vendedore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class MiSaldoAPIController extends Controller
{
    //
    public function getSaldo($id)
    {
        error_log("MiSaldo_getSaldo");

        $sumaEntregados = 0.0;
        $sumaCostoInicial = 0.0;
        $sumaCosto = 0.0;

        $sumaDevolucionInicial = 0.0;
        $sumaDevolucion = 0.0;
        $sumaRetiros = 0.0;

        $upuser = $id;

        // $searchGeneralProduct = PedidosShopify::with(['pedido_fechas', 'up_users']);
        // $searchGeneralSellers = Vendedore::all();
        // $searchWithDrawal = OrdenesRetiro::with('ordenes_retiros_users_permissions_user_links');

        //SUMA ENTREGADOS
        // foreach ($searchGeneralProduct as $producto) {
        //     if ($producto->id_comercial == $upuser && $producto->status == "ENTREGADO") {
        //         $sumaEntregados += floatval($producto->precio_total);
        //     }
        // }
        $fechaServidor = date('j/n/Y');

        $sumaEntregados = PedidosShopify::where('id_comercial', $upuser)
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('status', 'ENTREGADO')
            ->sum('precio_total');
        error_log("sumaEntregados: $sumaEntregados");
        //OBTENER COSTO SUMA SELLER
        // foreach ($searchGeneralSellers as $seller) {
        //     if ($seller->id_master == $upuser) {
        //         $sumaCostoInicial += floatval($seller->costo_envio);
        //     }
        // }

        $AmountProductWarehouse = PedidosShopify::where('id_comercial', $upuser)
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('status', 'ENTREGADO')
            ->sum('value_product_warehouse');

        $refererValue = PedidosShopify::where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('status', 'ENTREGADO')
            ->whereHas('users.vendedores', function ($query) use ($upuser) {
                $query->where('referer', $upuser);
            })
            ->sum('value_referer');


        error_log("AmountProductWarehouse: $AmountProductWarehouse");
        error_log("refererValue: $refererValue");


        // $sumaCostoInicial = Vendedore::where('id_master', $upuser)
        //     ->sum('costo_envio');

        //SUMA COSTO
        // foreach ($searchGeneralProduct as $producto) {
        //     if ($producto->id_comercial == $upuser && ($producto->status == "ENTREGADO" || $producto->status == "NO ENTREGADO")) {
        //         $sumaCosto += $sumaCostoInicial;
        //     }
        // }

        // $sumaCostodb = DB::table('pedidos_shopifies')
        //     ->join('pedidos_shopifies_ruta_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_ruta_links.pedidos_shopify_id')
        //     // ->join('pedidos_shopifies_transportadora_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_transportadora_links.pedidos_shopify_id')
        //     ->selectRaw('SUM(' . $sumaCostoInicial . ') as sumaCosto')
        //     ->where('estado_interno', 'CONFIRMADO')
        //     ->where('estado_logistico', 'ENVIADO')
        //     ->where('id_comercial', $upuser)
        //     ->where(function ($query) {
        //         $query->where('status', 'ENTREGADO')
        //             ->orWhere('status', 'NO ENTREGADO');
        //     })
        //     ->first();
        // $sumaCosto = $sumaCostodb->sumaCosto;

        $sumaCosto = PedidosShopify::where('id_comercial', $upuser)
            ->with(['pedidoCarrier'])
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where(function ($query) {
                $query->where('status', 'ENTREGADO')
                    ->orWhere('status', 'NO ENTREGADO');
            })
            ->whereDoesntHave('pedidoCarrier')
            ->sum('costo_envio');

        error_log("sumaCosto TranspInt: $sumaCosto");

        $sumaCostodbCE = DB::table('pedidos_shopifies')
            ->join('pedidos_shopifies_carrier_external_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_carrier_external_links.pedidos_shopify_id')
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('id_comercial', $upuser)
            ->where(function ($query) {
                $query->whereIn('status', ['ENTREGADO', 'NOVEDAD']);
            })
            ->sum('costo_envio');
        error_log("sumaCostodbCE: $sumaCostodbCE");

        $sumaCosto += $sumaCostodbCE;
        error_log("sumaCosto Envio: $sumaCosto");


        //OBTENER DEVOLUCION SUMA SELLER
        // foreach ($searchGeneralSellers as $seller) {
        //     if ($seller->id_master == $upuser) {
        //         $sumaDevolucionInicial += floatval($seller->costo_devolucion);
        //     }
        // }

        // $sumaDevolucionInicial = DB::table('vendedores')
        //     ->where('id_master', $upuser)
        //     ->sum('costo_devolucion');

        //SUMA DEVOLUCION
        // foreach ($searchGeneralProduct as $producto) {
        //     if (
        //         $producto->id_comercial == $upuser &&
        //         ($producto->estado_devolucion == "ENTREGADO EN OFICINA" ||
        //             $producto->estado_devolucion == "DEVOLUCION EN RUTA" ||
        //             $producto->estado_devolucion == "EN BODEGA"
        //         ) &&
        //         $producto->status == "NOVEDAD"
        //     ) {
        //         $sumaDevolucion += $sumaDevolucionInicial;
        //     }
        // }
        /*
        $sumaDevolucion = DB::table('pedidos_shopifies')
            //->leftJoin('pedidos_shopifies_carrier_external_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_carrier_external_links.pedidos_shopify_id')
            ->join('pedidos_shopifies_ruta_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_ruta_links.pedidos_shopify_id')
            ->where('id_comercial', $upuser)
            // ->where(function ($query) {
            //     $query->orWhere('estado_devolucion', 'ENTREGADO EN OFICINA')
            //         ->orWhere('estado_devolucion', 'DEVOLUCION EN RUTA')
            //         ->orWhere('estado_devolucion', 'EN BODEGA')
            //     ->orWhere('estado_devolucion', 'EN BODEGA PROVEEDOR');

            // })
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('status', 'NOVEDAD')
            ->where(function ($query) {
                $query->where('estado_devolucion', '<>', 'PENDIENTE')
                    ->where(function ($query) {
                        $query->orWhere('estado_devolucion', 'ENTREGADO EN OFICINA')
                            ->orWhere('estado_devolucion', 'DEVOLUCION EN RUTA')
                            ->orWhere('estado_devolucion', 'EN BODEGA')
                            ->orWhere('estado_devolucion', 'EN BODEGA PROVEEDOR');
                    });
            })
            ->sum(DB::raw($sumaDevolucionInicial));

        // error_log("sumaDevolucion TranspInt: $sumaDevolucion");

        $sumaDevolucionCE = DB::table('pedidos_shopifies')
            ->join('pedidos_shopifies_carrier_external_links', 'pedidos_shopifies.id', '=', 'pedidos_shopifies_carrier_external_links.pedidos_shopify_id')
            ->where('id_comercial', $upuser)
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('status', 'NOVEDAD')
            ->where(function ($query) {
                $query->where('estado_devolucion', '<>', 'PENDIENTE')
                    ->where(function ($query) {
                        $query->orWhere('estado_devolucion', 'ENTREGADO EN OFICINA')
                            ->orWhere('estado_devolucion', 'DEVOLUCION EN RUTA')
                            ->orWhere('estado_devolucion', 'EN BODEGA')
                            ->orWhere('estado_devolucion', 'EN BODEGA PROVEEDOR');
                    });
            })
            ->sum('costo_devolucion');

        // error_log("sumaDevolucionCE: $sumaDevolucionCE");
        $sumaDevolucion += $sumaDevolucionCE;
        */

        $sumaDevolucion = PedidosShopify::where('id_comercial', $upuser)
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->where('status', 'NOVEDAD')
            ->whereNotIn('estado_devolucion', ['PENDIENTE'])
            ->sum('costo_devolucion');

        error_log("sumaDevolucion : $sumaDevolucion");

        // foreach ($searchWithDrawal as $retiro) {
        //     if (
        //         $retiro->ordenes_retiros_users_permissions_user_links->upuser == $upuser &&
        //         $retiro->estado == "REALIZADO"
        //     ) {
        //         $sumaRetiros += floatval($retiro->monto);
        //     }
        // }
        $sumaRetiros = OrdenesRetiro::join('ordenes_retiros_users_permissions_user_links as l', 'ordenes_retiros.id', '=', 'l.ordenes_retiro_id')
            ->where('l.user_id', $upuser)
            // ->where('ordenes_retiros.estado', 'REALIZADO')
            ->where(function ($query) {
                $query->where('ordenes_retiros.estado', 'APROBADO')
                    ->orWhere('ordenes_retiros.estado', 'REALIZADO');
            })
            ->sum('ordenes_retiros.monto');
        error_log("sumaRetiros: $sumaRetiros");

        $sumaEntregados = round($sumaEntregados, 2);
        $sumaCosto = round($sumaCosto, 2);
        $sumaDevolucion = round($sumaDevolucion, 2);
        $sumaDevolucion = round($sumaDevolucion, 2);

        $responseFinal = ($sumaEntregados + $refererValue) - ($sumaCosto + $sumaDevolucion + $AmountProductWarehouse);

        $responseFinal = $responseFinal -  $sumaRetiros;

        Log::info($sumaEntregados);
        Log::info($refererValue);
        Log::info($sumaCosto);
        Log::info($sumaDevolucion);
        Log::info($sumaRetiros);
        Log::info($AmountProductWarehouse);
        Log::info($responseFinal);
        return [
            'code' => 200,
            'value' => $responseFinal
        ];
    }
}
