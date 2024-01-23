<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpUsersProvidersLink extends Model
{
    public $table = 'up_users_providers_links';

    public $fillable = [
        'provider_id',
        'up_user_id'
    ];

    protected $casts = [
        
    ];

    public static array $rules = [
        'provider_id' => 'nullable',
        'up_user_id' => 'nullable'
    ];

    public function provider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Provider::class, 'provider_id');
    }

    public function upUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\UpUser::class, 'up_user_id');
    }
}
