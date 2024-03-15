<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CarriersExternal
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $status
 * @property string|null $type_coverage
 * @property string|null $costs
 * @property int|null $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|CarrierCoverage[] $carrier_coverages
 *
 * @package App\Models
 */
class CarriersExternal extends Model
{
	protected $table = 'carriers_external';

	protected $casts = [
		'active' => 'int'
	];

	protected $fillable = [
		'name',
		'phone',
		'email',
		'address',
		'status',
		'type_coverage',
		'costs',
		'active'
	];

	public function carrier_coverages()
	{
		return $this->hasMany(CarrierCoverage::class, 'id_carrier')->with('coverage_external');
	}
}
