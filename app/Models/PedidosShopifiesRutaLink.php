<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosShopifiesRutaLink
 * 
 * @property int $id
 * @property int|null $pedidos_shopify_id
 * @property int|null $ruta_id
 * @property float|null $pedidos_shopify_order
 * 
 * @property PedidosShopify|null $pedidos_shopify
 * @property Ruta|null $ruta
 *
 * @package App\Models
 */
class PedidosShopifiesRutaLink extends Model
{
	protected $table = 'pedidos_shopifies_ruta_links';
	public $timestamps = false;

	protected $casts = [
		'pedidos_shopify_id' => 'int',
		'ruta_id' => 'int',
		'pedidos_shopify_order' => 'float'
	];

	protected $fillable = [
		'pedidos_shopify_id',
		'ruta_id',
		'pedidos_shopify_order'
	];

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}

	public function ruta()
	{
		return $this->belongsTo(Ruta::class);
	}
}
