<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    public $table = 'warehouses';
    protected $primaryKey = 'warehouse_id';

    public $fillable = [
        'branch_name',
        'address',
        'customer_service_phone',
        'reference',
        'description',
        'url_image',
        'city',
        'collection',
        'active',
        'approved',
        'provider_id'
    ];

    protected $casts = [
        'branch_name' => 'string',
        'address' => 'string',
        'customer_service_phone' => 'string',
        'reference' => 'string',
        'description' => 'string',
        'url_image' => 'string',
        'city' => 'string',
        'collection' => 'json', // Campo 'collection' como tipo JSON
        'active' => 'int', // Cambiado de 'int' a 'boolean'
    ];

    public static array $rules = [
        'branch_name' => 'nullable|string|max:70',
        'address' => 'nullable|string|max:70',
        'customer_service_phone' => 'nullable|string|max:70',
        'reference' => 'nullable|string|max:70',
        'description' => 'nullable|string|max:65535',
        'url_image' => 'nullable|string|max:150',
        'city' => 'nullable|string|max:80',
        'collection' => 'nullable|json',
        'active' => 'nullable|int', // Cambiado de 'int' a 'boolean'
        'approved',
        'provider_id' => 'nullable',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
    ];

    public function provider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Provider::class, 'provider_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Product::class, 'warehouse_id');
    }
}
