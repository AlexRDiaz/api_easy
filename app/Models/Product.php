<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Product
 * 
 * @property int $product_id
 * @property string|null $product_name
 * @property int|null $stock
 * @property float|null $price
 * @property string|null $url_img
 * @property int|null $isvariable
 * @property string|null $features
 * @property int|null $approved
 * @property int|null $active
 * @property int|null $warehouse_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Warehouse|null $warehouse
 *
 * @package App\Models
 */
class Product extends Model
{
	protected $table = 'products';
	protected $primaryKey = 'product_id';

	protected $casts = [
		'stock' => 'int',
		'price' => 'float',
		'isvariable' => 'int',
		'approved' => 'int',
		'active' => 'int',
		'warehouse_id' => 'int'
	];

	protected $fillable = [
		'product_name',
		'stock',
		'price',
		'url_img',
		'isvariable',
		'features',
		'approved',
		'active',
		'warehouse_id'
	];

	public function warehouse()
	{
		return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
	}

	public function productseller(): \Illuminate\Database\Eloquent\Relations\HasMany
	{
		return $this->hasMany(\App\Models\ProductsSellerLink::class, 'product_id');
	}

	public function reserve(): \Illuminate\Database\Eloquent\Relations\HasMany
	{
		return $this->hasMany(\App\Models\Reserve::class, 'product_id');
	}

	public function changeStock($skuProduct, $quantity)
	{
		$lastCPosition = strrpos($skuProduct, 'C');

		$onlySku = substr($skuProduct, 0, $lastCPosition);
		$productIdFromSKU = substr($skuProduct, $lastCPosition + 1);    


		// Convierte el ID del producto a entero para la comparación.
		$productIdFromSKU = intval($productIdFromSKU);


		// Verifica si el ID del producto extraído del SKU coincide con el ID del producto actual.
		if ($this->product_id == $productIdFromSKU) {
			if ($this->stock < $quantity) {
				return 'insufficient_stock';
			}

			// Actualiza el stock general del producto
			$this->stock -= $quantity;

			// Aquí suponemos que 'features' contiene un array de variantes con su 'sku' y 'inventory_quantity'.
			$features = json_decode($this->features, true);
			if (isset($features['variants']) && is_array($features['variants'])) {
				foreach ($features['variants'] as $key => $variant) {
					// Verifica si el SKU de la variante coincide.
					if ($variant['sku'] == $onlySku) {
						if ($variant['inventory_quantity'] < $quantity) {
							// Revertir el cambio en stock general si no hay suficiente stock en la variante
							$this->stock += $quantity;
							$this->save();
							return 'insufficient_stock_variant';
						}
						// Resta la cantidad del stock de la variante.
						$features['variants'][$key]['inventory_quantity'] -= $quantity;
						break; // Salir del loop si ya encontramos y actualizamos la variante
					}
				}
			}

			// Guardar los cambios en el producto y sus variantes.
			$this->features = json_encode($features);
			$this->save();
			return true;
		}

		// Si llegamos aquí, significa que no se encontró el producto con ese ID.
		return false;
	}

	public function changeStockGen($id, $skuProduct, $quantity, $type)
	{
		//from editProduct with idproduct
		// Convierte el ID del producto a entero para la comparación.
		$productIdFromSKU = intval($id);

		// Verifica si el ID del producto extraído del SKU coincide con el ID del producto actual.
		if ($this->product_id == $productIdFromSKU) {
			if ($type == 0) {
				if ($this->stock < $quantity) {
					error_log("*insufficient_stock");
					return 'insufficient_stock';
				}
			}

			// Actualiza el stock general del producto
			if ($type == 1) {
				$this->stock += $quantity;
			} else {
				$this->stock -= $quantity;
			}

			$product = Product::find($id);
			$isvariable = $product->isvariable;
			$features = json_decode($this->features, true);
			if ($isvariable == 1) {
				if (isset($features['variants']) && is_array($features['variants'])) {
					// Aquí suponemos que 'features' contiene un array de variantes con su 'sku' y 'inventory_quantity'.
					foreach ($features['variants'] as $key => $variant) {
						// Verifica si el SKU de la variante coincide.
						if ($variant['sku'] == $skuProduct) {
							if ($type == 0) {
								if ($variant['inventory_quantity'] < $quantity) {
									// Revertir el cambio en stock general si no hay suficiente stock en la variante
									// $this->stock += $quantity;

									if ($type == 1) {
										$this->stock -= $quantity;
									} else {
										$this->stock += $quantity;
									}
									$this->save();
									error_log("*insufficient_stock_variant");

									return 'insufficient_stock_variant';
								}
							}
							// Resta la cantidad del stock de la variante.
							// $features['variants'][$key]['inventory_quantity'] -= $quantity;
							if ($type == 1) {
								$features['variants'][$key]['inventory_quantity'] += $quantity;
							} else {
								$features['variants'][$key]['inventory_quantity'] -= $quantity;
							}
							$features['variants'][$key]['inventory_quantity'] = strval($features['variants'][$key]['inventory_quantity']);

							break; // Salir del loop si ya encontramos y actualizamos la variante
						}
					}
				}
			}

			// Guardar los cambios en el producto y sus variantes.
			$this->features = json_encode($features);
			$this->save();
			return true;
		}

		// Si llegamos aquí, significa que no se encontró el producto con ese ID.
		return false;
	}


	public function isVariant($skuProduct)
	{
		$features = json_decode($this->features, true);
		return collect($features['variants'] ?? [])->contains('sku', $skuProduct);
	}

	public function getVariantPrice($skuProduct)
	{
		$features = json_decode($this->features, true);
		$variant = collect($features['variants'] ?? [])->firstWhere('sku', $skuProduct);
		return $variant['price'] ?? $this->price; // Retorna el precio de la variante o el del producto general si no se encuentra
	}

}
