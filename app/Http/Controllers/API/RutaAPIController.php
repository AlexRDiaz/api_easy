<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ruta;
use App\Models\SubRuta;
use App\Models\Transportadora;
use Illuminate\Http\Request;

class RutaAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $rutas = Ruta::all();
        //$rutas = Ruta::with('transportadoras', 'sub_rutas')->get();
        return response()->json($rutas);
    }

    public function activeRoutes()
    {
        $rutas = Ruta::where('active', 1)->get();

        $rutaStrings = [];

        foreach ($rutas as $ruta) {
            // Concatena el título y el ID de la ruta
            $rutaString = $ruta->titulo . '-' . $ruta->id;
            $rutaStrings[] = $rutaString;
        }

        return $rutaStrings;
    }

    public function show(string $id)
    {
        //
        $ruta = Ruta::with('transportadoras', 'sub_rutas')->findOrFail($id);
        return response()->json($ruta);
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

    
   // public function getSubRutasByRuta(Request $request, $rutaId)
    // {
    //     // Extraer el ID de la transportadora del JSON
    //     $data = $request->json()->all();
    //     $transportadoraId = $data['transportadora_id'];

    //     // Busca la ruta específica
    //     $ruta = Ruta::find($rutaId);
    //     if (!$ruta) {
    //         return response()->json(['mensaje' => 'Ruta no encontrada'], 404);
    //     }

    //     // Busca la transportadora para asegurarse de que exista
    //     $transportadora = Transportadora::find($transportadoraId);
    //     if (!$transportadora) {
    //         return response()->json(['mensaje' => 'Transportadora no encontrada'], 404);
    //     }

    //     // Obtiene las subrutas que están asociadas con la ruta y la transportadora especificada
    //     $subRutas = $ruta->sub_rutas()
    //         ->whereHas('operadores.transportadoras', function ($query) use ($transportadoraId) {
    //             $query->where('transportadoras.id', $transportadoraId);
    //         })
    //         ->get()
    //         ->map(function ($subRuta) {
    //             // Construye el string con el formato "titulo-id"
    //             return $subRuta->titulo . '-' . $subRuta->id;
    //         });

    //     return response()->json($subRutas);
    // }

    public function getTransportadorasConRutasYSubRutas(Request $request,$rutaId)
    {
        
        $data = $request->json()->all();
        $transportadoraId = $data['transportadora_id'];

        $transportadoras = Transportadora::with(['rutas' => function ($query) use ($rutaId,$transportadoraId) {
            $query->where('rutas.id', $rutaId)->with(['sub_rutas' => function ($query) use ($transportadoraId) {
                $query->where('sub_rutas.id_operadora', $transportadoraId);
            }]);
        }])->where('transportadoras.id', $transportadoraId)->get();
    
        $subRutasNombres = [];
        foreach ($transportadoras as $transportadora) {
            foreach ($transportadora->rutas as $ruta) {
                foreach ($ruta->sub_rutas as $subRuta) {
                    if (!empty($subRuta->titulo)) {
                        $subRutasNombres[] = $subRuta->titulo . '-' . $subRuta->id;
                    }
                }
            }
        }
    
        return response()->json($subRutasNombres);
    }
    
}
