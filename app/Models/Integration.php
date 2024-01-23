<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    public $table = 'integrations';


    public $fillable = [
        'name',
        'description',
        'store_url',
        'user_id',
        'token',
        'created_at',
        'updated_at'
    ];

    protected $casts = [

        'name' => 'string',
        'description' => 'string',
        'store_url' => 'string',
        'user_id' => 'string',
        'token' => 'string'
    ];

    public static array $rules = [
        'store_url' => 'nullable|string|max:100',
        'user_id' => 'nullable|string|max:20',
        'token' => 'nullable|string'
    ];

    
}
