<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosShopifiesPedidoFechaLink
 * 
 * @property int $id
 * @property int|null $pedidos_shopify_id
 * @property int|null $pedido_fecha_id
 * @property float|null $pedidos_shopify_order
 * 
 * @property PedidosShopify|null $pedidos_shopify
 * @property PedidoFecha|null $pedido_fecha
 *
 * @package App\Models
 */
class PedidosShopifiesPedidoFechaLink extends Model
{
	protected $table = 'pedidos_shopifies_pedido_fecha_links';
	public $timestamps = false;

	protected $casts = [
		'pedidos_shopify_id' => 'int',
		'pedido_fecha_id' => 'int',
		'pedidos_shopify_order' => 'float'
	];

	protected $fillable = [
		'pedidos_shopify_id',
		'pedido_fecha_id',
		'pedidos_shopify_order'
	];

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}

	public function pedido_fecha()
	{
		return $this->belongsTo(PedidoFecha::class);
	}
}
