<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StrapiCoreStoreSetting
 * 
 * @property int $id
 * @property string|null $key
 * @property string|null $value
 * @property string|null $type
 * @property string|null $environment
 * @property string|null $tag
 *
 * @package App\Models
 */
class StrapiCoreStoreSetting extends Model
{
	protected $table = 'strapi_core_store_settings';
	public $timestamps = false;

	protected $fillable = [
		'key',
		'value',
		'type',
		'environment',
		'tag'
	];
}
