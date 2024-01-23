<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StrapiApiTokenPermission
 * 
 * @property int $id
 * @property string|null $action
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
class StrapiApiTokenPermission extends Model
{
	protected $table = 'strapi_api_token_permissions';

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

	public function strapi_api_token_permissions_token_links()
	{
		return $this->hasMany(StrapiApiTokenPermissionsTokenLink::class, 'api_token_permission_id');
	}
}
