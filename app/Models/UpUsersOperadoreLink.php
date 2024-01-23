<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UpUsersOperadoreLink
 * 
 * @property int $id
 * @property int|null $user_id
 * @property int|null $operadore_id
 * 
 * @property UpUser|null $up_user
 * @property Operadore|null $operadore
 *
 * @package App\Models
 */
class UpUsersOperadoreLink extends Model
{
	protected $table = 'up_users_operadore_links';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'operadore_id' => 'int'
	];

	protected $fillable = [
		'user_id',
		'operadore_id'
	];

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'user_id');
	}

	public function operadore()
	{
		return $this->belongsTo(Operadore::class);
	}
}
