<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Region
 * 
 * @property int $id
 * @property string $name
 * @property string|null $translations
 * @property Carbon|null $created_at
 * @property Carbon $updated_at
 * @property bool $flag
 * @property string|null $wikiDataId
 * 
 * @property Collection|Country[] $countries
 * @property Collection|Subregion[] $subregions
 *
 * @package App\Models
 */
class Region extends Model
{
	protected $table = 'regions';

	protected $casts = [
		'flag' => 'bool'
	];

	protected $fillable = [
		'name',
		'translations',
		'flag',
		'wikiDataId'
	];

	public function countries()
	{
		return $this->hasMany(Country::class);
	}

	public function subregions()
	{
		return $this->hasMany(Subregion::class);
	}
}
