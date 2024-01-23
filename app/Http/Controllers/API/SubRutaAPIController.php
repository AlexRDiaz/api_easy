<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubRuta;
use App\Models\Transportadora;
use App\Models\Ruta;
use App\Models\Operadore;
use Illuminate\Http\Request;

class SubRutaAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $subrutas = SubRuta::all();
        return response()->json($subrutas);
    }

    public function getSubrutas()
    {
        // Obtener las subrutas con su nombre e ID, ignorando casos vacíos
        $subrutas = SubRuta::whereNotNull('titulo')
            ->where('titulo', '<>', '') // Asegura que el título no esté vacío
            ->selectRaw('CONCAT(titulo, "-", id) as nombre_id')
            ->pluck('nombre_id')
            ->toArray();
    
        return response()->json($subrutas);
    }
    
    public function getSubroutesByTransportadoraId($transportadoraId)
    {
        try {
            $subrutas = SubRuta::where('id_operadora', $transportadoraId)->get();

            // Filtra las subrutas para excluir aquellas con títulos vacíos y luego las transforma.
            $subrutasTransformed = $subrutas->filter(function ($subruta) {
                return !empty($subruta->titulo);
            })->map(function ($subruta) {
                return $subruta->titulo . '-' . $subruta->id;
            })->values();

            // Si necesitas verificar si hay resultados o no.
            if ($subrutasTransformed->isEmpty()) {
                return response()->json(['message' => 'No subrutas found for the provided transportadora id'], 404);
            }

            return response()->json($subrutasTransformed);

        } catch (\Exception $e) {
            // Log::error("Error fetching subrutas: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getOperatorsbySubrouteAndTransportadora(Request $request, $subRutaId)
    {
        // Extraer los datos del JSON
        $data = $request->json()->all();
        $transportadoraId = $data['transportadora_id'];

        // Busca la transportadora para asegurarse de que exista
        $transportadora = Transportadora::find($transportadoraId);
        if (!$transportadora) {
            return response()->json(['mensaje' => 'Transportadora no encontrada'], 404);
        }

        // Verifica si se deben obtener todos los operadores de la transportadora
        if ($subRutaId == 9999) {
            $operadores = Operadore::whereHas('transportadoras', function ($query) use ($transportadoraId) {
                $query->where('transportadoras.id', $transportadoraId);
            })
                ->with('up_users')
                ->get();
        } else {
            $subRuta = SubRuta::find($subRutaId);
            if (!$subRuta) {
                return response()->json(['mensaje' => 'SubRuta no encontrada'], 404);
            }

            // Obtiene los operadores que están en la subruta y asociados con la transportadora especificada
            $operadores = $subRuta->operadores()
                ->whereHas('transportadoras', function ($query) use ($transportadoraId) {
                    $query->where('transportadoras.id', $transportadoraId);
                })
                ->with('up_users')
                ->get();
        }

        // Filtra y mapea los operadores
        $operadoresFiltrados = $operadores->filter(function ($operador) {
            return !empty($operador->up_users->first()) && !empty($operador->up_users->first()->username);
        })
            ->map(function ($operador) {
                $username = $operador->up_users->first()->username;
                return $username . '-' . $operador->id;
            })
            ->values();

        return response()->json($operadoresFiltrados);
    }



    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
