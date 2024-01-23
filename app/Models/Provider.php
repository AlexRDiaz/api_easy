<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    public $table = 'providers';

    public $fillable = [
        'user_id',
        'name',
        'phone',
        'description',
        'saldo',
        'provider_order',
        'up_user_order',        
        'approved',
        'active',
    ];

    protected $casts = [
        'name' => 'string',
        'phone' => 'string',
        'description' => 'string',
        'saldo' => 'string',
        'approved' => 'int',
        'active' => 'int',
    ];

    public static array $rules = [
        'user_id' => 'nullable',
        'name' => 'nullable|string|max:70',
        'phone' => 'nullable|string|max:15',
        'description' => 'nullable|string|max:65535',
        'saldo' => 'nullable|string|20',
        'created_at' => 'nullable',
        'updated_at' => 'nullable'
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\UpUser::class, 'user_id');
    }

    public function warehouses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Warehouse::class, 'provider_id');
    }
}
