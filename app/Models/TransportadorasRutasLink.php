<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TransportadorasRutasLink
 * 
 * @property int $id
 * @property int|null $transportadora_id
 * @property int|null $ruta_id
 * @property float|null $ruta_order
 * @property float|null $transportadora_order
 * 
 * @property Transportadora|null $transportadora
 * @property Ruta|null $ruta
 *
 * @package App\Models
 */
class TransportadorasRutasLink extends Model
{
	protected $table = 'transportadoras_rutas_links';
	public $timestamps = false;

	protected $casts = [
		'transportadora_id' => 'int',
		'ruta_id' => 'int',
		'ruta_order' => 'float',
		'transportadora_order' => 'float'
	];

	protected $fillable = [
		'transportadora_id',
		'ruta_id',
		'ruta_order',
		'transportadora_order'
	];

	public function transportadora()
	{
		return $this->belongsTo(Transportadora::class);
	}

	public function ruta()
	{
		return $this->belongsTo(Ruta::class);
	}
}
