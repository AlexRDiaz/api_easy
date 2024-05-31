<?php

namespace App\Repositories;

use App\Models\TransactionsGlobal;
use App\Repositories\BaseRepository;

class transactionsGlobalRepository
{
    protected $fieldSearchable = [
        
    ];

    public function create(TransactionsGlobal $transactionsGlobal)
    {
        return $transactionsGlobal->save();
    }
    
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return TransactionsGlobal::class;
    }
}
