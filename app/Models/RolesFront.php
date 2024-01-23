<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RolesFront
 * 
 * @property int $id
 * @property string|null $titulo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $published_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|UpUser[] $up_users
 *
 * @package App\Models
 */
class RolesFront extends Model
{
	protected $table = 'roles_fronts';

	protected $casts = [
		'published_at' => 'datetime',
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'titulo',
		'published_at',
		'created_by_id',
		'updated_by_id',
		'accesos'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function up_users()
	{
		return $this->belongsToMany(UpUser::class, 'up_users_roles_front_links', 'roles_front_id', 'user_id')
					->withPivot('id', 'user_order');
	}
}
