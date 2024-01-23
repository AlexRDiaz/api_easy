<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProductoShopifiesPedidosShopifyLink
 * 
 * @property int $id
 * @property int|null $producto_shopify_id
 * @property int|null $pedidos_shopify_id
 * @property float|null $producto_shopify_order
 * 
 * @property ProductoShopify|null $producto_shopify
 * @property PedidosShopify|null $pedidos_shopify
 *
 * @package App\Models
 */
class ProductoShopifiesPedidosShopifyLink extends Model
{
	protected $table = 'producto_shopifies_pedidos_shopify_links';
	public $timestamps = false;

	protected $casts = [
		'producto_shopify_id' => 'int',
		'pedidos_shopify_id' => 'int',
		'producto_shopify_order' => 'float'
	];

	protected $fillable = [
		'producto_shopify_id',
		'pedidos_shopify_id',
		'producto_shopify_order'
	];

	public function producto_shopify()
	{
		return $this->belongsTo(ProductoShopify::class);
	}

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}
}
