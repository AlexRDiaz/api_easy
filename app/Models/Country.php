<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Country
 * 
 * @property int $id
 * @property string $name
 * @property string|null $iso3
 * @property string|null $numeric_code
 * @property string|null $iso2
 * @property string|null $phonecode
 * @property string|null $capital
 * @property string|null $currency
 * @property string|null $currency_name
 * @property string|null $currency_symbol
 * @property string|null $tld
 * @property string|null $native
 * @property string|null $region
 * @property int|null $region_id
 * @property string|null $subregion
 * @property int|null $subregion_id
 * @property string|null $nationality
 * @property string|null $timezones
 * @property string|null $translations
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $emoji
 * @property string|null $emojiU
 * @property Carbon|null $created_at
 * @property Carbon $updated_at
 * @property bool $flag
 * @property string|null $wikiDataId
 * 
 * @property Collection|CompanyCountriesLink[] $company_countries_links
 *
 * @package App\Models
 */
class Country extends Model
{
	protected $table = 'countries';

	protected $casts = [
		'region_id' => 'int',
		'subregion_id' => 'int',
		'latitude' => 'float',
		'longitude' => 'float',
		'flag' => 'bool'
	];

	protected $fillable = [
		'name',
		'iso3',
		'numeric_code',
		'iso2',
		'phonecode',
		'capital',
		'currency',
		'currency_name',
		'currency_symbol',
		'tld',
		'native',
		'region',
		'region_id',
		'subregion',
		'subregion_id',
		'nationality',
		'timezones',
		'translations',
		'latitude',
		'longitude',
		'emoji',
		'emojiU',
		'flag',
		'wikiDataId'
	];

	public function region()
	{
		return $this->belongsTo(Region::class);
	}

	public function subregion()
	{
		return $this->belongsTo(Subregion::class);
	}

	public function company_countries_links()
	{
		return $this->hasMany(CompanyCountriesLink::class);
	}
}
