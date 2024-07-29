<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaccionGlobal extends Model
{
    protected $table = 'transactions_global';
    public $timestamps = false;

    protected $fillable = [
        'admission_date',
        'delivery_date',
        'status',
        'return_state',
        'id_order',
        'code',
        'origin',
        'withdrawal_price',
        'value_order',
        'return_cost',
        'delivery_cost',
        'notdelivery_cost',
        'provider_cost',
        'referer_cost',
        'total_transaction',
        'previous_value',
        'current_value',
        'state',
        'id_seller',
        'internal_transportation_cost',
        'external_transportation_cost',
        'external_return_cost',
        'order_entry',


    ];
    protected $casts = [];

    public static array $rules = [];



    public function user()
    {
        return $this->hasOne(Vendedore::class, 'id_master','id_seller');
    }
    public function order()
    {
        return $this->hasOne(PedidosShopify::class, 'id','id_order');
    }
}
