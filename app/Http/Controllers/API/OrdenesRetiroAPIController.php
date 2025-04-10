<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\TransaccionesAPIController;
use App\Mail\ValidationCode;
use App\Models\OrdenesRetiro;
use App\Models\OrdenesRetirosUsersPermissionsUserLink;
use App\Models\UpUser;
use App\Models\Vendedore;
use App\Models\TransaccionGlobal;


use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;



class OrdenesRetiroAPIController extends Controller
{
    public function show($id)
    {
        $trasnportadora = OrdenesRetiro::findOrFail($id);

        return response()->json($trasnportadora);
    }

    public function withdrawal(Request $request, $id)
    {
        error_log("withdrawalSeller");
        try {

            //     // Obtiene los datos del cuerpo de la solicitud
            $data = $request->validate([
                'monto' => 'required',
                'email' => 'required|email',
                'id_vendedor' => 'required',
                'generated_by' => 'required',
            ]);

            // //     // Obtener datos del request
            $monto = $request->input('monto');
            // $fecha = $request->input('fecha');
            $fecha = date("d/m/Y H:i:s");
            $email = $request->input('email');
            $idVendedor = $request->input('id_vendedor');
            $generated_by = $request->input('generated_by');

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


            Mail::to($email)->send(new ValidationCode($resultCode, $monto));

            //     // Crea un registro de retiro
            $withdrawal = new OrdenesRetiro();
            $withdrawal->monto = $monto;
            $withdrawal->fecha = $fecha;
            $withdrawal->codigo_generado = $resultCode;
            $withdrawal->estado = 'PENDIENTE';
            $withdrawal->id_vendedor = $idVendedor;
            $withdrawal->rol_id = 2;
            $withdrawal->created_by_id = $generated_by;
            $withdrawal->save();

            $ordenUser = new OrdenesRetirosUsersPermissionsUserLink();
            $ordenUser->ordenes_retiro_id = $withdrawal->id;
            $ordenUser->user_id = $id;
            $ordenUser->save();



            return response()->json(['code' => 200]);
        } catch (\Exception $e) {
            error_log("Error_withdrawalSeller: " . $e);

            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function withdrawalProvider(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'monto' => 'required',
                'email' => 'required|email',
                'id_vendedor' => 'required'
            ]);
            $monto = $request->input('monto');
            $email = $request->input('email');
            $idVendedor = $request->input('id_vendedor');

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


            Mail::to($email)->send(new ValidationCode($resultCode, $monto));


            return response()->json(["response" => "code generated succesfully", "code" => $resultCode], Response::HTTP_OK);
        } catch (\Exception $e) {
            error_log("Error withdrawalProvider: " . $e);

            return response()->json([
                "response" => "Error",
                "error" => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function postWhitdrawalProviderAproved(Request $request, $id)
    {
        $data = $request->json()->all();

        $withdrawal = new OrdenesRetiro();
        $withdrawal->monto = $data["monto"];
        $withdrawal->fecha = new DateTime();
        $withdrawal->codigo_generado = $data["codigo"];
        $withdrawal->estado = 'APROBADO';
        $withdrawal->id_vendedor = $data["id_vendedor"];
        $withdrawal->account_id = encrypt($data["account_id"]);
        $withdrawal->rol_id = 5;

        $withdrawal->save();

        $ordenUser = new OrdenesRetirosUsersPermissionsUserLink();
        $ordenUser->ordenes_retiro_id = $withdrawal->id;
        $ordenUser->user_id = $id;
        $ordenUser->save();

        return response()->json(["response" => "solicitud generada exitosamente"], Response::HTTP_OK);
    }

    public function getOrdenesRetiroNew($id, Request $request)
    {
        // Recibir el número de registros por página desde el frontend o usar un valor por defecto (10 en este caso)
        $perPage = $request->input('per_page', 10);  // Si no se envía el parámetro, usa 10 por defecto
        $pageNumber = $request->input('current_page');  // Si no se envía el parámetro, usa 10 por defecto

        // Obtener los retiros con la relación y aplicar paginación
        $retiros = OrdenesRetiro::with('users_permissions_user')
            ->whereHas('users_permissions_user', function ($query) use ($id) {
                $query->where('up_users.id', $id);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $pageNumber);

        return response()->json($retiros);
    }


    // public function getOrdenesRetiroNew($id, Request $request)
    // {
    //     // Recibir el número de registros por página desde el frontend o usar un valor por defecto (10 en este caso)
    //     $perPage = $request->input('per_page', 10);  // Si no se envía el parámetro, usa 10 por defecto

    //     // Obtener los retiros con la relación y aplicar paginación
    //     $retiros = OrdenesRetiro::with('users_permissions_user')
    //         ->whereHas('users_permissions_user', function ($query) use ($id) {
    //             $query->where('up_users.id', $id);
    //         })
    //         ->orderBy('id', 'desc')
    //         ->paginate($perPage); // Usamos paginate en lugar de get para aplicar la paginación

    //     // Retornar la respuesta JSON incluyendo los datos necesarios para el frontend
    //     return response()->json([
    //         'data' => $retiros->items(),  // Los registros paginados
    //         'current_page' => $retiros->currentPage(),  // Página actual
    //         'per_page' => $retiros->perPage(),  // Número de registros por página
    //         'total' => $retiros->total(),  // Total de registros
    //         'last_page' => $retiros->lastPage(),  // Última página
    //         'from' => $retiros->firstItem(),  // Primer ítem mostrado
    //         'to' => $retiros->lastItem()  // Último ítem mostrado
    //     ]);
    // }


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
        if ($id == 0) {
            $pedidos['total_retiros'] = "0.00";
        }

        $ordenes = DB::table('ordenes_retiros as o')
            ->join('ordenes_retiros_users_permissions_user_links as oul', 'o.id', '=', 'oul.ordenes_retiro_id')
            ->where('oul.user_id', $id)
            // ->where('o.estado', 'REALIZADO')
            ->where(function ($query) {
                $query->where('o.estado', 'APROBADO')
                    ->orWhere('o.estado', 'REALIZADO');
            })
            ->select('o.*');

        $total_retiros = $ordenes->sum('o.monto');

        $pedidos['total_retiros'] = number_format($total_retiros, 2, '.', '');

        error_log($pedidos['total_retiros']);

        return response()->json($pedidos);
    }


    public function getCountOrders(Request $request, $idUser)
    {
        $aprobados = OrdenesRetirosUsersPermissionsUserLink::with('ordenes_retiro')
            ->where('user_id', $idUser)
            ->whereHas('ordenes_retiro', function ($query) use ($idUser) {
                $query->where('estado', 'APROBADO');
            })
            ->get();

        $realizados = OrdenesRetirosUsersPermissionsUserLink::with('ordenes_retiro')
            ->where('user_id', $idUser)
            ->whereHas('ordenes_retiro', function ($query) use ($idUser) {
                $query->where('estado', 'REALIZADO');
            })
            ->get();

        $conteoAprobados = $aprobados->count();
        $conteoRealizados = $realizados->count();

        $sumaMontoAprobados = $aprobados->sum(function ($item) {
            return (float) $item->ordenes_retiro->monto;
        });

        $sumaMontoRealizados = $realizados->sum(function ($item) {
            return (float) $item->ordenes_retiro->monto;
        });

        return response()->json([
            'aprobados' => [
                'conteo' => $conteoAprobados,
                'suma_monto' => $sumaMontoAprobados,
            ],
            'realizados' => [
                'conteo' => $conteoRealizados,
                'suma_monto' => $sumaMontoRealizados,
            ],
        ]);
    }


    // new-old
    public function postWithdrawalProvider(Request $request)
    {
        try {

            //code...
            $user = UpUser::where("id", $request->input('user_id'))->with('vendedores')->first();


            // $data = $request->validate([
            //     'monto' => 'required',
            //     'email' => 'required|email',

            // ]);


            $monto = $request->input('monto');
            $email = $request->input('email');
            // $email = "easyecommercetest@gmail.com";
            $user_id = $request->input('user_id');
            // $user = UpUser::where("id", $user_id)->with('vendedores')->first();


            // if ($user->vendedores[0]->saldo >= $monto) {


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


            Mail::to($email)->send(new ValidationCode($resultCode, $monto));


            return response()->json(["response" => "code generated succesfully", "code" => $resultCode], Response::HTTP_OK);
            // } 
            // else {
            //     error_log("saldo insuficiente");

            //     return response()->json(["response" => "saldo insuficiente"], Response::HTTP_BAD_REQUEST);
            // }
        } catch (\Exception $e) {
            error_log("ERROR: $e");

            return response()->json(["response" => "error al generar el codigo", "error" => $e], Response::HTTP_BAD_REQUEST);
        }
    }

    // public function sendEmail(Request $request)
    // {
    //     try {
    //         $data = $request->validate([
    //             'message' => 'required',
    //             'email' => 'required|email',
    //         ]);

    //         $email = $data['email'];
    //         $messageContent = $data['message'];

    //         $to = $email;
    //         $subject = 'Test send email';

    //         Mail::raw($messageContent, function ($mail) use ($to, $subject) {
    //             $mail->to($to)->subject($subject);
    //         });

    //         return response()->json([
    //             "response" => "Email sent successfully",
    //             "code" => Response::HTTP_OK
    //         ], Response::HTTP_OK);
    //     } catch (\Exception $e) {
    //         error_log("Error al enviar email: " . $e);

    //         return response()->json([
    //             "response" => "Error al enviar email",
    //             "error" => $e->getMessage()
    //         ], Response::HTTP_BAD_REQUEST);
    //     }
    // }





    public function totalForSellers()
    {
        try {
            //code...
            $sellers = Vendedore::where('id_master', '!=', '')
                ->where('id_master', '!=', 0)
                ->get();

            $total_realizado = 0;

            foreach ($sellers as $seller) {
                $sellerIdMaster = $seller->id_master;
                $currentTotalResponse = $this->getOrdenesRetiroCount($sellerIdMaster);

                $total_realizado = $currentTotalResponse->original['total_retiros'];
                // error_log("$sellerIdMaster : $total_realizado");
                //last orden_retiro
                $lastWithdrawal = DB::table('ordenes_retiros as o')
                    ->join('ordenes_retiros_users_permissions_user_links as oul', 'o.id', '=', 'oul.ordenes_retiro_id')
                    ->where('oul.user_id', $sellerIdMaster)
                    ->select('o.*')
                    ->orderBy('o.id', 'desc')
                    ->first();

                if ($lastWithdrawal !== null) {
                    $idLast = $lastWithdrawal->id;
                    // error_log("$sellerIdMaster-> $idLast");
                    $withdrawal = OrdenesRetiro::findOrFail($idLast);
                    $withdrawal->previous_value = $total_realizado;
                    $withdrawal->current_value = $total_realizado;
                    $withdrawal->id_vendedor = $sellerIdMaster;
                    $withdrawal->save();
                } else {
                    error_log("No se encontraron retiros para el usuario");
                }

                // error_log("$idLast");

                error_log("***************");
            }

            return response()->json(['message' => 'Se calculó los totales de todos los vendedores'], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateRealizado($sellerIdMaster, Request $request)
    {
        error_log("updateRealizado");

        $data = $request->json()->all();
        $monto = $data["monto"];
        $status = $data["status"];

        $lastWithdrawal = DB::table('ordenes_retiros as o')
            ->join('ordenes_retiros_users_permissions_user_links as oul', 'o.id', '=', 'oul.ordenes_retiro_id')
            ->where('oul.user_id', $sellerIdMaster)
            ->select('o.*')
            ->orderBy('o.id', 'desc')
            ->first();
        if ($lastWithdrawal !== null) {
            $idLast = $lastWithdrawal->id;
            $withdrawal = OrdenesRetiro::findOrFail($idLast);

            if ($withdrawal["current_value"] != null || $withdrawal["previous_value"] == "0.00") {
                //update
                error_log("update");
                if ($status == "REALIZADO") {
                    error_log("REALIZADO");

                    // Valor original
                    $valorOriginal = (float) number_format($withdrawal->current_value, 2, '.', '');
                    $montoDouble = (float) number_format($monto, 2, '.', '');
                    $value = $valorOriginal + $montoDouble;
                    error_log("valorOriginal: $valorOriginal");
                    error_log("montoDouble: $montoDouble");
                    error_log("value: $value");

                    $withdrawal->previous_value = $withdrawal->current_value;
                    $withdrawal->current_value = strval($value);
                    $withdrawal->save();
                } else {
                    error_log("$status");
                    $withdrawal->previous_value = $withdrawal->current_value;
                    $withdrawal->current_value = $withdrawal->current_value;
                    $withdrawal->save();
                }
            } else {
                // calculate the first
                error_log("calculate the first");
                $currentTotalResponse = $this->getOrdenesRetiroCount($sellerIdMaster);
                $total_realizado = $currentTotalResponse->original['total_retiros'];

                $withdrawal->previous_value = $total_realizado;
                $withdrawal->current_value = $total_realizado;
                $withdrawal->id_vendedor = $sellerIdMaster;
                $withdrawal->save();
            }
        } else {
            //
        }
        // return $lastWithdrawal;
        return response()->json($lastWithdrawal, 200);
    }

    public function putIntern(Request $request, $id)
    {
        try {
            $data = $request->json()->all();

            $withdrawal = OrdenesRetiro::findOrFail($id);
            $withdrawal->comprobante = $data["comprobante"];
            $withdrawal->comentario = $data["comentario"];
            // $withdrawal->fecha_transferencia =  Carbon::now()->format('j/n/Y H:i:s');
            $withdrawal->updated_by_id = $data["generated_by"];
            $withdrawal->save();

            return response()->json(["response" => "edited succesfully"], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(["response" => "edidted failed", "error" => $e], Response::HTTP_BAD_REQUEST);
        }
    }

    public function putRechazado(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $transaccionesRepository = app()->make('App\Repositories\transaccionesRepository');
            $vendedorRepository = app()->make('App\Repositories\vendedorRepository');
            $providerTransactionRepository = app()->make('App\Repositories\providerTransactionRepository');
            $providerRepository = app()->make('App\Repositories\providerRepository');

            $transactionsController = new TransaccionesAPIController(
                $transaccionesRepository,
                $vendedorRepository,
                $providerTransactionRepository,
                $providerRepository
            );


            $data = $request->json()->all();
            $monto = $data["monto"];
            $idOrdenRetiro = $id;
            $userSesion = $data["idSesion"];

            $withdrawal = OrdenesRetiro::findOrFail($id);
            $withdrawal->estado = "RECHAZADO";
            $withdrawal->updated_by_id = $userSesion;
            $withdrawal->paid_by = $userSesion;
            $withdrawal->save();

            $idSellerProv = $withdrawal->id_vendedor; //idSeller or provider
            // $marcaTtransferencia = Carbon::createFromFormat('d/m/Y H:i:s', $withdrawal->fecha_transferencia)->format('Y-m-d');

            if ($withdrawal) {
                if ($data["rol_id"] == 2) {
                    $transactionsController->CreditLocal(
                        $idSellerProv,
                        $monto,
                        $idOrdenRetiro,
                        "0000",
                        "reembolso",
                        "reembolso orden retiro cancelada",
                        $userSesion
                    );

                    // ! integracion

                    // // Verificar si ya existe una transacción global para este pedido y vendedor
                    $existingTransaction = TransaccionGlobal::where('origin', 'Retiro de Efectivo')
                        ->where('id_seller', $withdrawal->id_vendedor)
                        ->where('code', 'Retiro-' . $withdrawal->id)
                        ->first();

                    // Obtener la transacción global previa
                    $previousTransactionGlobal = TransaccionGlobal::where('id_seller', $withdrawal->id_vendedor)
                        ->orderBy('id', 'desc')
                        ->first();

                    $previousValue = $previousTransactionGlobal ? $previousTransactionGlobal->current_value : 0;

                    // error_log($existingTransaction);

                    if ($existingTransaction) {
                        $newTransactionGlobal = new TransaccionGlobal();

                        $newTransactionGlobal->admission_date = now()->format('Y-m-d');
                        // $newTransactionGlobal->delivery_date = $marcaTtransferencia;
                        $newTransactionGlobal->status = "REEMBOLSO";
                        // $newTransactionGlobal->return_state = $order->estado_devolucion;
                        $newTransactionGlobal->return_state = null;
                        $newTransactionGlobal->id_order = $withdrawal->id;
                        $newTransactionGlobal->code = 'RETIRO' . "-" . $withdrawal->id;
                        $newTransactionGlobal->origin = 'Retiro de ' . 'Efectivo';
                        // $withdrawal->monto = str_replace(',', '.', $withdrawal->monto);
                        $newTransactionGlobal->withdrawal_price = $withdrawal->monto; // Ajusta según necesites
                        $newTransactionGlobal->value_order = 0; // Ajusta según necesites
                        $newTransactionGlobal->return_cost = 0;
                        $newTransactionGlobal->delivery_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->notdelivery_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->provider_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->referer_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->total_transaction = $withdrawal->monto;
                        $newTransactionGlobal->previous_value = $previousValue;
                        $newTransactionGlobal->current_value = $previousValue + $newTransactionGlobal->total_transaction;
                        $newTransactionGlobal->state = true;
                        $newTransactionGlobal->id_seller = $withdrawal->id_vendedor;
                        $newTransactionGlobal->internal_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_transportation_cost = 0; // Ajusta según necesites
                        $newTransactionGlobal->external_return_cost = 0;
                        $newTransactionGlobal->save();
                    }
                } else {
                    $transactionsController->CreditLocalProvider(
                        $idSellerProv,
                        $monto,
                        $idOrdenRetiro,
                        "reembolso-" . $idOrdenRetiro,
                        "reembolso proveedor",
                        $userSesion
                    );
                }
            }

            DB::commit(); // Confirma la transacción si todas las operaciones tienen éxito

            return response()->json(["response" => "update succesfully"], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollback(); // En caso de error, revierte todos los cambios realizados en la transacción
            error_log("error_putRechazado: $e");
            return response()->json(["response" => "edited failed", "error" => $e], Response::HTTP_BAD_REQUEST);
        }
    }
}
