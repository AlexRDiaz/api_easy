<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StrapiApiTokenPermissionsTokenLink
 * 
 * @property int $id
 * @property int|null $api_token_permission_id
 * @property int|null $api_token_id
 * @property float|null $api_token_permission_order
 * 
 * @property StrapiApiTokenPermission|null $strapi_api_token_permission
 * @property StrapiApiToken|null $strapi_api_token
 *
 * @package App\Models
 */
class StrapiApiTokenPermissionsTokenLink extends Model
{
	protected $table = 'strapi_api_token_permissions_token_links';
	public $timestamps = false;

	protected $casts = [
		'api_token_permission_id' => 'int',
		'api_token_id' => 'int',
		'api_token_permission_order' => 'float'
	];

	protected $fillable = [
		'api_token_permission_id',
		'api_token_id',
		'api_token_permission_order'
	];

	public function strapi_api_token_permission()
	{
		return $this->belongsTo(StrapiApiTokenPermission::class, 'api_token_permission_id');
	}

	public function strapi_api_token()
	{
		return $this->belongsTo(StrapiApiToken::class, 'api_token_id');
	}
}
