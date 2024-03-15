<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class dpaProvincia extends Model
{
    public $table = 'dpa_provincias';

    public $fillable = [
        'provincia'
    ];

    protected $casts = [
        'provincia' => 'string'
    ];

    public static array $rules = [
        'provincia' => 'nullable|string',
        'created_at' => 'nullable',
        'updated_at' => 'nullable'
    ];

}
