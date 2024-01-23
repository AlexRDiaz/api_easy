<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class NovedadesPedidosShopifyLink
 * 
 * @property int $id
 * @property int|null $novedad_id
 * @property int|null $pedidos_shopify_id
 * @property float|null $novedad_order
 * 
 * @property Novedade|null $novedade
 * @property PedidosShopify|null $pedidos_shopify
 *
 * @package App\Models
 */
class NovedadesPedidosShopifyLink extends Model
{
	protected $table = 'novedades_pedidos_shopify_links';
	public $timestamps = false;

	protected $casts = [
		'novedad_id' => 'int',
		'pedidos_shopify_id' => 'int',
		'novedad_order' => 'float'
	];

	protected $fillable = [
		'novedad_id',
		'pedidos_shopify_id',
		'novedad_order'
	];

	public function novedade()
	{
		return $this->belongsTo(Novedade::class, 'novedad_id');
	}

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}
}
