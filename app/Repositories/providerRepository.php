<?php

namespace App\Repositories;

use App\Models\Provider;
use App\Repositories\BaseRepository;

class providerRepository 
{
    protected $fieldSearchable = [

    ];

    public function create(Provider $provider)
    {
        return $provider->save();
    }

    public function update($nuevoSaldo, $id)
    {
        $providerencontrado = Provider::findOrFail($id);
        // Actualiza el saldo del vendedor
        $providerencontrado->saldo = $nuevoSaldo;
        $providerencontrado->save();

        return $providerencontrado;
    }
    
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Provider::class;
    }
}