<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class File
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $alternative_text
 * @property string|null $caption
 * @property int|null $width
 * @property int|null $height
 * @property string|null $formats
 * @property string|null $hash
 * @property string|null $ext
 * @property string|null $mime
 * @property float|null $size
 * @property string|null $url
 * @property string|null $preview_url
 * @property string|null $provider
 * @property string|null $provider_metadata
 * @property string|null $folder_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|FilesFolderLink[] $files_folder_links
 * @property Collection|FilesRelatedMorph[] $files_related_morphs
 *
 * @package App\Models
 */
class File extends Model
{
	protected $table = 'files';

	protected $casts = [
		'width' => 'int',
		'height' => 'int',
		'size' => 'float',
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $fillable = [
		'name',
		'alternative_text',
		'caption',
		'width',
		'height',
		'formats',
		'hash',
		'ext',
		'mime',
		'size',
		'url',
		'preview_url',
		'provider',
		'provider_metadata',
		'folder_path',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function files_folder_links()
	{
		return $this->hasMany(FilesFolderLink::class);
	}

	public function files_related_morphs()
	{
		return $this->hasMany(FilesRelatedMorph::class);
	}
}
