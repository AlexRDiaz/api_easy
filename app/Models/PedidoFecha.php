<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidoFecha
 * 
 * @property int $id
 * @property string|null $fecha
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|PedidosShopifiesPedidoFechaLink[] $pedidos_shopifies_pedido_fecha_links
 *
 * @package App\Models
 */
class PedidoFecha extends Model
{
	protected $table = 'pedido_fechas';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'fecha',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function pedidos_shopifies_pedido_fecha_links()
	{
		return $this->hasMany(PedidosShopifiesPedidoFechaLink::class);
	}
}
