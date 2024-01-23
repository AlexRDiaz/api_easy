<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosShopifiesTransportadoraLink
 * 
 * @property int $id
 * @property int|null $pedidos_shopify_id
 * @property int|null $transportadora_id
 * @property float|null $pedidos_shopify_order
 * 
 * @property PedidosShopify|null $pedidos_shopify
 * @property Transportadora|null $transportadora
 *
 * @package App\Models
 */
class PedidosShopifiesTransportadoraLink extends Model
{
	protected $table = 'pedidos_shopifies_transportadora_links';
	public $timestamps = false;

	protected $casts = [
		'pedidos_shopify_id' => 'int',
		'transportadora_id' => 'int',
		'pedidos_shopify_order' => 'float'
	];

	protected $fillable = [
		'pedidos_shopify_id',
		'transportadora_id',
		'pedidos_shopify_order'
	];

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}

	public function transportadora()
	{
		return $this->belongsTo(Transportadora::class);
	}
}
