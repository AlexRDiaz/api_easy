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
    
    public function getOperatorbyId($idOperator)
    {
        // Comprobar si el idOperator comienza con 'T'
        $result="";
        if (strpos($idOperator, 'T') === 0) {
            // Extraer el número después del '-'
            $number = explode('-', $idOperator)[1];

            // Buscar en la tabla Transportadora
            $transportadora = Transportadora::where('active', 1)
                ->where('id', $number)
                ->first();

            if ($transportadora) {
                // Concatenar el título de Transportadora con el número
                $result = $transportadora->nombre;
            } else {
                // Manejar el caso en que no se encuentre el registro
                $result = 'Transportadora no encontrada';
            }
        } else {
             // Extraer el número después del '-'
             $number = explode('-', $idOperator)[1];
            // El proceso original para 'O'
            $operators = Operadore::with('up_users')
                ->where('id', $number)
                ->whereHas('transportadoras', function ($query) {
                    $query->where('active', 1);
                })
                ->get();

            foreach ($operators as $operator) {
                if ($operator->up_users->isNotEmpty() && !empty($operator->up_users[0]->username)) {
                    $result = $operator->up_users[0]->username;
                }
            }
        }

        return response()->json($result);
    }



}
?>