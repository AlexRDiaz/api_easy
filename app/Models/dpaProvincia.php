<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DpaProvincia
 * 
 * @property int $id
 * @property string|null $provincia
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|CoverageExternal[] $coverage_externals
 * @property Collection|DpaCantone[] $dpa_cantones
 *
 * @package App\Models
 */
class DpaProvincia extends Model
{
    protected $table = 'dpa_provincias';

    public $fillable = [
        'provincia'
    ];

    protected $casts = [
        'provincia' => 'string'
    ];

    public static array $rules = [
        'provincia' => 'nullable|string',
        'created_at' => 'nullable',
        'updated_at' => 'nullable'
    ];

    public function coverage_externals()
    {
        // return $this->hasMany(CoverageExternal::class, 'id_provincia');
        return $this->hasMany(CoverageExternal::class, 'id_provincia')->select('id', 'ciudad', 'id_provincia');
    }
}
