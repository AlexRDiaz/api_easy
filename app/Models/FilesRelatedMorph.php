<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class FilesRelatedMorph
 * 
 * @property int $id
 * @property int|null $file_id
 * @property int|null $related_id
 * @property string|null $related_type
 * @property string|null $field
 * @property float|null $order
 * 
 * @property File|null $file
 *
 * @package App\Models
 */
class FilesRelatedMorph extends Model
{
	protected $table = 'files_related_morphs';
	public $timestamps = false;

	protected $casts = [
		'file_id' => 'int',
		'related_id' => 'int',
		'order' => 'float'
	];

	protected $fillable = [
		'file_id',
		'related_id',
		'related_type',
		'field',
		'order'
	];

	public function file()
	{
		return $this->belongsTo(File::class);
	}
}
