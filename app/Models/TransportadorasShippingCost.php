<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TransportadorasShippingCost
 * 
 * @property int $id
 * @property string $status
 * @property Carbon $time_stamp
 * @property float $daily_proceeds
 * @property float $daily_shipping_cost
 * @property float $daily_total
 * @property string|null $rejected_reason
 * @property string|null $url_proof_payment
 * @property int $id_transportadora
 * 
 * @property Transportadora $transportadora
 *
 * @package App\Models
 */
class TransportadorasShippingCost extends Model
{
	protected $table = 'transportadoras_shipping_cost';
	public $timestamps = false;

	protected $casts = [
		'time_stamp' => 'datetime',
		'daily_proceeds' => 'float',
		'daily_shipping_cost' => 'float',
		'daily_total' => 'float',
		'id_transportadora' => 'int'
	];

	protected $fillable = [
		'status',
		'time_stamp',
		'daily_proceeds',
		'daily_shipping_cost',
		'daily_total',
		'rejected_reason',
		'url_proof_payment',
		'id_transportadora'
	];

	public function transportadora()
	{
		return $this->belongsTo(Transportadora::class, 'id_transportadora');
	}
}
