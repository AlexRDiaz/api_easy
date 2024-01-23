<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AdminUsersRolesLink
 * 
 * @property int $id
 * @property int|null $user_id
 * @property int|null $role_id
 * @property float|null $role_order
 * @property float|null $user_order
 * 
 * @property AdminUser|null $admin_user
 * @property AdminRole|null $admin_role
 *
 * @package App\Models
 */
class AdminUsersRolesLink extends Model
{
	protected $table = 'admin_users_roles_links';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'role_id' => 'int',
		'role_order' => 'float',
		'user_order' => 'float'
	];

	protected $fillable = [
		'user_id',
		'role_id',
		'role_order',
		'user_order'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'user_id');
	}

	public function admin_role()
	{
		return $this->belongsTo(AdminRole::class, 'role_id');
	}
}
