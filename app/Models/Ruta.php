<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Ruta
 * 
 * @property int $id
 * @property string|null $titulo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|PedidosShopifiesRutaLink[] $pedidos_shopifies_ruta_links
 * @property Collection|SubRutasRutaLink[] $sub_rutas_ruta_links
 * @property Collection|Transportadora[] $transportadoras
 *
 * @package App\Models
 */
class Ruta extends Model
{
	protected $table = 'rutas';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'titulo',
		'created_by_id',
		'updated_by_id',
		'active'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function pedidos_shopifies_ruta_links()
	{
		return $this->hasMany(PedidosShopifiesRutaLink::class);
	}

	public function sub_rutas_ruta_links()
	{
		return $this->hasMany(SubRutasRutaLink::class);
	}

	public function sub_rutas()
    {
        return $this->hasManyThrough(SubRuta::class, SubRutasRutaLink::class, 'ruta_id', 'id', 'id', 'sub_ruta_id');
    }

	public function transportadoras()
	{
		return $this->belongsToMany(Transportadora::class, 'transportadoras_rutas_links')
					->withPivot('id', 'ruta_order', 'transportadora_order');
	}
}
