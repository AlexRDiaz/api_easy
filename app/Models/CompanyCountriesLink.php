<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CompanyCountriesLink
 * 
 * @property int $id
 * @property int $company_id
 * @property int $country_id
 * @property Carbon|null $created_at
 * 
 * @property Company $company
 * @property Country $country
 *
 * @package App\Models
 */
class CompanyCountriesLink extends Model
{
	protected $table = 'company_countries_link';
	public $timestamps = false;

	protected $casts = [
		'company_id' => 'int',
		'country_id' => 'int'
	];

	protected $fillable = [
		'company_id',
		'country_id'
	];

	public function company()
	{
		return $this->belongsTo(Company::class);
	}

	public function country()
	{
		return $this->belongsTo(Country::class);
	}
}
