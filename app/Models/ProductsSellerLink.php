<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductsSellerLink extends Model
{
    public $table = 'products_seller_links';

    public $fillable = [
        'product_id',
        'id_master',
        'favorite',
        'onsale'
    ];

    protected $casts = [
        'favorite' => 'int',
        'onsale' => 'int'
    ];

    public static array $rules = [
        'product_id' => 'nullable',
        'id_master' => 'nullable',
        'favorite' => 'nullable|int',
        'onsale' => 'nullable|int',
        'created_at' => 'nullable',
        'updated_at' => 'nullable'
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function idMaster(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\UpUser::class, 'id_master');
    }
}
