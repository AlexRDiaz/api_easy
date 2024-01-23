<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Status
 * 
 * @property int $id
 * @property string $name
 * @property float $delivery_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Status extends Model
{
	protected $table = 'statuses';

	protected $casts = [
		'delivery_price' => 'float'
	];

	protected $fillable = [
		'name',
		'delivery_price'
	];
}
