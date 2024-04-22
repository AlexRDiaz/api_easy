<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProductWarehouseLink
 * 
 * @property int $id
 * @property int|null $id_product
 * @property int|null $id_warehouse
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Product|null $product
 * @property Warehouse|null $warehouse
 *
 * @package App\Models
 */
class ProductWarehouseLink extends Model
{
	protected $table = 'product_warehouse_link';

	protected $casts = [
		'id_product' => 'int',
		'id_warehouse' => 'int'
	];

	protected $fillable = [
		'id_product',
		'id_warehouse'
	];

	public function product()
	{
		return $this->belongsTo(Product::class, 'id_product');
	}

	public function warehouse()
	{
		return $this->belongsTo(Warehouse::class, 'id_warehouse');
	}
}
