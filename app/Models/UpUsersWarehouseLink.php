<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UpUsersWarehouseLink
 * 
 * @property int $id
 * @property int|null $id_user
 * @property int|null $id_warehouse
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property UpUser|null $up_user
 * @property Warehouse|null $warehouse
 *
 * @package App\Models
 */
class UpUsersWarehouseLink extends Model
{
	protected $table = 'up_users_warehouse_link';

	protected $casts = [
		'id_user' => 'int',
		'id_warehouse' => 'int'
	];

	protected $fillable = [
		'id_user',
		'id_warehouse',
		'notify',
	];

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'id_user');
	}

	public function warehouse()
	{
		return $this->belongsTo(Warehouse::class, 'id_warehouse');
	}
}
