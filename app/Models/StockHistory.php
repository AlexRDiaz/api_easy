<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StockHistory
 * 
 * @property int $id
 * @property int|null $product_id
 * @property string $variant_sku
 * @property int $type
 * @property Carbon $date
 * @property int $units
 * @property int $last_stock
 * @property int $current_stock
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Product|null $product
 *
 * @package App\Models
 */
class StockHistory extends Model
{

	protected $table = 'stock_history';

	protected $casts = [
		'product_id' => 'int',
		'type' => 'int',
		'date' => 'datetime',
		'units' => 'int',
		'last_stock' => 'int',
		'current_stock' => 'int'
	];

	protected $fillable = [
		'product_id',
		'variant_sku',
		'type',
		'date',
		'units',
		'last_stock',
		'current_stock',
		'description'
	];

	public function product()
	{
		return $this->belongsTo(Product::class);
	}
}

