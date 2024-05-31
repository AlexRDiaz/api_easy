<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosShopify
 * 
 * @property int $id
 * @property Carbon|null $admission_date
 * @property Carbon|null $delivery_date
 * @property string|null $status
 * @property string|null $return_state
 * @property string|null $id_order
 * @property string|null $code
 * @property string|null $origin
 * @property float|null $withdrawal_price
 * @property float|null $value_order
 * @property float|null $return_cost
 * @property float|null $delivery_cost
 * @property float|null $notdelivery_cost
 * @property float|null $provider_cost
 * @property float|null $referer_cost
 * @property float|null $total_transaction
 * @property float|null $previous_value
 * @property float|null $current_value
 * @property bool|null $state
 * @property int $id_seller
 * @property float|null $internal_transportation_cost, 
 * @property float|null $external_transportation_cost, 
 * @property float|null $external_return_cost
 * @property int $order_entry
 * 
 * 
 * 
//  * @property AdminUser|null $admin_user
//  * @property Collection|Novedade[] $novedades
//  * @property Collection|PedidosShopifiesOperadoreLink[] $pedidos_shopifies_operadore_links
//  * @property Collection|PedidosShopifiesPedidoFechaLink[] $pedidos_shopifies_pedido_fecha_links
//  * @property Collection|PedidosShopifiesRutaLink[] $pedidos_shopifies_ruta_links
//  * @property Collection|PedidosShopifiesSubRutaLink[] $pedidos_shopifies_sub_ruta_links
//  * @property Collection|PedidosShopifiesTransportadoraLink[] $pedidos_shopifies_transportadora_links
//  * @property Collection|ProductoShopifiesPedidosShopifyLink[] $producto_shopifies_pedidos_shopify_links
//  * @property Collection|TransaccionPedidoTransportadora[] $transaccion_pedido_transportadoras
//  * @property Collection|UpUsersPedidosShopifiesLink[] $up_users_pedidos_shopifies_links
//  *

 * @package App\Models
 */
class TransactionsGlobal extends Model
{
	protected $table = 'transactions_global';
    public $timestamps = false;

	protected $casts = [
        "admission_date" => 'datetime', 
        "delivery_date" => 'datetime', 
        "withdrawal_price" => 'float', 
        "value_order" => 'float', 
        "return_cost" => 'float', 
        "delivery_cost" => 'float', 
        "notdelivery_cost" => 'float', 
        "provider_cost" => 'float', 
        "referer_cost" => 'float', 
        "total_transaction" => 'float', 
        "previous_value" => 'float', 
        "current_value" => 'float', 
        "state" => 'bool', 
        "id_seller" => 'int',
        "internal_transportation_cost" => 'float', 
        "external_transportation_cost" => 'float', 
        "external_return_cost" => 'float',
        "order_entry" => 'int'
    
    ];

	protected $fillable = [
		"admission_date", 
        "delivery_date", 
        "status",
        "return_state",
        "id_order", 
        "code", 
        "origin", 
        "withdrawal_price", 
        "value_order", 
        "return_cost", 
        "delivery_cost", 
        "notdelivery_cost", 
        "provider_cost", 
        "referer_cost", 
        "total_transaction", 
        "previous_value", 
        "current_value", 
        "state", 
        "id_seller",
        "internal_transportation_cost", 
        "external_transportation_cost", 
        "external_return_cost",
        // "order_entry"
	];

	
}
