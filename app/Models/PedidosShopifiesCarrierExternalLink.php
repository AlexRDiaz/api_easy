<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidosShopifiesCarrierExternalLink extends Model
{
    public $table = 'pedidos_shopifies_carrier_external_links';

    public $fillable = [
        'pedidos_shopify_id',
        'carrier_id',
        'external_id',
        'city_external_id',
        'cost_refound_external'
    ];

    protected $casts = [
        'external_id' => 'string',
        'cost_refound_external' => 'decimal:2'
    ];

    public static array $rules = [
        'pedidos_shopify_id' => 'nullable',
        'carrier_id' => 'nullable',
        'external_id' => 'nullable|string',
        'city_external_id' => 'nullable',
        'cost_refound_external' => 'nullable|numeric',
        'created_at' => 'nullable',
        'updated_at' => 'nullable'
    ];

    public function carrier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // return $this->belongsTo(\App\Models\CarriersExternal::class, 'carrier_id');
        return $this->belongsTo(\App\Models\CarriersExternal::class, 'carrier_id')->select('id', 'name');
    }

    public function cityExternal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\CoverageExternal::class, 'city_external_id');
    }

    public function pedidosShopify(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\PedidosShopify::class, 'pedidos_shopify_id');
    }
}
