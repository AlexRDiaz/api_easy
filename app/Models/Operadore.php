<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Operadore
 * 
 * @property int $id
 * @property string|null $telefono
 * @property string|null $costo_operador
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|SubRuta[] $sub_rutas
 * @property Collection|Transportadora[] $transportadoras
 * @property Collection|PedidosShopifiesOperadoreLink[] $pedidos_shopifies_operadore_links
 * @property Collection|UpUser[] $up_users
 *
 * @package App\Models
 */
class Operadore extends Model
{
	protected $table = 'operadores';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'id',
		'telefono',
		'costo_operador',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function sub_rutas()
	{
		return $this->belongsToMany(SubRuta::class, 'operadores_sub_ruta_links')
					->withPivot('id', 'operadore_order');
	}

	public function transportadoras()
	{
		return $this->belongsToMany(Transportadora::class, 'operadores_transportadora_links')
					->withPivot('id', 'operadore_order');
	}

	public function pedidos_shopifies_operadore_links()
	{
		return $this->hasMany(PedidosShopifiesOperadoreLink::class);
	}

	public function up_users()
	{
		return $this->belongsToMany(UpUser::class, 'up_users_operadore_links', 'operadore_id', 'user_id')
					->withPivot('id');
	}
}
