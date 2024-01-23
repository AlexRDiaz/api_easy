<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UpPermission
 * 
 * @property int $id
 * @property string|null $action
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|UpPermissionsRoleLink[] $up_permissions_role_links
 *
 * @package App\Models
 */
class UpPermission extends Model
{
	protected $table = 'up_permissions';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'action',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function up_permissions_role_links()
	{
		return $this->hasMany(UpPermissionsRoleLink::class, 'permission_id');
	}
}
