<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
// use App\Models\pedidos_shopifies;
use App\Models\Operadore;
use App\Models\PedidosShopify;
use App\Models\Ruta;
use App\Models\Transportadora;
use App\Models\TransportadorasShippingCost;
// use App\Models\Ruta;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use LengthException;

class TransportadorasAPIController extends Controller
{

    public function index()
    {
        $transportadoras = Transportadora::all();
        return response()->json($transportadoras);
    }
    public function show($id)
    {
        $trasnportadora = Transportadora::with(['admin_user', 'operadores', 'rutas'])
            ->findOrFail($id);

        return response()->json($trasnportadora);
    }

    public function getTransportadoras(Request $request)
    {
        // No necesitas $data en este caso, ya que no lo estás utilizando

        $transportadoras = Transportadora::select(DB::raw('CONCAT(nombre, "-", id ) as id_nombre'))->distinct()->get()->pluck('id_nombre');

        return response()->json(['transportadoras' => $transportadoras]);
    }
    public function getActiveTransportadoras(Request $request)
    {
        $transportadoras = Transportadora::where('active', 1)->get();
    
        $formattedTransportadoras = $transportadoras->map(function ($transportadora) {
            return $transportadora->nombre . ' - ' . $transportadora->id;
        })->toArray();
    
        return response()->json( $formattedTransportadoras);
    }

    public function getRutasOfTransport($transportadoraId)
    {
        // Obtener la transportadora con sus rutas relacionadas
        $transportadora = Transportadora::with(['rutas'])->find($transportadoraId);

        // Verificar si la transportadora existe
        if (!$transportadora) {
            return response()->json(['error' => 'Transportadora no encontrada'], 404);
        }

        // Obtener las rutas de la transportadora y formatear los resultados
        $rutas = $transportadora->rutas->map(function ($ruta) {
            return $ruta->titulo . ' - ' . $ruta->id;
        });

        // Hacer algo con las rutas formateadas (por ejemplo, devolverlas en una respuesta JSON)
        return response()->json(['rutas' => $rutas]);
    }

    public function getTransportadorasOfRuta($rutaId)
    {
        // Obtener la ruta con sus transportadoras relacionadas
        $ruta = Ruta::with(['transportadoras'])->find($rutaId);

        // Verificar si la ruta existe
        if (!$ruta) {
            return response()->json(['error' => 'Ruta no encontrada'], 404);
        }

        // Obtener las transportadoras de la ruta y formatear los resultados
        $transportadoras = $ruta->transportadoras->map(function ($transportadora) {
            return $transportadora->nombre . ' - ' . $transportadora->id;
        });

        // Hacer algo con las transportadoras formateadas (por ejemplo, devolverlas en una respuesta JSON)
        return response()->json(['transportadoras' => $transportadoras]);
    }

    public function getOperatoresbyTransport(Request $request, $id)
    {
        $result = Transportadora::with(['operadores.up_users'])
            ->where('id', $id)
            ->get();
        if ($result->isEmpty()) {
            return response()->json(['message' => 'No se encontraron transportadoras con el ID especificado'], 404);
        }

        // Obtener todos los IDs y usernames de up_users
        $usersData = [];
        $datoparcil = "";
        foreach ($result as $transportadora) {
            foreach ($transportadora->operadores as $operador) {
                $datoparcil = $operador->id;
                foreach ($operador->up_users as $user) {
                    $usersData[] = $user->username . '-' . $datoparcil;
                }
            }
        }

        if (empty($usersData)) {
            $resultData = 'No se encontraron usuarios en up_users.';
        } else {
            $resultData = $usersData;
        }

        return response()->json(['operadores' => $resultData]);
        //      // Extraer y formatear los usuarios (operadores)
        // $usersData = $result->flatMap(function ($transportadora) {
        //     return $transportadora->operadores->flatMap(function ($operador) {
        //         return $operador->up_users->map(function ($user) {
        //             return $user->username. '-' . $user->id ;
        //         });
        //     });
        // });

        // return response()->json(['operadores' => $usersData]);
    }

    public function getTransportsByRoute($idRoute)
    {
        $transportadoras = Transportadora::whereHas('rutas', function ($query) use ($idRoute) {
            $query->where('rutas.id', '=', $idRoute);
        })->where('active', 1)->get();

        if ($transportadoras->isEmpty()) {
            return response()->json([], 200);
        }

        return response()->json($transportadoras);
    }
}
