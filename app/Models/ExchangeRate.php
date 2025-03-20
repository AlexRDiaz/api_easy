<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExchangeRate
 * 
 * @property int $id
 * @property int $country_id
 * @property string $source
 * @property float $rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $updated_by
 * 
 * @property Country $country
 *
 * @package App\Models
 */
class ExchangeRate extends Model
{
	protected $table = 'exchange_rates';

	protected $casts = [
		'country_id' => 'int',
		'rate' => 'float',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'country_id',
		'source',
		'rate',
		'updated_by'
	];

	public function country()
	{
		return $this->belongsTo(Country::class);
	}
}
