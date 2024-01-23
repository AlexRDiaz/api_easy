<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class IngresosEgreso
 * 
 * @property int $id
 * @property string|null $fecha
 * @property string|null $tipo
 * @property string|null $persona
 * @property string|null $motivo
 * @property string|null $comprobante
 * @property string|null $monto
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 *
 * @package App\Models
 */
class IngresosEgreso extends Model
{
	protected $table = 'ingresos_egresos';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'fecha',
		'tipo',
		'persona',
		'motivo',
		'comprobante',
		'monto',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}
}
