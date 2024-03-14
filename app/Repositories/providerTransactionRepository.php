<?php

namespace App\Repositories;

use App\Models\ProviderTransaction;
use App\Repositories\BaseRepository;

class providerTransactionRepository
{
    protected $fieldSearchable = [
        
    ];

    public function create(ProviderTransaction $transaccion)
    {
        return $transaccion->save();
    }
    
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return ProviderTransaction::class;
    }
}
