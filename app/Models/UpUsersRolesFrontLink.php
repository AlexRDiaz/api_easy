<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UpUsersRolesFrontLink
 * 
 * @property int $id
 * @property int|null $user_id
 * @property int|null $roles_front_id
 * @property float|null $user_order
 * 
 * @property UpUser|null $up_user
 * @property RolesFront|null $roles_front
 *
 * @package App\Models
 */
class UpUsersRolesFrontLink extends Model
{
	protected $table = 'up_users_roles_front_links';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'roles_front_id' => 'int',
		'user_order' => 'float'
	];

	protected $fillable = [
		'user_id',
		'roles_front_id',
		'user_order'
	];

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'user_id');
	}

	public function roles_front()
	{
		return $this->belongsTo(RolesFront::class);
	}
}
