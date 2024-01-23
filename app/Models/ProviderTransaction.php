<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderTransaction extends Model
{
    protected $table = 'provider_transactions';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'transaction_type',
        'amount',
        'previous_value',
        'current_value',
        'timestamp',
        'origin_id',
        'origin_code',
        'provider_id',
        'comment',
        'generated_by'

        
    ];
    protected $casts = [
        
    ];

    public static array $rules = [
        
    ];

	public function pedido()
	{
		return $this->belongsTo(PedidosShopify::class, 'origin_id', 'id');
	}

}