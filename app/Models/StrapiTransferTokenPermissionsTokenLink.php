<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StrapiTransferTokenPermissionsTokenLink
 * 
 * @property int $id
 * @property int|null $transfer_token_permission_id
 * @property int|null $transfer_token_id
 * @property float|null $transfer_token_permission_order
 * 
 * @property StrapiTransferTokenPermission|null $strapi_transfer_token_permission
 * @property StrapiTransferToken|null $strapi_transfer_token
 *
 * @package App\Models
 */
class StrapiTransferTokenPermissionsTokenLink extends Model
{
	protected $table = 'strapi_transfer_token_permissions_token_links';
	public $timestamps = false;

	protected $casts = [
		'transfer_token_permission_id' => 'int',
		'transfer_token_id' => 'int',
		'transfer_token_permission_order' => 'float'
	];

	protected $fillable = [
		'transfer_token_permission_id',
		'transfer_token_id',
		'transfer_token_permission_order'
	];

	public function strapi_transfer_token_permission()
	{
		return $this->belongsTo(StrapiTransferTokenPermission::class, 'transfer_token_permission_id');
	}

	public function strapi_transfer_token()
	{
		return $this->belongsTo(StrapiTransferToken::class, 'transfer_token_id');
	}
}
