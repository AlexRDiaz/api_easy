<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserve extends Model
{
    public $table = 'reserves';

    public $fillable = [
        'product_id',
        'sku',
        'stock',
        'id_comercial',
        'warehouse_price'
    ];

    protected $casts = [
        'sku' => 'string'
    ];

    public static array $rules = [
        'product_id' => 'required',
        'sku' => 'required|string|max:255',
        'stock' => 'required',
        'warehouse_price' => 'required'

    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function seller(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // return $this->belongsTo(\App\Models\UpUser::class, 'id_comercial');
        // return $this->belongsTo(UpUser::class, 'id_comercial', 'id')
        //     ->select('id', 'username', 'email');
        return $this->belongsTo(UpUser::class, 'id_comercial', 'id')
			->select('id', 'username', 'email')
			->with(['vendedores' => function ($query) {
				$query->select('vendedores.id', 'nombre_comercial', 'id_master');
			}]);
    }
}
