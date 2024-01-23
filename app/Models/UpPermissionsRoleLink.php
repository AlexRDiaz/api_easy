<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UpPermissionsRoleLink
 * 
 * @property int $id
 * @property int|null $permission_id
 * @property int|null $role_id
 * @property float|null $permission_order
 * 
 * @property UpPermission|null $up_permission
 * @property UpRole|null $up_role
 *
 * @package App\Models
 */
class UpPermissionsRoleLink extends Model
{
	protected $table = 'up_permissions_role_links';
	public $timestamps = false;

	protected $casts = [
		'permission_id' => 'int',
		'role_id' => 'int',
		'permission_order' => 'float'
	];

	protected $fillable = [
		'permission_id',
		'role_id',
		'permission_order'
	];

	public function up_permission()
	{
		return $this->belongsTo(UpPermission::class, 'permission_id');
	}

	public function up_role()
	{
		return $this->belongsTo(UpRole::class, 'role_id');
	}
}
