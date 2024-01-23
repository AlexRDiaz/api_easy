<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosShopifiesSubRutaLink
 * 
 * @property int $id
 * @property int|null $pedidos_shopify_id
 * @property int|null $sub_ruta_id
 * @property float|null $pedidos_shopify_order
 * 
 * @property PedidosShopify|null $pedidos_shopify
 * @property SubRuta|null $sub_ruta
 *
 * @package App\Models
 */
class PedidosShopifiesSubRutaLink extends Model
{
	protected $table = 'pedidos_shopifies_sub_ruta_links';
	public $timestamps = false;

	protected $casts = [
		'pedidos_shopify_id' => 'int',
		'sub_ruta_id' => 'int',
		'pedidos_shopify_order' => 'float'
	];

	protected $fillable = [
		'pedidos_shopify_id',
		'sub_ruta_id',
		'pedidos_shopify_order'
	];

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}

	public function sub_ruta()
	{
		return $this->belongsTo(SubRuta::class);
	}
}
