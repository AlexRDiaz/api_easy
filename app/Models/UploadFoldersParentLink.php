<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UploadFoldersParentLink
 * 
 * @property int $id
 * @property int|null $folder_id
 * @property int|null $inv_folder_id
 * @property float|null $folder_order
 * 
 * @property UploadFolder|null $upload_folder
 *
 * @package App\Models
 */
class UploadFoldersParentLink extends Model
{
	protected $table = 'upload_folders_parent_links';
	public $timestamps = false;

	protected $casts = [
		'folder_id' => 'int',
		'inv_folder_id' => 'int',
		'folder_order' => 'float'
	];

	protected $fillable = [
		'folder_id',
		'inv_folder_id',
		'folder_order'
	];

	public function upload_folder()
	{
		return $this->belongsTo(UploadFolder::class, 'inv_folder_id');
	}
}
