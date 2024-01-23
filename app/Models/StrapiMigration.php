<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StrapiMigration
 * 
 * @property int $id
 * @property string|null $name
 * @property Carbon|null $time
 *
 * @package App\Models
 */
class StrapiMigration extends Model
{
	protected $table = 'strapi_migrations';
	public $timestamps = false;

	protected $casts = [
		'time' => 'datetime'
	];

	protected $fillable = [
		'name',
		'time'
	];
}
