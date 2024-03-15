<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CarrierCoverage
 * 
 * @property int $id
 * @property int|null $id_coverage
 * @property int|null $id_carrier
 * @property string|null $type
 * @property string|null $id_prov_ref
 * @property string|null $id_ciudad_ref
 * @property int|null $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property CarriersExternal|null $carriers_external
 * @property CoverageExternal|null $coverage_external
 *
 * @package App\Models
 */
class CarrierCoverage extends Model
{
	protected $table = 'carrier_coverage';

	protected $casts = [
		'id_coverage' => 'int',
		'id_carrier' => 'int',
		'active' => 'int'
	];

	protected $fillable = [
		'id_coverage',
		'id_carrier',
		'type',
		'id_prov_ref',
		'id_ciudad_ref',
		'active'
	];

	public function carriers_external()
	{
		return $this->belongsTo(CarriersExternal::class, 'id_carrier');
	}

	public function carriers_external_simple()
	{
		return $this->belongsTo(CarriersExternal::class, 'id_carrier')->select('id', 'name');
	}

	public function coverage_external()
	{
		return $this->belongsTo(CoverageExternal::class, 'id_coverage')
			->with('dpa_provincia');
	}
}
