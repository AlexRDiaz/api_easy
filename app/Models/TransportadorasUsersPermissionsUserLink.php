<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TransportadorasUsersPermissionsUserLink
 * 
 * @property int $id
 * @property int|null $transportadora_id
 * @property int|null $user_id
 * 
 * @property Transportadora|null $transportadora
 * @property UpUser|null $up_user
 *
 * @package App\Models
 */
class TransportadorasUsersPermissionsUserLink extends Model
{
	protected $table = 'transportadoras_users_permissions_user_links';
	public $timestamps = false;

	protected $casts = [
		'transportadora_id' => 'int',
		'user_id' => 'int'
	];

	protected $fillable = [
		'transportadora_id',
		'user_id'
	];

	public function transportadora()
	{
		return $this->belongsTo(Transportadora::class);
	}

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'user_id');
	}
}
