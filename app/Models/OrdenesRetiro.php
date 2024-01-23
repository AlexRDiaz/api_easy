<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class OrdenesRetiro
 * 
 * @property int $id
 * @property string|null $monto
 * @property string|null $codigo
 * @property string|null $fecha
 * @property string|null $estado
 * @property string|null $codigo_generado
 * @property string|null $fecha_transferencia
 * @property string|null $comprobante
 * @property string|null $comentario
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property string|null $id_vendedor
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|OrdenesRetirosUsersPermissionsUserLink[] $ordenes_retiros_users_permissions_user_links
 *
 * @package App\Models
 */
class OrdenesRetiro extends Model
{
	protected $table = 'ordenes_retiros';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'monto',
		'codigo',
		'fecha',
		'estado',
		'codigo_generado',
		'fecha_transferencia',
		'comprobante',
		'comentario',
		'created_by_id',
		'updated_by_id',
		'id_vendedor',
		'account_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function ordenes_retiros_users_permissions_user_links()
	{
		return $this->hasMany(OrdenesRetirosUsersPermissionsUserLink::class);
	}

	public function users_permissions_user()
    {
        return $this->hasManyThrough(UpUser::class, OrdenesRetirosUsersPermissionsUserLink::class, 'ordenes_retiro_id', 'id', 'id', 'user_id');
    }

	public function users_permissions()
	{
		return $this->belongsToMany(UpUser::class, OrdenesRetirosUsersPermissionsUserLink::class, 'user_id')
					->withPivot('id');
	}
}
