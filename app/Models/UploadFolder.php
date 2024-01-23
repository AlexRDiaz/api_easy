<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UploadFolder
 * 
 * @property int $id
 * @property string|null $name
 * @property int|null $path_id
 * @property string|null $path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|FilesFolderLink[] $files_folder_links
 * @property Collection|UploadFoldersParentLink[] $upload_folders_parent_links
 *
 * @package App\Models
 */
class UploadFolder extends Model
{
	protected $table = 'upload_folders';

	protected $casts = [
		'path_id' => 'int',
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'name',
		'path_id',
		'path',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function files_folder_links()
	{
		return $this->hasMany(FilesFolderLink::class, 'folder_id');
	}

	public function upload_folders_parent_links()
	{
		return $this->hasMany(UploadFoldersParentLink::class, 'inv_folder_id');
	}
}
