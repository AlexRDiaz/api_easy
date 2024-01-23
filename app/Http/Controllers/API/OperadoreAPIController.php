<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Operadore;
use App\Models\SubRuta;
use App\Models\Transportadora;
use Illuminate\Http\Request;

class OperadoreAPIController extends Controller
{
    // public function getOperators()
    // {
    //     $operators = Operadore::with('up_users:id,username')->get();

    //     $result = [];

    //     foreach ($operators as $operator) {
    //         $result[] = $operator->up_users->isEmpty()
    //             ? '' // Puedes decidir qué valor asignar si el username está vacío
    //             : $operator->up_users[0]->username . '-' . $operator->id;
    //     }

    //     return response()->json(['data' => $result, 'count' => count($result)]);
    // }

    public function getOperators()
    {
        $operators = Operadore::with('up_users')
            ->whereHas('transportadoras', function ($query) {
                $query->where('active', 1);
            })
            ->get();
    
        $result = [];
    
        foreach ($operators as $operator) {
            // Verificar si up_users no está vacío y tiene username
            if ($operator->up_users->isNotEmpty() && !empty($operator->up_users[0]->username)) {
                // Imprime información útil para depurar
    
                $result[] = $operator->up_users[0]->username . '-' . $operator->up_users[0]->id; 
            }
        }
    
        return response()->json(['data' => $result, 'count' => count($result)]);
    }
    



}
?>