<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UpUsersRoleLink
 * 
 * @property int $id
 * @property int|null $user_id
 * @property int|null $role_id
 * @property float|null $user_order
 * 
 * @property UpUser|null $up_user
 * @property UpRole|null $up_role
 *
 * @package App\Models
 */
class UpUsersRoleLink extends Model
{
	protected $table = 'up_users_role_links';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'role_id' => 'int',
		'user_order' => 'float'
	];

	protected $fillable = [
		'user_id',
		'role_id',
		'user_order'
	];

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'user_id');
	}

	public function up_role()
	{
		return $this->belongsTo(UpRole::class, 'role_id');
	}
}
