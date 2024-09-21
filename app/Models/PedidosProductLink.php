<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosProductLink
 * 
 * @property int $id
 * @property int|null $pedidos_shopify_id
 * @property int|null $product_id
 * @property Carbon|null $created_at
 * 
 * @property PedidosShopify|null $pedidos_shopify
 * @property Product|null $product
 *
 * @package App\Models
 */
class PedidosProductLink extends Model
{
	protected $table = 'pedidos_product_link';
	public $timestamps = false;

	protected $casts = [
		'pedidos_shopify_id' => 'int',
		'product_id' => 'int'
	];

	protected $fillable = [
		'pedidos_shopify_id',
		'product_id'
	];

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}

	public function product()
	{
		return $this->belongsTo(Product::class, 'product_id', 'product_id');
	}

	public function productSimple()
	{
		return $this->belongsTo(Product::class, 'product_id', 'product_id')
			->select('product_id', 'product_name', 'price');
	}

}
