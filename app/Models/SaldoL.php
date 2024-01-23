<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SaldoL
 * 
 * @property int $id
 * @property string|null $fecha
 * @property string|null $depositos
 * @property string|null $retiros
 * @property string|null $saldo
 * @property string|null $ingresos
 * @property string|null $egresos
 * @property string|null $utilidad_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 *
 * @package App\Models
 */
class SaldoL extends Model
{
	protected $table = 'saldo_ls';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'fecha',
		'depositos',
		'retiros',
		'saldo',
		'ingresos',
		'egresos',
		'utilidad_total',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}
}
