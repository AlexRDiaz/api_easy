<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Subregion
 * 
 * @property int $id
 * @property string $name
 * @property string|null $translations
 * @property int $region_id
 * @property Carbon|null $created_at
 * @property Carbon $updated_at
 * @property bool $flag
 * @property string|null $wikiDataId
 * 
 * @property Region $region
 * @property Collection|Country[] $countries
 *
 * @package App\Models
 */
class Subregion extends Model
{
	protected $table = 'subregions';

	protected $casts = [
		'region_id' => 'int',
		'flag' => 'bool'
	];

	protected $fillable = [
		'name',
		'translations',
		'region_id',
		'flag',
		'wikiDataId'
	];

	public function region()
	{
		return $this->belongsTo(Region::class);
	}

	public function countries()
	{
		return $this->hasMany(Country::class);
	}
}
