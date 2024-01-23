<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Novedade
 * 
 * @property int $id
 * @property string|null $m_t_novedad
 * @property int|null $try
 * @property string|null $url_image
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $published_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|PedidosShopify[] $pedidos_shopifies
 *
 * @package App\Models
 */
class Novedade extends Model
{
	protected $table = 'novedades';

	protected $casts = [
		'try' => 'int',
		'published_at' => 'datetime',
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'm_t_novedad',
		'try',
		'url_image',
		'comment',
		'published_at',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function pedidos_shopifies()
	{
		return $this->belongsToMany(PedidosShopify::class, 'novedades_pedidos_shopify_links', 'novedad_id')
					->withPivot('id', 'novedad_order');
	}
}
