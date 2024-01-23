<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProductoShopify
 * 
 * @property int $id
 * @property string|null $id_shopify
 * @property string|null $cantidad
 * @property string|null $precio
 * @property string|null $titulo
 * @property string|null $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|ProductoShopifiesPedidosShopifyLink[] $producto_shopifies_pedidos_shopify_links
 *
 * @package App\Models
 */
class ProductoShopify extends Model
{
	protected $table = 'producto_shopifies';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'id_shopify',
		'cantidad',
		'precio',
		'titulo',
		'estado',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function producto_shopifies_pedidos_shopify_links()
	{
		return $this->hasMany(ProductoShopifiesPedidosShopifyLink::class);
	}
}
