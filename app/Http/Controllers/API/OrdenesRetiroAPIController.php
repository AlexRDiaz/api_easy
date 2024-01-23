<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ValidationCode;
use App\Models\OrdenesRetiro;
use App\Models\OrdenesRetirosUsersPermissionsUserLink;
use App\Models\UpUser;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrdenesRetiroAPIController extends Controller
{
    public function show($id)
    {
        $trasnportadora = OrdenesRetiro::findOrFail($id);

        return response()->json($trasnportadora);

    }
    public function withdrawal(Request $request, $id)
    {
        //     // Obtiene los datos del cuerpo de la solicitud
        $data = $request->validate([
            'monto' => 'required',
            'fecha' => 'required',
            'email' => 'required|email',
            'id_vendedor' => 'required'
        ]);

        // //     // Obtener datos del request
        $monto = $request->input('monto');
        $fecha = $request->input('fecha');
        $email  = $request->input('email');
        $idVendedor  = $request->input('id_vendedor');

        // //     // Generar código único
        $numerosUtilizados = [];
        while (count($numerosUtilizados) < 10000000) {
            $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
            if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                $numerosUtilizados[] = $numeroAleatorio;
                break;
            }
        }
        $resultCode = $numeroAleatorio;
      //  $resultCode = implode('', array_slice($numerosUnicos, 0, 8));

        
        Mail::to($email)->send(new ValidationCode($resultCode,$monto));
      
        //     // Crea un registro de retiro
            $withdrawal = new OrdenesRetiro();
            $withdrawal->monto =$monto;
            $withdrawal->fecha = $fecha;
            $withdrawal->codigo_generado = $resultCode;
            $withdrawal->estado = 'PENDIENTE';
            $withdrawal->id_vendedor = $idVendedor;
            $withdrawal->save();
        
            $ordenUser=new OrdenesRetirosUsersPermissionsUserLink(); 
            $ordenUser->ordenes_retiro_id=$withdrawal->id;
            $ordenUser->user_id=$id;
            $ordenUser->save();



        return response()->json(['code' => 200]);
    }

    public function withdrawalProvider(Request $request, $id)
    {
        $data = $request->validate([
            'monto' => 'required',
            'email' => 'required|email',
            'id_vendedor' => 'required'
        ]);
        $monto = $request->input('monto');
        $email = $request->input('email');
        $idVendedor  = $request->input('id_vendedor');

        //     // Generar código único
        $numerosUtilizados = [];
        while (count($numerosUtilizados) < 10000000) {
            $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
            if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                $numerosUtilizados[] = $numeroAleatorio;
                break;
            }
        }
        $resultCode = $numeroAleatorio;

        
        Mail::to($email)->send(new ValidationCode($resultCode,$monto));
      
      
            return response()->json(["response"=>"code generated succesfully","code"=>$resultCode], Response::HTTP_OK);
        }

        public function postWhitdrawalProviderAproved(Request $request,$id){
            $data = $request->json()->all();

                 $withdrawal = new OrdenesRetiro();
            $withdrawal->monto =$data["monto"];
            $withdrawal->fecha = new  DateTime();
            $withdrawal->codigo_generado = $data["codigo"];
            $withdrawal->estado = 'APROBADO';
            $withdrawal->id_vendedor =  $data["id_vendedor"];
            $withdrawal->account_id = encrypt($data["account_id"]);

            $withdrawal->save();
        
            $ordenUser=new OrdenesRetirosUsersPermissionsUserLink(); 
            $ordenUser->ordenes_retiro_id=$withdrawal->id;
            $ordenUser->user_id=$id;
            $ordenUser->save();

            return response()->json(["response"=>"solicitud generada exitosamente"], Response::HTTP_OK);

        }

    public function getOrdenesRetiroNew($id, Request $request)
    { 

        $retiros= OrdenesRetiro::with('users_permissions_user')->whereHas('users_permissions_user', function ($query) use ($id) {
            $query->where('up_users.id', $id);
        })->get();
        

        return response()->json($retiros);

    }
    
    public function getOrdenesRetiro($id, Request $request)

    {

        $data = $request->json()->all();
        // $startDate = $data['start'];
        // $endDate = $data['end'];
        // $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        // $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');

        $pageSize = $data['page_size'];
        $pageNumber = $data['page_number'];

        $upuser = UpUser::find($id);
        if ($upuser) {

            $upuser = UpUser::find($id);
            if ($upuser) {
                $retiros = DB::table('ordenes_retiros as o')
                    ->whereExists(function ($query) use ($id) {
                        $query->select(DB::raw(1))
                            ->from('ordenes_retiros_users_permissions_user_links as orul')
                            ->whereRaw('o.id = orul.ordenes_retiro_id')
                            ->where('orul.user_id', '=', $id);
                    })
                    ->select('o.*');

                // ! Ordenamiento ********************************** 
                $orderByText = null;
                $orderByDate = null;
                $sort = $data['sort'];
                $sortParts = explode(':', $sort);

                $pt1 = $sortParts[0];

                $type = (stripos($pt1, 'fecha') !== false || stripos($pt1, 'marca') !== false) ? 'date' : 'text';

                $dataSort = [
                    [
                        'field' => $sortParts[0],
                        'type' => $type,
                        'direction' => $sortParts[1],
                    ],
                ];

                foreach ($dataSort as $value) {
                    $field = $value['field'];
                    $direction = $value['direction'];
                    $type = $value['type'];

                    if ($type === "text") {
                        $orderByText = [$field => $direction];
                    } else {
                        $orderByDate = [$field => $direction];
                    }
                }

                if ($orderByText !== null) {
                    $retiros->orderBy(key($orderByText), reset($orderByText));
                } else {
                    $retiros->orderBy(DB::raw("STR_TO_DATE(" . key($orderByDate) . ", '%e/%c/%Y')"), reset($orderByDate));
                }
                // ! **************************************************
                $pedidos = $retiros->paginate($pageSize, ['*'], 'page', $pageNumber);

                return response()->json($pedidos);
            } else {
                return response()->json(['message' => 'No se encontro el user'], 404);
            }
        }
    }
    public function getOrdenesRetiroCount($id)
    {
        if($id==0){
                $pedidos['total_retiros'] = "0.00";
        }
    
        $ordenes = DB::table('ordenes_retiros as o')
            ->join('ordenes_retiros_users_permissions_user_links as oul', 'o.id', '=', 'oul.ordenes_retiro_id')
            ->where('oul.user_id', $id)
            ->where('o.estado', 'REALIZADO')
            ->select('o.*');
    
        $total_retiros = $ordenes->sum('o.monto');
    
        $pedidos['total_retiros'] = number_format($total_retiros, 2, '.', '');
    
        return response()->json($pedidos);
    }
}
