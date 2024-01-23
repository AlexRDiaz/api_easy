<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class FilesFolderLink
 * 
 * @property int $id
 * @property int|null $file_id
 * @property int|null $folder_id
 * @property float|null $file_order
 * 
 * @property File|null $file
 * @property UploadFolder|null $upload_folder
 *
 * @package App\Models
 */
class FilesFolderLink extends Model
{
	protected $table = 'files_folder_links';
	public $timestamps = false;

	protected $casts = [
		'file_id' => 'int',
		'folder_id' => 'int',
		'file_order' => 'float'
	];

	protected $fillable = [
		'file_id',
		'folder_id',
		'file_order'
	];

	public function file()
	{
		return $this->belongsTo(File::class);
	}

	public function upload_folder()
	{
		return $this->belongsTo(UploadFolder::class, 'folder_id');
	}
}
