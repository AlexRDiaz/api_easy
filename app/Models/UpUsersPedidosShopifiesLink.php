<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UpUsersPedidosShopifiesLink
 * 
 * @property int $id
 * @property int|null $user_id
 * @property int|null $pedidos_shopify_id
 * @property float|null $pedidos_shopify_order
 * @property float|null $user_order
 * 
 * @property UpUser|null $up_user
 * @property PedidosShopify|null $pedidos_shopify
 *
 * @package App\Models
 */
class UpUsersPedidosShopifiesLink extends Model
{
	protected $table = 'up_users_pedidos_shopifies_links';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'pedidos_shopify_id' => 'int',
		'pedidos_shopify_order' => 'float',
		'user_order' => 'float'
	];

	protected $fillable = [
		'user_id',
		'pedidos_shopify_id',
		'pedidos_shopify_order',
		'user_order'
	];

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'user_id');
	}

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class);
	}
}
