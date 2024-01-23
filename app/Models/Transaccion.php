<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    protected $table = 'transaccion';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tipo',
        'monto',
        'valor_anterior',
        'valor_actual',
        'marca_de_tiempo',
        'id_origen',
        'codigo',
        'origen',
        'id_vendedor',
        'comentario',
        'state',
        'generated_by'

        
    ];
    protected $casts = [
        
    ];

    public static array $rules = [
        
    ];



public function user()
	{
		return $this->hasOne(UpUser::class, 'id','id_vendedor');
	}

}
