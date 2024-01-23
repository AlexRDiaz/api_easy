<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosShopifiesOperadoreLink
 * 
 * @property int $id
 * @property int|null $pedidos_shopify_id
 * @property int|null $operadore_id
 * @property float|null $pedidos_shopify_order
 * 
 * @property PedidosShopify|null $pedidos_shopify
 * @property Operadore|null $operadore
 *
 * @package App\Models
 */
class PedidosShopifiesOperadoreLink extends Model
{
	protected $table = 'pedidos_shopifies_operadore_links';
	public $timestamps = false;

	protected $casts = [
		'pedidos_shopify_id' => 'int',
		'operadore_id' => 'int',
		'pedidos_shopify_order' => 'float'
	];

	protected $fillable = [
		'pedidos_shopify_id',
		'operadore_id',
		'pedidos_shopify_order'
	];

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}

	public function operadore()
	{
		return $this->belongsTo(Operadore::class);
	}

	
}
