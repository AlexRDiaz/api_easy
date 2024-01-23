<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Vendedore
 * 
 * @property int $id
 * @property string|null $nombre_comercial
 * @property string|null $telefono_1
 * @property string|null $telefono_2
 * @property string|null $costo_envio
 * @property string|null $costo_devolucion
 * @property string|null $fecha_alta
 * @property string|null $id_master
 * @property string|null $url_tienda
 * @property string|null $referer
 * @property string|null $referer_cost
 * @property string|null $monto_inicial
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|UpUser[] $up_users
 *
 * @package App\Models
 */
class Vendedore extends Model
{
	protected $table = 'vendedores';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'nombre_comercial',
		'telefono_1',
		'telefono_2',
		'costo_envio',
		'costo_devolucion',
		'fecha_alta',
		'id_master',
		'url_tienda',
		'referer',
		'referer_cost',
		'monto_inicial',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function up_users()
	{
		return $this->belongsToMany(UpUser::class, 'up_users_vendedores_links', 'vendedor_id', 'user_id')
					->withPivot('id', 'vendedor_order', 'user_order');
	}
}
