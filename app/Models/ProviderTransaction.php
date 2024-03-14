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
        'generated_by',
        'status',
        'description'

        
    ];
    protected $casts = [
        
    ];

    public static array $rules = [
        
    ];

    public function pedido()
    {
        if (strpos($this->origin_code, 'Retiro-') === false) {
            return $this->belongsTo(PedidosShopify::class, 'origin_id', 'id');
        } else {
            return null;
        }
    }
    public function orden_retiro()
    {
        if (strpos($this->origin_code, 'Retiro-') !== false) {
            // Si el código de origen contiene 'Retiro-', devolver null
            // return null;
            return $this->belongsTo(OrdenesRetiro::class, 'origin_id', 'id');

        } else {
            // Si el código de origen no contiene 'Retiro-', devolver la relación con el pedido
            return $this->belongsTo(OrdenesRetiro::class, 'origin_id', 'id');
        }
    }

}