<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchemasTest extends Model
{
    public $table = 'schemas_tests';

    public $fillable = [
        'name',
        'delivery_price'
    ];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'delivery_price' => 'float'
    ];

    public static array $rules = [
        'name' => 'required',
        'delivery_price' => 'required'
    ];

    
}
