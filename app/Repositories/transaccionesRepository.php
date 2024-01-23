<?php

namespace App\Repositories;

use App\Models\Transaccion;
use App\Repositories\BaseRepository;

class transaccionesRepository
{
    protected $fieldSearchable = [
        
    ];

    public function create(Transaccion $transaccion)
    {
        return $transaccion->save();
    }
    
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return transaccion::class;
    }
}
