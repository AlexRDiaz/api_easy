<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportStats extends Model
{
    protected $table = 'transport_stats';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'transport_id',
        'transport_name',
        'transport_stats_day',
        'transport_stats_month',
        'route_name',
        'efficiency_month_date',
        'efficiency_day_date',
        'monthly_counter',
        'daily_counter'
    ];
    // protected $casts = [
    //     'efficiency_month_date' => 'date',
    //     'efficiency_day_date' => 'date'
    // ];

    public static array $rules = [

    ];
}