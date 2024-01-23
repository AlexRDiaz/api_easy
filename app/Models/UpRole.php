<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UpRole
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|UpPermissionsRoleLink[] $up_permissions_role_links
 * @property Collection|UpUsersRoleLink[] $up_users_role_links
 *
 * @package App\Models
 */
class UpRole extends Model
{
	protected $table = 'up_roles';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'type',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function up_permissions_role_links()
	{
		return $this->hasMany(UpPermissionsRoleLink::class, 'role_id');
	}

	public function up_users_role_links()
	{
		return $this->hasMany(UpUsersRoleLink::class, 'role_id');
	}
}
