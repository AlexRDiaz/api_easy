<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StrapiApiToken
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $type
 * @property string|null $access_key
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property int|null $lifespan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|StrapiApiTokenPermissionsTokenLink[] $strapi_api_token_permissions_token_links
 *
 * @package App\Models
 */
class StrapiApiToken extends Model
{
	protected $table = 'strapi_api_tokens';

	protected $casts = [
		'last_used_at' => 'datetime',
		'expires_at' => 'datetime',
		'lifespan' => 'int',
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'type',
		'access_key',
		'last_used_at',
		'expires_at',
		'lifespan',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function strapi_api_token_permissions_token_links()
	{
		return $this->hasMany(StrapiApiTokenPermissionsTokenLink::class, 'api_token_id');
	}
}
