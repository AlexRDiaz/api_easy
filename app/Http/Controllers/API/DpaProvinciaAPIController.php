<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\dpaProvincia;
use Illuminate\Http\Request;

class DpaProvinciaAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $provincias = dpaProvincia::all();
        $formattedProvincias = $provincias->map(function ($provincias) {
            return  $provincias->provincia . '-' . $provincias->id;
        })->toArray();
        return response()->json($formattedProvincias);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $cantones = dpaProvincia::with('dpaCantones.dpaParroquias')
            ->where('id', $id)
            ->get();

        return response()->json($cantones);
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

    public function getCantones(string $id)
    {
        $provincia = dpaProvincia::with('dpaCantones')
            ->where('id', $id)
            ->first();

        if (!$provincia) {
            return response()->json(['message' => 'Provincia no encontrada'], 404);
        }

        $formattedData = $provincia->dpaCantones->flatMap(function ($canton) {
            return $canton->dpaParroquias->map(function ($parroquia) use ($canton) {
                // return $canton->id . '-' . $parroquia->id . '-' . $canton->canton.'/'.$parroquia->parroquia;
                return $canton->id . '-' . $parroquia->id . '-' . $parroquia->parroquia;
            });
        })->toArray();

        $total = count($formattedData);
        error_log("$total parroquias");
        $nombres = array_map(function ($element) {
            // Dividir el elemento usando '-' y obtener el último componente
            $components = explode('-', $element);
            return end($components);
        }, $formattedData);

        $duplicados = array_unique(array_diff_assoc($nombres, array_unique($nombres)));

        if (empty($duplicados)) {
            error_log("No hay nombres de parroquias duplicados.");
        } else {
            error_log("Nombres de parroquias duplicados: " . implode(", ", $duplicados));
        }

        return response()->json($formattedData);
    }
}
