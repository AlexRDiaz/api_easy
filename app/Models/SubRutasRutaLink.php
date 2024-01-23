<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SubRutasRutaLink
 * 
 * @property int $id
 * @property int|null $sub_ruta_id
 * @property int|null $ruta_id
 * @property float|null $sub_ruta_order
 * 
 * @property SubRuta|null $sub_ruta
 * @property Ruta|null $ruta
 *
 * @package App\Models
 */
class SubRutasRutaLink extends Model
{
	protected $table = 'sub_rutas_ruta_links';
	public $timestamps = false;

	protected $casts = [
		'sub_ruta_id' => 'int',
		'ruta_id' => 'int',
		'sub_ruta_order' => 'float'
	];

	protected $fillable = [
		'sub_ruta_id',
		'ruta_id',
		'sub_ruta_order'
	];

	public function sub_ruta()
	{
		return $this->belongsTo(SubRuta::class);
	}

	public function ruta()
	{
		return $this->belongsTo(Ruta::class);
	}
}
