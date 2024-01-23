<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class OrdenesRetirosUsersPermissionsUserLink
 * 
 * @property int $id
 * @property int|null $ordenes_retiro_id
 * @property int|null $user_id
 * @property float|null $ordenes_retiro_order
 * 
 * @property OrdenesRetiro|null $ordenes_retiro
 * @property UpUser|null $up_user
 *
 * @package App\Models
 */
class OrdenesRetirosUsersPermissionsUserLink extends Model
{
	protected $table = 'ordenes_retiros_users_permissions_user_links';
	public $timestamps = false;

	protected $casts = [
		'ordenes_retiro_id' => 'int',
		'user_id' => 'int',
		'ordenes_retiro_order' => 'float'
	];

	protected $fillable = [
		'ordenes_retiro_id',
		'user_id',
		'ordenes_retiro_order'
	];

	public function ordenes_retiro()
	{
		return $this->belongsTo(OrdenesRetiro::class);
	}

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'user_id');
	}
}
