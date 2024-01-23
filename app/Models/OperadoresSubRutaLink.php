<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class OperadoresSubRutaLink
 * 
 * @property int $id
 * @property int|null $operadore_id
 * @property int|null $sub_ruta_id
 * @property float|null $operadore_order
 * 
 * @property Operadore|null $operadore
 * @property SubRuta|null $sub_ruta
 *
 * @package App\Models
 */
class OperadoresSubRutaLink extends Model
{
	protected $table = 'operadores_sub_ruta_links';
	public $timestamps = false;

	protected $casts = [
		'operadore_id' => 'int',
		'sub_ruta_id' => 'int',
		'operadore_order' => 'float'
	];

	protected $fillable = [
		'operadore_id',
		'sub_ruta_id',
		'operadore_order'
	];

	public function operadore()
	{
		return $this->belongsTo(Operadore::class);
	}

	public function sub_ruta()
	{
		return $this->belongsTo(SubRuta::class);
	}
}
