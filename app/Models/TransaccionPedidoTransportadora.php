<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TransaccionPedidoTransportadora
 * 
 * @property int $id
 * @property string $status
 * @property string $fecha_entrega
 * @property float $precio_total
 * @property float $costo_transportadora
 * @property int $id_pedido
 * @property int $id_transportadora
 * @property int $id_operador
 * 
 * @property PedidosShopify $pedidos_shopify
 * @property Transportadora $transportadora
 * @property Operadore $operadore
 *
 * @package App\Models
 */
class TransaccionPedidoTransportadora extends Model
{
	protected $table = 'transaccion_pedido_transportadora';
	public $timestamps = false;

	protected $casts = [
		'precio_total' => 'float',
		'costo_transportadora' => 'float',
		'id_pedido' => 'int',
		'id_transportadora' => 'int',
		'id_operador' => 'int'
	];

	protected $fillable = [
		'status',
		'fecha_entrega',
		'precio_total',
		'costo_transportadora',
		'id_pedido',
		'id_transportadora',
		'id_operador'
	];

	public function pedidos_shopify()
	{
		return $this->belongsTo(PedidosShopify::class, 'id_pedido');
	}

	public function transportadora()
	{
		return $this->belongsTo(Transportadora::class, 'id_transportadora');
	}

	public function operadore()
	{
		return $this->belongsTo(Operadore::class, 'id_operador');
	}
}
