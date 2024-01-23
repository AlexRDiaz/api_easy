<?php

namespace App\Repositories;

use App\Models\pedidos_shopifies;
use App\Repositories\BaseRepository;

class pedidos_shopifiesRepository extends BaseRepository
{
    protected $fieldSearchable = [
        
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return pedidos_shopifies::class;
    }
}
