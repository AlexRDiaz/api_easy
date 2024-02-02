<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidosShopify
 * 
 * @property int $id
 * @property string|null $numero_orden
 * @property string|null $direccion_shipping
 * @property string|null $nombre_shipping
 * @property string|null $telefono_shipping
 * @property string|null $precio_total
 * @property string|null $observacion
 * @property string|null $ciudad_shipping
 * @property string|null $estado_interno
 * @property string|null $id_comercial
 * @property string|null $producto_p
 * @property string|null $producto_extra
 * @property string|null $cantidad_total
 * @property string|null $status
 * @property string|null $estado_logistico
 * @property string|null $ruta
 * @property string|null $name_comercial
 * @property string|null $marca_tiempo_envio
 * @property string|null $fecha_entrega
 * @property string|null $comentario
 * @property string|null $tipo_pago
 * @property string|null $archivo
 * @property string|null $estado_pagado
 * @property string|null $url_pagado_foto
 * @property string|null $estado_pago_logistica
 * @property string|null $url_p_l_foto
 * @property string|null $estado_devolucion
 * @property string|null $tienda_temporal
 * @property string|null $marca_t_d
 * @property string|null $marca_t_d_t
 * @property string|null $marca_t_d_l
 * @property string|null $marca_t_i
 * @property string|null $do
 * @property string|null $dt
 * @property string|null $dl
 * @property string|null $fecha_confirmacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property string|null $comentario_rechazado
 * @property bool|null $revisado
 * @property float|null $costo_envio
 * @property float|null $costo_devolucion
 * @property float|null $costo_transportadora
 * @property Carbon|null $printed_at
 * @property int|null $printed_by
 * @property Carbon|null $sent_at
 * @property int|null $sent_by
 * @property int|null $received_by
 * @property Carbon|null $status_last_modified_at
 * @property int|null $status_last_modified_by
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|Novedade[] $novedades
 * @property Collection|PedidosShopifiesOperadoreLink[] $pedidos_shopifies_operadore_links
 * @property Collection|PedidosShopifiesPedidoFechaLink[] $pedidos_shopifies_pedido_fecha_links
 * @property Collection|PedidosShopifiesRutaLink[] $pedidos_shopifies_ruta_links
 * @property Collection|PedidosShopifiesSubRutaLink[] $pedidos_shopifies_sub_ruta_links
 * @property Collection|PedidosShopifiesTransportadoraLink[] $pedidos_shopifies_transportadora_links
 * @property Collection|ProductoShopifiesPedidosShopifyLink[] $producto_shopifies_pedidos_shopify_links
 * @property Collection|TransaccionPedidoTransportadora[] $transaccion_pedido_transportadoras
 * @property Collection|UpUsersPedidosShopifiesLink[] $up_users_pedidos_shopifies_links
 *
 * @package App\Models
 */
class PedidosShopify extends Model
{
	protected $table = 'pedidos_shopifies';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int',
		'revisado' => 'bool',
		'costo_envio' => 'float',
		'costo_devolucion' => 'float',
		'costo_transportadora' => 'float',
		'printed_at' => 'datetime',
		'printed_by' => 'int',
		'sent_at' => 'datetime',
		'sent_by' => 'int',
		'revisado_seller' => 'int',
		'received_by' => 'int',
		'status_last_modified_at' => 'datetime',
		'status_last_modified_by' => 'int',
		'confirmed_by' => 'int',
		'confirmed_at' => 'datetime'
	];

	protected $fillable = [
		'numero_orden',
		'direccion_shipping',
		'nombre_shipping',
		'telefono_shipping',
		'precio_total',
		'observacion',
		'ciudad_shipping',
		'estado_interno',
		'id_comercial',
		'producto_p',
		'producto_extra',
		'variant_details',
		'cantidad_total',
		'status',
		'estado_logistico',
		'ruta',
		'name_comercial',
		'marca_tiempo_envio',
		'fecha_entrega',
		'comentario',
		'tipo_pago',
		'archivo',
		'estado_pagado',
		'url_pagado_foto',
		'estado_pago_logistica',
		'url_p_l_foto',
		'estado_devolucion',
		'tienda_temporal',
		'marca_t_d',
		'marca_t_d_t',
		'marca_t_d_l',
		'marca_t_i',
		'do',
		'dt',
		'dl',
		'fecha_confirmacion',
		'created_by_id',
		'updated_by_id',
		'comentario_rechazado',
		'revisado',
		'costo_envio',
		'costo_devolucion',
		'costo_transportadora',
		'printed_at',
		'printed_by',
		'sent_at',
		'sent_by',
		'revisado_seller',
		'received_by',
		'status_last_modified_at',
		'status_last_modified_by',
		'confirmed_by',
		'confirmed_at',
		'sku',
		'id_product',
		'gestioned_novelty'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function novedades()
	{
		return $this->belongsToMany(Novedade::class, 'novedades_pedidos_shopify_links', 'pedidos_shopify_id', 'novedad_id')
			->withPivot('id', 'novedad_order');
	}

	public function pedidos_shopifies_operadore_links()
	{
		return $this->hasMany(PedidosShopifiesOperadoreLink::class);
	}

	public function pedidos_shopifies_pedido_fecha_links()
	{
		return $this->hasMany(PedidosShopifiesPedidoFechaLink::class);
	}

	public function pedidos_shopifies_ruta_links()
	{
		return $this->hasMany(PedidosShopifiesRutaLink::class);
	}

	public function pedidos_shopifies_sub_ruta_links()
	{
		return $this->hasMany(PedidosShopifiesSubRutaLink::class);
	}

	public function pedidos_shopifies_transportadora_links()
	{
		return $this->hasMany(PedidosShopifiesTransportadoraLink::class);
	}

	public function producto_shopifies_pedidos_shopify_links()
	{
		return $this->hasMany(ProductoShopifiesPedidosShopifyLink::class);
	}

	public function up_users_pedidos_shopifies_links()
	{
		return $this->hasMany(UpUsersPedidosShopifiesLink::class);
	}

	public function operadore()
	{
		return $this->hasManyThrough(Operadore::class, PedidosShopifiesOperadoreLink::class, 'pedidos_shopify_id', 'id', 'id', 'operadore_id');
	}
	public function transportadora()
	{
		return $this->hasManyThrough(Transportadora::class, PedidosShopifiesTransportadoraLink::class, 'pedidos_shopify_id', 'id', 'id', 'transportadora_id');
	}
	public function pedidoFecha()
	{
		return $this->hasManyThrough(PedidoFecha::class, PedidosShopifiesPedidoFechaLink::class, 'pedidos_shopify_id', 'id', 'id', 'pedido_fecha_id');
	}
	public function users()
	{
		return $this->hasManyThrough(UpUser::class, UpUsersPedidosShopifiesLink::class, 'pedidos_shopify_id', 'id', 'id', 'user_id');
	}

	public function ruta()
	{
		return $this->hasManyThrough(Ruta::class, PedidosShopifiesRutaLink::class, 'pedidos_shopify_id', 'id', 'id', 'ruta_id');
	}

	public function subRuta()
	{
		return $this->hasManyThrough(SubRuta::class, PedidosShopifiesSubRutaLink::class, 'pedidos_shopify_id', 'id', 'id', 'sub_ruta_id');
	}

	public function upuser_pedidos_link()
	{
		return $this->belongsToMany(UpUser::class, UpUsersPedidosShopifiesLink::class, 'user_id')
			->withPivot('id');
	}

	public function pedido_fecha_link()
	{
		return $this->belongsToMany(PedidoFecha::class, PedidosShopifiesPedidoFechaLink::class, 'pedido_fecha_id')
			->withPivot('id');
	}
	// public function novedades()
	// {
	//     return $this->hasManyThrough(Novedade::class, NovedadesPedidosShopifyLink::class, 'pedidos_shopify_id', 'id', 'id', 'novedad_id');
	// }

	public function printedBy()
	{
		// return $this->belongsTo(UpUser::class, 'printed_by', 'id');
		return $this->belongsTo(UpUser::class, 'printed_by', 'id')->with('rolesFronts');
	}

	public function sentBy()
	{
		// return $this->belongsTo(UpUser::class, 'sent_by', 'id');
		return $this->belongsTo(UpUser::class, 'sent_by', 'id')->with('rolesFronts');

	}

	public function receivedBy()
	{
		return $this->belongsTo(UpUser::class, 'received_by', 'id');
	}
	public function confirmedBy()
	{
		return $this->belongsTo(UpUser::class, 'confirmed_by');
	}

	public function statusLastModifiedBy()
	{
		return $this->belongsTo(UpUser::class, 'status_last_modified_by', 'id');
	}
	
	public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class, 'id_product');
    }

	public function transactionTransportadora()
	{
		return $this->belongsTo(TransaccionPedidoTransportadora::class, 'id', 'id_pedido');
	}

}
