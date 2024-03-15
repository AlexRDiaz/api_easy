<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CoverageExternal
 * 
 * @property int $id
 * @property string|null $ciudad
 * @property int|null $id_provincia
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property DpaProvincia|null $dpa_provincia
 * @property Collection|CarrierCoverage[] $carrier_coverages
 *
 * @package App\Models
 */
class CoverageExternal extends Model
{
	protected $table = 'coverage_external';

	protected $casts = [
		'id_provincia' => 'int'
	];

	protected $fillable = [
		'ciudad',
		'id_provincia'
	];

	public function dpa_provincia()
	{
		return $this->belongsTo(DpaProvincia::class, 'id_provincia');
	}

	public function carrier_coverages()
	{
		return $this->hasMany(CarrierCoverage::class, 'id_coverage');
	}
}
