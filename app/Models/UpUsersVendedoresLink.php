<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UpUsersVendedoresLink
 * 
 * @property int $id
 * @property int|null $user_id
 * @property int|null $vendedor_id
 * @property float|null $vendedor_order
 * @property float|null $user_order
 * 
 * @property UpUser|null $up_user
 * @property Vendedore|null $vendedore
 *
 * @package App\Models
 */
class UpUsersVendedoresLink extends Model
{
	protected $table = 'up_users_vendedores_links';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'vendedor_id' => 'int',
		'vendedor_order' => 'float',
		'user_order' => 'float'
	];

	protected $fillable = [
		'user_id',
		'vendedor_id',
		'vendedor_order',
		'user_order'
	];

	public function up_user()
	{
		return $this->belongsTo(UpUser::class, 'user_id');
	}

	public function vendedore()
	{
		return $this->belongsTo(Vendedore::class, 'vendedor_id');
	}
}
