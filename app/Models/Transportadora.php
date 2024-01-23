<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Transportadora
 * 
 * @property int $id
 * @property string|null $nombre
 * @property string|null $costo_transportadora
 * @property string|null $telefono_1
 * @property string|null $telefono_2
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|Operadore[] $operadores
 * @property Collection|PedidosShopifiesTransportadoraLink[] $pedidos_shopifies_transportadora_links
 * @property Collection|TransaccionPedidoTransportadora[] $transaccion_pedido_transportadoras
 * @property Collection|Ruta[] $rutas
 * @property Collection|TransportadorasShippingCost[] $transportadoras_shipping_costs
 * @property Collection|TransportadorasUsersPermissionsUserLink[] $transportadoras_users_permissions_user_links
 *
 * @package App\Models
 */
class Transportadora extends Model
{
	protected $table = 'transportadoras';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'transportadora_id',
		'nombre',
		'costo_transportadora',
		'telefono_1',
		'telefono_2',
		'created_by_id',
		'updated_by_id',
		'active'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function operadores()
	{
		return $this->belongsToMany(Operadore::class, 'operadores_transportadora_links')
					->withPivot('id', 'operadore_order');
	}

	public function pedidos_shopifies_transportadora_links()
	{
		return $this->hasMany(PedidosShopifiesTransportadoraLink::class);
	}

	public function transaccion_pedido_transportadoras()
	{
		return $this->hasMany(TransaccionPedidoTransportadora::class, 'id_transportadora');
	}

	public function rutas()
	{
		return $this->belongsToMany(Ruta::class, 'transportadoras_rutas_links')
					->withPivot('id', 'ruta_order', 'transportadora_order');
	}

	public function subrutas()
	{
		return $this->belongsToMany(SubRuta::class, 'transportadoras_rutas_links')
					->withPivot('id', 'ruta_order', 'transportadora_order');
	}

	public function transportadoras_users_permissions_user_links()
	{
		return $this->hasMany(TransportadorasUsersPermissionsUserLink::class);
	}

	public function pedidos()
	{
		return $this->hasManyThrough(PedidosShopify::class, PedidosShopifiesTransportadoraLink::class, 'transportadora_id', 'id', 'id', 'pedidos_shopify_id');
	}
}
