<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class I18nLocale
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 *
 * @package App\Models
 */
class I18nLocale extends Model
{
	protected $table = 'i18n_locale';

	protected $casts = [
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'name',
		'code',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}
}
