<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SubRuta
 * 
 * @property int $id
 * @property string|null $titulo
 * @property string|null $id_operadora
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|Operadore[] $operadores
 * @property Collection|PedidosShopifiesSubRutaLink[] $pedidos_shopifies_sub_ruta_links
 * @property Collection|Ruta[] $rutas
 *
 * @package App\Models
 */
class SubRuta extends Model
{
	protected $table = 'sub_rutas';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'id',
		'titulo',
		'id_operadora',
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
		return $this->belongsToMany(Operadore::class, 'operadores_sub_ruta_links')
					->withPivot('id', 'operadore_order');
	}

	public function pedidos_shopifies_sub_ruta_links()
	{
		return $this->hasMany(PedidosShopifiesSubRutaLink::class);
	}

	public function rutas()
	{
		return $this->belongsToMany(Ruta::class, 'sub_rutas_ruta_links')
					->withPivot('id', 'sub_ruta_order');
	}
}
