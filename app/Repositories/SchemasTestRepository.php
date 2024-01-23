<?php

namespace App\Repositories;

use App\Models\SchemasTest;
use App\Repositories\BaseRepository;

class SchemasTestRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'delivery_price'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return SchemasTest::class;
    }
}
