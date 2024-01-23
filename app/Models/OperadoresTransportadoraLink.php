<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class OperadoresTransportadoraLink
 * 
 * @property int $id
 * @property int|null $operadore_id
 * @property int|null $transportadora_id
 * @property float|null $operadore_order
 * 
 * @property Operadore|null $operadore
 * @property Transportadora|null $transportadora
 *
 * @package App\Models
 */
class OperadoresTransportadoraLink extends Model
{
	protected $table = 'operadores_transportadora_links';
	public $timestamps = false;

	protected $casts = [
		'operadore_id' => 'int',
		'transportadora_id' => 'int',
		'operadore_order' => 'float'
	];

	protected $fillable = [
		'operadore_id',
		'transportadora_id',
		'operadore_order'
	];

	public function operadore()
	{
		return $this->belongsTo(Operadore::class);
	}

	public function transportadora()
	{
		return $this->belongsTo(Transportadora::class);
	}
}
