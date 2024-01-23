<?php

namespace App\Repositories;

use App\Models\Vendedore;
use App\Repositories\BaseRepository;

class vendedorRepository
{
    protected $fieldSearchable = [

    ];

    public function create(Vendedore $vendedor)
    {
        return $vendedor->save();
    }

    public function update($nuevoSaldo, $id)
    {
        $vendedorencontrado = Vendedore::findOrFail($id);
        // Actualiza el saldo del vendedor
        $vendedorencontrado->saldo = $nuevoSaldo;
        $vendedorencontrado->save();

        return $vendedorencontrado;
    }
    
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Vendedore::class;
    }
}