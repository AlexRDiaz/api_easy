<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AdminPermission
 * 
 * @property int $id
 * @property string|null $action
 * @property string|null $subject
 * @property string|null $properties
 * @property string|null $conditions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|AdminPermissionsRoleLink[] $admin_permissions_role_links
 *
 * @package App\Models
 */
class AdminPermission extends Model
{
	protected $table = 'admin_permissions';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'action',
		'subject',
		'properties',
		'conditions',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function admin_permissions_role_links()
	{
		return $this->hasMany(AdminPermissionsRoleLink::class, 'permission_id');
	}
}
