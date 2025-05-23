<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\UserValidation;
use App\Models\Integration;
use App\Models\Operadore;
use App\Models\OperadoresSubRutaLink;
use App\Models\OperadoresTransportadoraLink;
use App\Models\OrdenesRetiro;
use App\Models\PedidosShopify;
use App\Models\Provider;
use App\Models\ProviderWarehouseLink;
use App\Models\RolesFront;
use App\Models\Ruta;
use App\Models\Transportadora;
use App\Models\TransportadorasRutasLink;
use App\Models\UpUsersVendedoresLink;
use App\Models\TransportadorasUsersPermissionsUserLink;
use App\Models\UpRole;
use App\Models\UpUser;
use App\Models\UpUsersOperadoreLink;
use App\Models\UpUsersRoleLink;
use App\Models\UpUsersRolesFrontLink;
use App\Models\Vendedore;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class UpUserAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $upUsers = UpUser::all();
        return response()->json(['data' => $upUsers], Response::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $upUser = UpUser::find($id);
        if (!$upUser) {
            return response()->json(['message' => 'UpUser not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['data' => $upUser], Response::HTTP_OK);
    }

    // Puedes agregar métodos adicionales según tus necesidades, como create, update y delete.

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Valida los datos de entrada (puedes agregar reglas de validación aquí)
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:up_users',
        ]);

        $numerosUtilizados = [];
        while (count($numerosUtilizados) < 10000000) {
            $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
            if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                $numerosUtilizados[] = $numeroAleatorio;
                break;
            }
        }
        $resultCode = $numeroAleatorio;


        $user = new UpUser();
        $user->username = $request->input('username');
        $user->email = $request->input('email');
        $user->codigo_generado = $resultCode;
        $user->password = bcrypt('123456789'); // Puedes utilizar bcrypt para encriptar la contraseña
        $user->fecha_alta = $request->input('FechaAlta'); // Fecha actual
        $user->confirmed = $request->input('confirmed');
        $user->estado = $request->input('estado');
        $permisosCadena = json_encode($request->input('PERMISOS'));
        $user->permisos = $permisosCadena;
        $user->blocked = false;
        $user->company_id = $request->input('company_id');
        $user->save();
        $user->vendedores()->attach($request->input('vendedores'), []);

        $newUpUsersRoleLink = new UpUsersRoleLink();
        $newUpUsersRoleLink->user_id = $user->id; // Asigna el ID del usuario existente
        $newUpUsersRoleLink->role_id = $request->input('role'); // Asigna el ID del rol existente
        $newUpUsersRoleLink->save();


        $userRoleFront = new UpUsersRolesFrontLink();
        $userRoleFront->user_id = $user->id;
        $userRoleFront->roles_front_id = $request->input('roles_front');
        $userRoleFront->save();



        Mail::to($user->email)->send(new UserValidation($resultCode));


        return response()->json(['message' => 'Usuario interno creado con éxito', 'user_id' => $user->id, 'user_id'], 201);
    }


    public function storeSubProvider(Request $request)
    {
        // Valida los datos de entrada (puedes agregar reglas de validación aquí)
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:up_users',
        ]);

        $numerosUtilizados = [];
        while (count($numerosUtilizados) < 10000000) {
            $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
            if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                $numerosUtilizados[] = $numeroAleatorio;
                break;
            }
        }
        $resultCode = $numeroAleatorio;


        $user = new UpUser();
        $user->username = $request->input('username');
        $user->email = $request->input('email');
        $user->codigo_generado = $resultCode;
        $user->password = bcrypt('123456789'); // Puedes utilizar bcrypt para encriptar la contraseña
        $user->fecha_alta = $request->input('FechaAlta'); // Fecha actual
        $user->confirmed = $request->input('confirmed');
        $user->estado = $request->input('estado');
        // $permisosCadena = json_encode($request->input('PERMISOS'));
        // $user->permisos = $permisosCadena;
        $user->permisos = json_encode($request->input('permisos'));
        $user->blocked = false;
        $user->company_id = $request->input('company_id');
        $user->save();
        $user->providers()->attach($request->input('providers'), []);

        $newUpUsersRoleLink = new UpUsersRoleLink();
        $newUpUsersRoleLink->user_id = $user->id; // Asigna el ID del usuario existente
        $newUpUsersRoleLink->role_id = $request->input('role'); // Asigna el ID del rol existente
        $newUpUsersRoleLink->save();


        $userRoleFront = new UpUsersRolesFrontLink();
        $userRoleFront->user_id = $user->id;
        $userRoleFront->roles_front_id = $request->input('roles_front');
        $userRoleFront->save();



        Mail::to($user->email)->send(new UserValidation($resultCode));


        return response()->json(['message' => 'Subproveedor creado con éxito', 'user_id' => $user->id, 'user_id'], 200);
    }

    public function editAutome(Request $request, $id)
    {
        $data = $request->json()->all();

        $user = UpUser::find($id);
        $user->enable_autome = $data["enable_autome"];
        $user->config_autome = $data["config_autome"];
        $user->save();
    }

    public function updateSubProvider(Request $request, $id)
    {
        // Valida los datos de entrada (puedes agregar reglas de validación aquí)
        // Valida los datos de entrada para la actualización
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:up_users,email,' . $id,
            // Asegúrate de manejar la unicidad del email excepto para el usuario que se está actualizando
        ]);

        $user = UpUser::find($id); // Encuentra al usuario por su ID

        if ($user) {
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->blocked = $request->input('blocked');
            $user->save(); // Guarda los cambios en el usuario

            return response()->json(['message' => 'Vendedor actualizado con éxito', "user" => $user], 200);
        } else {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
    }


    public function storeProvider(Request $request)
    {
        error_log("storeProvider");
        try {

            // // Valida los datos de entrada (puedes agregar reglas de validación aquí)
            $request->validate([
                'username' => 'required|string|max:255',
                'email' => 'required|email|unique:up_users',
            ]);

            $numerosUtilizados = [];
            while (count($numerosUtilizados) < 10000000) {
                $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
                if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                    $numerosUtilizados[] = $numeroAleatorio;
                    break;
                }
            }
            $resultCode = $numeroAleatorio;

            DB::beginTransaction();

            $user = new UpUser();
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->codigo_generado = $resultCode;
            $user->password = bcrypt($request->input('password')); // Puedes utilizar bcrypt para encriptar la contraseña
            $user->fecha_alta = $request->input('FechaAlta'); // Fecha actual
            $user->confirmed = $request->input('confirmed');
            $user->estado = "NO VALIDADO";
            $user->provider = "local";
            $user->confirmed = 1;
            $user->fecha_alta = $request->input('fecha_alta');
            // $permisosCadena = json_encode([]);
            // $user->permisos = $permisosCadena;
            $user->permisos = json_encode($request->input('permisos'));
            $user->blocked = false;
            $user->company_id = $request->input('company_id');
            $user->save();
            // $user->providers()->attach($user->id, [
            // ]);


            // $provider= new Provider();
            // $provider->user_id = $user->id;
            // $provider->name = $request->input('porvider_name');
            // $provider->description= $request->input('description');
            // $provider->phone = $request->input('provider_phone');
            // $provider->createdAt= new DateTime();


            $newUpUsersRoleLink = new UpUsersRoleLink();
            $newUpUsersRoleLink->user_id = $user->id; // Asigna el ID del usuario existente
            $newUpUsersRoleLink->role_id = $request->input('role'); // Asigna el ID del rol existente
            $newUpUsersRoleLink->save();



            $userRoleFront = new UpUsersRolesFrontLink();
            $userRoleFront->user_id = $user->id;
            $userRoleFront->roles_front_id = 5;
            $userRoleFront->save();

            $provider = new Provider();
            $provider->name = $request->input('provider_name');
            $provider->phone = $request->input('provider_phone');
            $provider->description = $request->input('description');
            $provider->special = $request->input('special');
            $provider->created_at = new DateTime();
            $provider->user_id = $user->id;
            $provider->saldo = 0;
            $provider->company_id = $request->input('company_id');

            $provider->save();
            $user->providers()->attach($provider->id, []);

            // Mail::to($user->email)->send(new UserValidation($resultCode));

            DB::commit();
            return response()->json(['message' => 'Provider creado con éxito'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("ErrorProv: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function storeGeneral(Request $request)
    {
        // // Valida los datos de entrada (puedes agregar reglas de validación aquí)
        error_log("storeReferido");
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:up_users',
        ]);

        $numerosUtilizados = [];
        while (count($numerosUtilizados) < 10000000) {
            $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
            if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                $numerosUtilizados[] = $numeroAleatorio;
                break;
            }
        }
        $resultCode = $numeroAleatorio;

        DB::beginTransaction();
        try {
            $userFound = UpUser::where('id', $request->input('referer'))->first();
            $refererCompanyId = $userFound->company_id;
            // error_log("refererCompanyId: $refererCompanyId");

            $rolSeller = RolesFront::where('titulo', 'VENDEDOR')->first();
            $accesos = json_decode($rolSeller->accesos, true);

            foreach ($accesos as $acceso) {
                if (isset($acceso['active']) && $acceso['active'] === true) {
                    $activeViewsNames[] = $acceso['view_name'];
                }
            }
            $activeViewsCadena = json_encode($activeViewsNames);


            $user = new UpUser();
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->codigo_generado = $resultCode;
            $user->password = bcrypt($request->input('password')); // Puedes utilizar bcrypt para encriptar la contraseña
            $user->fecha_alta = $request->input('FechaAlta'); // Fecha actual
            $user->confirmed = $request->input('confirmed');
            $user->estado = "NO VALIDADO";
            $user->provider = "local";
            $user->confirmed = 1;
            $user->fecha_alta = $request->input('fecha_alta');
            // $permisosCadena = json_encode(["DashBoard", "Reporte de Ventas", "Agregar Usuarios Vendedores", "Ingreso de Pedidos", "Estado Entregas Pedidos", "Pedidos No Deseados", "Billetera", "Devoluciones", "Retiros en Efectivo", "Conoce a tu Transporte"]);
            // $user->permisos = $permisosCadena;
            $user->permisos = $activeViewsCadena;
            $user->blocked = false;
            $user->company_id = $refererCompanyId;
            $user->save();
            $user->vendedores()->attach($request->input('vendedores'), []);



            $newUpUsersRoleLink = new UpUsersRoleLink();
            $newUpUsersRoleLink->user_id = $user->id; // Asigna el ID del usuario existente
            $newUpUsersRoleLink->role_id = $request->input('role'); // Asigna el ID del rol existente
            $newUpUsersRoleLink->save();


            $userRoleFront = new UpUsersRolesFrontLink();
            $userRoleFront->user_id = $user->id;
            $userRoleFront->roles_front_id = 2;
            $userRoleFront->save();

            $seller = new Vendedore();
            $seller->nombre_comercial = $request->input('nombre_comercial');
            $seller->telefono_1 = $request->input('telefono1');
            $seller->telefono_2 = $request->input('telefono2');
            $seller->nombre_comercial = $request->input('nombre_comercial');
            $seller->fecha_alta = $request->input('fecha_alta');
            $seller->id_master = $user->id;
            $seller->url_tienda = $request->input('url_tienda');
            // $seller->costo_envio = $request->input('costo_envio');
            // $seller->costo_devolucion = $request->input('costo_devolucion');
            $seller->costo_envio = 6;
            $seller->costo_devolucion = 6;
            $seller->referer = $request->input('referer');
            $seller->saldo = 0;
            $seller->company_id = $refererCompanyId;
            $seller->save();

            $user->vendedores()->attach($seller->id, []);

            if ($seller) {

                try {
                    Mail::to($user->email)->send(new UserValidation($resultCode));
                } catch (\Exception $e) {
                    error_log("Error_storeReferido al enviar email con el newSeller-resultCode  $user->id: $e");
                }
                DB::commit();

                return response()->json(['message' => 'Vendedor creado con éxito'], 200);
            } else {
                DB::rollback();
                error_log("Error_storeReferido al crear UsuarioReferido");
                return response()->json(['message' => 'storeSellerWPError al crear Usuario'], 404);
            }
        } catch (\Exception $e) {
            DB::rollback();
            error_log("Error_storeReferido: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function updateProvider(Request $request, $id)
    {
        // Valida los datos de entrada para la actualización
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:up_users,email,' . $id,
            // Asegúrate de manejar la unicidad del email excepto para el usuario que se está actualizando
        ]);

        $user = UpUser::find($id); // Encuentra al usuario por su ID

        if ($user) {
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->save(); // Guarda los cambios en el usuario

            $provider = $user->providers[0];
            // Suponiendo una relación "user has one provider"
            if ($provider) {
                $provider->name = $request->input('provider_name');
                $provider->phone = $request->input('provider_phone');
                $provider->description = $request->input('description');

                $provider->save(); // Guarda los cambios en el proveedor
            }



            return response()->json(['message' => 'Vendedor actualizado con éxito', "proveedor" => $provider, "user" => $user], 200);
        } else {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
    }

    public function getSellerMaster($id)
    {
        $vendedores = UpUser::find($id)->vendedores;

        if (!$vendedores) {
            return response()->json(['message' => 'Vendedores not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($vendedores[0], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $upUser = UpUser::find($id);
        $newPassword = $request->input('password');

        if (!$upUser) {
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        if ($newPassword) {
            $upUser->password = bcrypt($newPassword);
            $upUser->save();
            return response()->json(['message' => 'Contraseña actualizada con éxito', 'user' => $upUser], Response::HTTP_OK);
        } else {
            $upUser->fill($request->all());
            $upUser->save();
            return response()->json(['message' => 'Usuario actualizado con éxito', 'user' => $upUser], Response::HTTP_OK);
        }

        // Agrega tu lógica para actualizar un UpUser existente aquí.
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // Agrega tu lógica para eliminar un UpUser aquí.
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Buscar al usuario por su correo electrónico en la tabla UpUser
        $user = UpUser::where('email', $credentials['email'])->first();

        if (!$user) {
            error_log("1");
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Validar la contraseña proporcionada por el usuario con el hash almacenado en la base de datos
        if (!Hash::check($credentials['password'], $user->password)) {
            error_log("2");

            return response()->json(['error' => 'Credenciales inválidas'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->blocked == 1 && $user->active == 1) {
            error_log("3");

            return response()->json(['error' => 'Usuario Bloqueado'], Response::HTTP_UNAUTHORIZED);
        }
        if ($user->active == 0) {
            error_log("3");

            return response()->json(['error' => 'Usuario Eliminado'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Intentar generar un token JWT
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Credenciales inválidas'], Response::HTTP_UNAUTHORIZED);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Guardar algunos datos del usuario en la sesión
        // $request->session()->put('user_id', $user->id);
        // $request->session()->put('user_email', $user->email);
        $mensaje = "usuario logueado";
        error_log("usuario logueado");
        return response()->json([
            'jwt' => $token,
            'user' => $user
        ], Response::HTTP_OK);
    }


    public function generateIntegration(Request $request)
    {

        $data = $request->json()->all();
        $user = UpUser::find($data["user_id"]);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        try {
            // Intenta autenticar al usuario y generar un token
            if (!$token = JWTAuth::fromUser($user, ['exp' => null])) {
                return response()->json(['error' => 'No se pudo generar el token'], Response::HTTP_UNAUTHORIZED);
            }



            $integration = Integration::where("name", $data["name"])->first();


            if (!empty($integration)) {
                return response()->json([
                    'integration' => $integration,
                    'error' => 'Nombre ya ingresado'
                ], 404);
            }
            $newIntegration = new Integration();
            $newIntegration->name = $data["name"];
            $newIntegration->description = $data["description"];
            $newIntegration->token = $token;
            $newIntegration->created_at = new DateTime();
            $newIntegration->user_id = $data["user_id"];
            $newIntegration->save();
            return response()->json(['integration' => $newIntegration], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar el token', "e" => $e], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function users($id)
    {
        $upUser = UpUser::with([
            'roles_fronts',
            'vendedores',
            'transportadora',
            'operadores.transportadoras',
            'providers',
        ])->find($id);

        if (!$upUser) {
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['user' => $upUser], Response::HTTP_OK);
    }

    public function userspdf($id)
    {
        $upUser = UpUser::with([
            'roles_fronts',
            'vendedores',
            'transportadora',
            'operadores',
            'providers',
        ])->find($id);

        if (!$upUser) {
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['user' => $upUser], Response::HTTP_OK);
    }

    public function updatePaymentInformation(Request $request, $id)
    {
        try {
            $data = $request->json()->all();
            $myuuid = Uuid::uuid4();
            $data["id"] = $myuuid;
            $user = UpUser::find($id);

            if ($user->payment_information == null || $user->payment_information == "") {

                $jsonData = json_encode([$data]);
                $encryptedData = encrypt($jsonData);
                $user->payment_information = $encryptedData;
            } else {
                $currentPaymentInformation = $this->getPaymentInformationLocal($id);
                array_push($currentPaymentInformation, $data);
                $jsonData2 = json_encode($currentPaymentInformation);
                $encryptedData2 = encrypt($jsonData2);
                $user->payment_information = $encryptedData2;
            }

            $user->save();

            return response()->json(['message' => 'User modified successfully', $currentPaymentInformation], Response::HTTP_OK);
        } catch (\Exception $e) {
            error_log("Error updatePaymentInformation: $e");
            return response()->json(['error' => 'User modify failed', $e], Response::HTTP_BAD_REQUEST);
        }
    }

    public function modifyAccount(Request $request, $id)
    {
        try {

            $data = $request->json()->all();
            $user = UpUser::find($id);
            // $myuuid = Uuid::uuid4();
            // $data["account_data"]["id"]= $myuuid;

            $jsonData = json_encode($data["account_data"]);
            $encryptedData = encrypt($jsonData);
            $user->payment_information = $encryptedData;


            $user->save();

            return response()->json(['message' => 'User modified successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'User modify failed', $e], Response::HTTP_BAD_REQUEST);
        }
    }

    public function getPaymentInformationfromWithdrawal($id)
    {
        try {
            $ordenR = OrdenesRetiro::where("id", $id)->first();
            if ($ordenR->account_id != null) {
                $userId = $ordenR->id_vendedor;
                $user = UpUser::find($userId);
                if ($user->payment_information != null) {
                    $decriptedData = decrypt($user->payment_information);
                    $decodedData = json_decode($decriptedData, true);

                    foreach ($decodedData as $data) {
                        if ($data['id'] === $ordenR->account_id) {
                            return response()->json(['message' => 'Get successfully', 'data' => $data], Response::HTTP_OK);
                        }
                    }
                }
            } else {
                return response()->json(['message' => 'Empty', 'data' => []], Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Get failed', $e], Response::HTTP_BAD_REQUEST);
        }
    }


    public function getPaymentInformation($id)
    {
        try {


            $user = UpUser::find($id);
            if ($user->payment_information != null) {
                $decriptedData = decrypt($user->payment_information);

                return response()->json(['message' => 'Get successfully', 'data' => json_decode($decriptedData)], Response::HTTP_OK);
            } else {
                return response()->json(['message' => 'Empty', 'data' => []], Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            error_log("Error getPaymentInformation: $e");
            return response()->json(['error' => 'Get failed', $e], Response::HTTP_BAD_REQUEST);
        }
    }

    public function getPaymentInformationLocal($id)
    {
        try {


            $user = UpUser::find($id);
            $decriptedData = decrypt($user->payment_information);

            return json_decode($decriptedData);
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function managePermission(Request $request)
    {
        $viewName = $request->input('view_name');
        $userId = $request->input('user_id');

        $upUser = UpUser::find($userId);

        // Si el usuario no existe, devuelve un error
        if (!$upUser) {
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Decodificar el JSON de la columna permisos a un array
        $permissions = json_decode($upUser->permisos, true);

        if (in_array($viewName, $permissions)) {
            // Si el nombre de la vista ya existe, lo eliminamos
            $permissions = array_filter($permissions, function ($value) use ($viewName) {
                return $value !== $viewName;
            });
            $permissions = array_values($permissions); // Reindexa las claves
        } else {
            // Si el nombre de la vista no existe, lo agregamos
            $permissions[] = $viewName;
        }

        // Actualizamos el usuario con los permisos modificados
        $upUser->permisos = json_encode($permissions);
        $upUser->save();

        return response()->json(['message' => 'Permisos actualizados correctamente'], Response::HTTP_OK);
    }

    public function getPermissionsSellerPrincipalforNewSeller(Request $request, $userId)
    {
        $upUser = UpUser::find($userId);

        // Si el usuario no existe, devuelve un error
        if (!$upUser) {
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Decodificar el JSON de la columna permisos a un array
        $permissions = json_decode($upUser->permisos, true);
        $formattedPermissions = [];

        foreach ($permissions as $permission) {
            $formattedPermissions[] = [
                'view_name' => $permission,
                'active' => true
            ];
        }

        // Codifica el array resultante a JSON y luego agrégalo a un array con la clave "accesos"
        $result = [
            'accesos' => json_encode($formattedPermissions)
        ];

        // Retorna la lista de permisos en el formato deseado
        return response()->json($result, Response::HTTP_OK);
    }


    public function getSellers($id, $search = null)
    {
        $upUser = UpUser::with([
            'roles_fronts',
            'vendedores',
            'transportadora',
            'operadores',

        ])
            ->whereHas('vendedores', function ($query) use ($id) {
                $query->where('id_master', $id);
            });


        if (!empty($search)) {
            $upUser->where(function ($query) use ($search) {
                $query->where('username', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $resp = $upUser->get();
        return response()->json(['consulta' => $search, 'users' => $resp], Response::HTTP_OK);
    }

    public function getSubProviders($id, $search = null)
    {
        $upUser = UpUser::with([
            'roles_fronts',
            'providers',
            'warehouses',
        ])->whereNot("id", $id)
            ->where("blocked", 0)
            ->where("active", 1)
            ->whereHas('providers', function ($query) use ($id) {
                $query->where('user_id', $id);
            });


        if (!empty($search)) {
            $upUser->where(function ($query) use ($search) {
                $query->where('username', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $resp = $upUser->get();
        return response()->json(['consulta' => $search, 'users' => $resp], Response::HTTP_OK);
    }

    public function verifyTerms($id)
    {
        $upuser = Upuser::find($id);
        if ($upuser) {
            $acceptedTerms = $upuser->accepted_terms_conditions;
            if ($acceptedTerms == null) {
                $acceptedTerms = false;
            }
            return response()->json($acceptedTerms);
        } else {
            return response()->json(['message' => 'No se encontro el user'], 404);
        }
    }

    public function updateAcceptedTerms($id, Request $request)
    {
        $user_found = UpUser::findOrFail($id);
        $accepted_terms = $request->input('accepted_terms_conditions');

        // Update 'accepted_terms_conditions'
        $user_found->accepted_terms_conditions = $accepted_terms;
        $user_found->save();

        return response()->json(['message' => 'Estado de Términos y condiciones actualizados con éxito'], 200);
    }


    // ! FUNCION DE VENDEDORES QUE NECESITA NERFEO :) 
    public function getUserPedidos($id, Request $request)
    {
        $user = UpUser::with('upUsersPedidos.pedidos_shopifies_ruta_links.ruta', 'upUsersPedidos.pedidos_shopifies_transportadora_links.transportadora')->find($id);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $pedidos = $user->upUsersPedidos->where('estado_interno', 'CONFIRMADO')->where('estado_logistico', 'ENVIADO');


        $allRutasTransportadoras = collect();
        $pedidosInfo = [];
        $entregadosCount = 0;
        $noEntregadosCount = 0;
        $novedad = 0;

        foreach ($pedidos as $pedido) {
            $rutasInfo = $pedido->pedidos_shopifies_ruta_links->map(function ($link) {
                return $link->ruta->titulo . '-' . $link->ruta->id;
            })->implode(', ');

            $transportadorasInfo = $pedido->pedidos_shopifies_transportadora_links->map(function ($link) {
                return $link->transportadora->nombre . '-' . $link->transportadora->id;
            })->implode(', ');

            $allRutasTransportadoras->push($rutasInfo . '|' . $transportadorasInfo);

            $status = $pedido->status;

            if ($status === 'ENTREGADO') {
                $entregadosCount++;
            } else if ($status === 'NO ENTREGADO') {
                $noEntregadosCount++;
            } else if ($status === 'NOVEDAD') {
                $novedad++;
            }

            $pedidosInfo[] = [
                'pedido_id' => $pedido->id,
                'rutas' => $rutasInfo,
                'transportadoras' => $transportadorasInfo,
                'status' => $status,
            ];
        }

        // Obtener listas únicas sin repeticiones
        $uniqueRutasTransportadoras = $allRutasTransportadoras->unique()->values();

        $rutaTransportadoraCount = collect();

        foreach ($uniqueRutasTransportadoras as $uniqueInfo) {
            list($rutas, $transportadora) = explode('|', $uniqueInfo);

            $counts = collect($pedidosInfo)->where('rutas', $rutas)->where('transportadoras', $transportadora)->countBy('status')->toArray();

            $rutaTransportadoraCount->push([
                'pedidos_info' => $pedidosInfo,
                'rutas' => $rutas,
                'transportadoras' => $transportadora,
                'entregados_count' => $counts['ENTREGADO'] ?? 0,
                'no_entregados_count' => $counts['NO ENTREGADO'] ?? 0,
                'novedad_count' => $counts['NOVEDAD'] ?? 0,
                'total_pedidos' => ($counts['ENTREGADO'] ?? 0) + ($counts['NO ENTREGADO'] ?? 0) + ($counts['NOVEDAD'] ?? 0),

            ]);
        }

        // Agrupar internamente por la propiedad "rutas"
        $groupedRutasTransportadoras = $rutaTransportadoraCount->groupBy('rutas')->map(function ($group) {
            return $group->map(function ($item) {
                return [
                    'transportadoras' => $item['transportadoras'],
                    'entregados_count' => $item['entregados_count'],
                    'no_entregados_count' => $item['no_entregados_count'],
                    'novedad_count' => $item['novedad_count'],
                    'total_pedidos' => $item['total_pedidos'],
                ];
            });
        });

        return response()->json([
            'pedidos' => $pedidosInfo,
            'listarutas_transportadoras' => $groupedRutasTransportadoras,
            'entregados_count' => $entregadosCount,
            'no_entregados_count' => $noEntregadosCount,
            'novedad_count' => $novedad,
            'total_pedidos' => $entregadosCount + $noEntregadosCount + $novedad,
        ]);
    }

    // ! FUNCION PARECIDA A NERFEO TRANS:) 
    public function getUserPedidosByTransportadora($idTransportadora, Request $request)
    {
        $transportadora = Transportadora::find($idTransportadora);

        if (!$transportadora) {
            return response()->json(['error' => 'Transportadora no encontrada'], 404);
        }

        $pedidos = PedidosShopify::whereHas('pedidos_shopifies_transportadora_links', function ($query) use ($idTransportadora) {
            $query->where('transportadora_id', $idTransportadora)
                ->where('estado_interno', 'CONFIRMADO')
                ->where('estado_logistico', 'ENVIADO');
        })->get();


        // Filtrar los pedidos entregados
        $entregados = $pedidos->filter(function ($pedido) {
            return $pedido->status === 'ENTREGADO';
        });

        // Filtrar los pedidos no entregados
        $noEntregados = $pedidos->filter(function ($pedido) {
            return $pedido->status === 'NO ENTREGADO';
        });

        // Filtrar los pedidos con novedad
        $novedad = $pedidos->filter(function ($pedido) {
            return $pedido->status === 'NOVEDAD';
        });

        // Contar la cantidad de pedidos entregados, no entregados y con novedad
        $cantidadEntregados = $entregados->count();
        $cantidadNoEntregados = $noEntregados->count();
        $cantidadNovedad = $novedad->count();

        // Calcular la suma total de ambos
        $sumaTotal = $cantidadEntregados + $cantidadNoEntregados + $cantidadNovedad;

        return response()->json([
            'identification' => $transportadora->nombre . '-' . $transportadora->id,
            'entregados_count' => $cantidadEntregados,
            'no_entregados_count' => $cantidadNoEntregados,
            'novedad_count' => $cantidadNovedad,
            'total_pedidos' => $sumaTotal,
        ]);
    }

    // ! FUNCION PARECIDA A NERFEO ROUTES:) 

    public function getUserPedidosByRuta($idRuta, Request $request)
    {
        $ruta = Ruta::find($idRuta);

        if (!$ruta) {
            return response()->json(['error' => 'Ruta no encontrada'], 404);
        }

        $pedidos = PedidosShopify::whereHas('pedidos_shopifies_ruta_links', function ($query) use ($idRuta) {
            $query->where('ruta_id', $idRuta);
        })
            ->where('estado_interno', 'CONFIRMADO')
            ->where('estado_logistico', 'ENVIADO')
            ->get();

        $entregadosCount = 0;
        $noEntregadosCount = 0;
        $novedad = 0;

        foreach ($pedidos as $pedido) {
            $status = $pedido->status;
            if ($status === 'ENTREGADO') {
                $entregadosCount++;
            } else if ($status === 'NO ENTREGADO') {
                $noEntregadosCount++;
            } else if ($status === 'NOVEDAD') {
                $novedad++;
            }
        }

        return response()->json([
            'identification' => $ruta->titulo . '-' . $ruta->id,
            'entregados_count' => $entregadosCount,
            'no_entregados_count' => $noEntregadosCount,
            'novedad_count' => $novedad,
            'total_pedidos' => $entregadosCount + $noEntregadosCount + $novedad,
        ]);
    }

    public function getPermisos()
    {
        // Obtén todos los registros de la tabla up_users_roles_front_links
        $registros = UpUser::all();

        // Si deseas devolver solo los campos 'id' y 'permisos', puedes hacer un mapeo
        $permisos = $registros->map(function ($registro) {
            return [
                'id' => $registro->id,
                'permisos' => $registro->permisos,
                // Asegúrate de que 'permisos' sea el nombre correcto del campo
            ];
        });

        return $permisos;
    }

    // UserController.php

    // 


    public function updatePermissions(Request $request)
    {
        $datosVista = $request->input('datos_vista');

        $active = $datosVista['active'];
        $viewName = $datosVista['view_name'];
        $idRol = $datosVista['id_rol'];

        // Obtener todos los usuarios que tienen el rol específico
        $users = UpUser::whereHas('roles_fronts', function ($query) use ($idRol) {
            $query->where('roles_fronts.id', $idRol);
        })->get();

        // Actualizar los permisos de los usuarios y roles_fronts
        foreach ($users as $user) {
            $permissions = json_decode($user->permisos, true) ?? [];

            // Agregar o quitar el valor de 'view_name' según 'active'
            if ($active) {
                // Agregar el valor solo si 'active' es true y no existe
                if (!in_array($viewName, $permissions)) {
                    $permissions[] = $viewName;
                }
            } else {
                // Quitar el valor solo si 'active' es false y existe
                $permissions = array_diff($permissions, [$viewName]);
            }

            // Actualizar los permisos en la tabla up_users
            $user->update(['permisos' => json_encode(array_values($permissions))]);

            // Actualizar la columna accesos en la tabla roles_fronts
            $role = $user->roles_fronts->where('id', $idRol)->first();
            if ($role) {
                $accessos = json_decode($role->accesos, true);

                // Buscar la opción con 'view_name' igual a $viewName y actualizar 'active'
                foreach ($accessos as &$option) {
                    if ($option['view_name'] === $viewName) {
                        $option['active'] = $active;
                    }
                }

                $role->update(['accesos' => json_encode($accessos)]);
            }
        }

        return response()->json(['message' => 'Permisos actualizados con éxito'], 200);
    }

    public function newPermission(Request $request)
    {
        $datosVista = $request->input('datos_vista');
        $active = $datosVista['active'];
        $viewName = $datosVista['view_name'];
        $idRol = $datosVista['id_rol'];

        // Paso 1: Obtener el modelo RolesFront
        $role = RolesFront::find($idRol);

        if ($role) {
            // Paso 2: Obtener el valor actual del campo accesos y convertirlo a un array
            $accesosArray = json_decode($role->accesos, true) ?: [];

            // Paso 3: Actualizar el array con los nuevos valores proporcionados
            $nuevoAcceso = ['active' => $active, 'view_name' => $viewName];
            $accesosArray[] = $nuevoAcceso; // Añadir el nuevo acceso al final del array

            // Paso 4: Convertir el array actualizado a formato JSON
            $nuevoValorAccesos = json_encode($accesosArray);

            // Paso 5: Actualizar el campo accesos en la base de datos
            $role->update(['accesos' => $nuevoValorAccesos]);

            // Puedes devolver una respuesta exitosa si es necesario
            return response()->json(['message' => 'Acceso actualizado correctamente']);
        } else {
            // Devolver una respuesta en caso de que no se encuentre el modelo
            return response()->json(['error' => 'Rol no encontrado'], 404);
        }
    }

    public function deletePermissions(Request $request)
    {
        $datosVista = $request->input('datos_vista');

        $viewName = $datosVista['view_name'];
        $idRol = $datosVista['id_rol'];

        // Obtener todos los usuarios que tienen el rol específico
        $users = UpUser::whereHas('roles_fronts', function ($query) use ($idRol) {
            $query->where('roles_fronts.id', $idRol);
        })->get();

        foreach ($users as $user) {
            $permissions = json_decode($user->permisos, true) ?? [];

            // Eliminar el valor de 'view_name'
            $permissions = array_diff($permissions, [$viewName]);

            // Actualizar los permisos en la tabla up_users
            $user->update(['permisos' => json_encode(array_values($permissions))]);
        }

        foreach ($users as $user) {

            // Actualizar la columna accesos en la tabla roles_fronts
            $role = $user->roles_fronts->where('id', $idRol)->first();
            if ($role) {
                $accessos = json_decode($role->accesos, true);

                // Eliminar la opción con 'view_name'
                $accessos = array_filter($accessos, function ($option) use ($viewName) {
                    return $option['view_name'] !== $viewName;
                });

                $role->update(['accesos' => json_encode(array_values($accessos))]);
            }
        }
        return response()->json(['message' => 'Permisos eliminados en roles y en cada usuario con éxito'], 200);
    }

    public function handleCallback(Request $request)
    {
        $code = $request->input('code'); // Captura el código de autorización
        error_log($code);
        // Procesa el código aquí (intercambio por access token, etc.)
        // ...

        return response()->json(['codigo' => $code], 200);
    }

    public function getOperatorsTransportLaravel(Request $request)
    {
        $data = $request->json()->all();
        $filtersOr = $data['or'];
        $Map = $data['and'];
        $searchValue = $data['searchValue'];

        $users = UpUser::with(['operadores.sub_rutas.rutas', 'rolesFronts', 'operadores.transportadoras'])
            ->whereHas('rolesFronts', function ($query) {
                $query->where('titulo', 'OPERADOR');
            })
            ->whereHas('operadores.sub_rutas.rutas', function ($query) {
                $query->where('active', 1);
            })
            ->whereHas('operadores.transportadoras', function ($query) {
                $query->where('active', 1);
            })
            ->where(function ($users) use ($searchValue, $filtersOr) {
                foreach ($filtersOr as $field) {
                    if (strpos($field, '.') !== false) {
                        // Si es un campo anidado
                        $relations = explode('.', $field);
                        $property = array_pop($relations);

                        $users->orWhereHas(implode('.', $relations), function ($q) use ($property, $searchValue) {
                            $q->where($property, 'LIKE', '%' . $searchValue . '%');
                        });
                    } else {
                        // Si no es un campo anidado
                        $users->orWhere($field, 'LIKE', '%' . $searchValue . '%');
                    }
                }
            })

            ->where((function ($users) use ($Map) {
                foreach ($Map as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($users, $relacion, $propiedad, $valor);
                        } else {
                            $users->where($key, '=', $valor);
                        }
                    }
                }
            }));


        $users = $users->get();

        return response()->json([
            'data' => $users,
            'total' => $users->count()
        ], 200);
    }

    private function recursiveWhereHas($query, $relation, $property, $searchTerm)
    {
        if ($searchTerm == "null") {
            $searchTerm = null;
        }
        if (strpos($property, '.') !== false) {

            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm) {
                $this->recursiveWhereHas($q, $nestedRelation, $nestedProperty, $searchTerm);
            });
        } else {
            $query->whereHas($relation, function ($q) use ($property, $searchTerm) {
                $q->where($property, '=', $searchTerm);
            });
        }
    }


    public function userByEmail(Request $request)
    {
        $data = $request->json()->all();

        $email = $data['email'];

        // Utiliza first() para obtener un solo resultado
        $upUser = UpUser::with([
            'roles_fronts',
            'vendedores',
            'transportadora',
            'operadores',
            'providers',
        ])->where('email', 'like', '%' . $email . '%')->first();

        // Verifica si el usuario no se encuentra
        if (!$upUser) {
            return response()->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Utiliza compact() para enviar la respuesta
        return response()->json(['user' => $upUser], Response::HTTP_OK);
    }

    public function updateTransport(Request $request, $id)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255',
                'email' => 'required|email|unique:up_users,email,' . $id,

            ]);

            $user = UpUser::find($id);

            if ($user) {
                $user->username = $request->input('username');
                $user->email = $request->input('email');
                $user->save();

                $transport = $user->transportadora()->first();
                if ($transport) {
                    $transport->nombre = $request->input('username');
                    $transport->costo_transportadora = $request->input('costo_transportadora');
                    $transport->telefono_1 = $request->input('telefono_1');
                    $transport->telefono_2 = $request->input('telefono_2');

                    $transport->save();

                    $rutaIds = $request->input('rutas'); // Asume que 'ruta' es un array de IDs
                    if (is_array($rutaIds)) {
                        $transport->rutas()->sync($rutaIds);
                    }
                }
            }
            // else {
            // return response()->json(['message' => 'Usuario no encontrado'], 404);
            return response()->json(['message' => 'Usuario actualizado con éxito'], 200);

            // }
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error !'], 404);
        }
    }


    public function updateSeller(Request $request, $id)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255',
                'email' => 'required|email|unique:up_users,email,' . $id,

            ]);

            $user = UpUser::find($id);

            if ($user) {
                $user->username = $request->input('username');
                $user->email = $request->input('email');
                $user->save();

                $seller = $user->vendedores()->first();
                if ($seller) {
                    $seller->nombre_comercial = $request->input('nombre_comercial');
                    $seller->telefono_1 = $request->input('telefono_1');
                    $seller->telefono_2 = $request->input('telefono_2');
                    $seller->costo_envio = $request->input('costo_envio');
                    $seller->costo_devolucion = $request->input('costo_devolucion');
                    $seller->url_tienda = $request->input('url_tienda');
                    $seller->save();
                }
            }
            // else {
            // return response()->json(['message' => 'Usuario no encontrado'], 404);
            return response()->json(['message' => 'Usuario actualizado con éxito'], 200);

            // }
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error !'], 404);
        }
    }

    public function updateLogisticUser(Request $request, $id)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255',
                'email' => 'required|email|unique:up_users,email,' . $id,

            ]);

            $user = UpUser::find($id);

            if ($user) {
                $user->username = $request->input('username');
                $user->email = $request->input('email');
                $user->persona_cargo = $request->input('persona_cargo');
                $user->telefono_1 = $request->input('telefono_1');
                $user->telefono_2 = $request->input('telefono_2');
                $user->save();
            }
            return response()->json(['message' => 'Usuario actualizado con éxito'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error !'], 404);
        }
    }


    public function storeGeneralNewUser(Request $request)
    {
        error_log("storeGeneralNewUser");

        try {
            $request->validate([
                'username' => 'required|string|max:255',
                'email' => 'required|email|unique:up_users',
            ]);

            // generación del código

            $numerosUtilizados = [];
            while (count($numerosUtilizados) < 10000000) {
                $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
                if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                    $numerosUtilizados[] = $numeroAleatorio;
                    break;
                }
            }
            $resultCode = $numeroAleatorio;

            //  creación del usuario
            DB::beginTransaction();
            $user = new UpUser();
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->codigo_generado = $resultCode;
            $user->password = bcrypt($request->input('password'));
            $user->confirmed = $request->input('confirmed');
            $user->estado = "NO VALIDADO";
            $user->provider = "local";
            $user->blocked = "0";
            $user->confirmed = 1;
            // $user->fecha_alta = $request->input('fecha_alta');
            $user->fecha_alta = date("d/m/Y");
            $permisosCadena = json_encode($request->input('PERMISOS'));
            $user->permisos = $permisosCadena;
            $user->company_id = $request->input('company_id');
            $user->save();

            $newUpUsersRoleLink = new UpUsersRoleLink();
            $newUpUsersRoleLink->user_id = $user->id;
            $newUpUsersRoleLink->role_id = 1;
            $newUpUsersRoleLink->save();

            $userRoleFront = new UpUsersRolesFrontLink();
            $userRoleFront->user_id = $user->id;
            $userRoleFront->roles_front_id = $request->input('roles_front');
            $userRoleFront->save();

            // 1	LOGISTICA
            // 2	VENDEDOR
            // 3	TRANSPORTADOR
            // 4	OPERADOR
            // 5	PROVEEDOR

            $typeU = $request->input('userType');

            if ($typeU == "3") {
                if ($request->has(['nombre_transportadora', 'telefono1', 'telefono2', 'costo_transportadora', 'rutas'])) {
                    $transport = new Transportadora();
                    $transport->nombre = $request->input('nombre_transportadora');
                    $transport->telefono_1 = $request->input('telefono1');
                    $transport->telefono_2 = $request->input('telefono2');
                    $transport->costo_transportadora = $request->input('costo_transportadora');
                    $transport->company_id = $request->input('company_id');
                    $transport->save();

                    $transportadoraUserPermissionsUserLinks = new TransportadorasUsersPermissionsUserLink();
                    $transportadoraUserPermissionsUserLinks->transportadora_id = $transport->id;
                    $transportadoraUserPermissionsUserLinks->user_id = $user->id;
                    $transportadoraUserPermissionsUserLinks->save();

                    $rutasIds = $request->input('rutas');
                    foreach ($rutasIds as $rutaId) {
                        $transportadoraRutaLinks = new TransportadorasRutasLink();
                        $transportadoraRutaLinks->transportadora_id = $transport->id;
                        $transportadoraRutaLinks->ruta_id = $rutaId;
                        $transportadoraRutaLinks->save();
                    }
                    // $user->transportadora()->attach($user->id, [],$transport->id,);
                }
                Mail::to($user->email)->send(new UserValidation($resultCode));
                //     return response()->json(['message' => 'Usuario creado con éxito', 'user_id' => $user->id], 200);


            } else
                if ($typeU == "2") {
                if ($request->has(['nombre_comercial', 'telefono1', 'telefono2', 'costo_envio', 'costo_devolucion', 'url_tienda'])) {
                    $newSeller = new Vendedore();
                    $newSeller->nombre_comercial = $request->input('nombre_comercial');
                    $newSeller->telefono_1 = $request->input('telefono1');
                    $newSeller->telefono_2 = $request->input('telefono2');
                    $newSeller->costo_envio = $request->input('costo_envio');
                    $newSeller->costo_devolucion = $request->input('costo_devolucion');
                    $newSeller->fecha_alta = $request->input('fecha_alta');
                    $newSeller->id_master = $user->id;
                    $newSeller->url_tienda = $request->input('url_tienda');
                    $newSeller->referer_cost = "0.10";
                    $newSeller->saldo = 0;
                    $newSeller->company_id = $request->input('company_id');
                    $newSeller->save();

                    $upUserVendedoreLinks = new UpUsersVendedoresLink();
                    $upUserVendedoreLinks->user_id = $user->id;
                    $upUserVendedoreLinks->vendedor_id = $newSeller->id;
                    $upUserVendedoreLinks->save();
                }
                Mail::to($user->email)->send(new UserValidation($resultCode));
            } elseif ($typeU == "4") {
                // "operatorName","phone","operatorCost","idCarrier" ,"idSubRoute"
                $operator = new Operadore();
                $operator->telefono = $request->input('phone');
                $operator->costo_operador = $request->input('operatorCost');
                $operator->save();

                $upUserOperador = new UpUsersOperadoreLink();
                $upUserOperador->user_id = $user->id;
                $upUserOperador->operadore_id = $operator->id;
                $upUserOperador->save();

                $OperadoresTransporta = new OperadoresTransportadoraLink();
                $OperadoresTransporta->operadore_id = $operator->id;
                $OperadoresTransporta->transportadora_id = $request->input('idCarrier');
                $OperadoresTransporta->save();

                $OperadoresSubRuta = new OperadoresSubRutaLink();
                $OperadoresSubRuta->operadore_id = $operator->id;
                $OperadoresSubRuta->sub_ruta_id = $request->input('idSubRoute');
                $OperadoresSubRuta->save();
                Mail::to($user->email)->send(new UserValidation($resultCode));
            } else if ($typeU == "1") {
                if ($request->has(['telefono1', 'telefono2', 'persona_cargo'])) {
                    $user->persona_cargo = $request->input('persona_cargo');
                    $user->telefono_1 = $request->input('telefono1');
                    $user->telefono_2 = $request->input('telefono2');
                    $user->save();
                }
                Mail::to($user->email)->send(new UserValidation($resultCode));
            }

            DB::commit();
            return response()->json(['message' => 'Usuario creado con éxito', 'user_id' => $user->id], 200);
        } catch (\Throwable $th) {
            // Log the error
            DB::rollBack();
            return response()->json(['message' => 'Error! ' . $th->getMessage()], 400);
        }
    }

    public function updateUserPassword(Request $request, $id)
    {
        try {

            $data = $request->json()->all();
            $newPassword = $data['password'];

            $user = UpUser::find($id);

            if ($user) {
                $user->password = bcrypt($newPassword);
                $user->save();

                return response()->json(["message" => "Actualización de contraseña exitosa"], 200);
            }
            return response()->json(["message" => 'No se encontro el Usuario'], 404);
        } catch (\Throwable $th) {
            return response()->json(["message" => 'No se pudo ejecutar la actualización de contraseña.'], 404);
        }
    }

    public function updateOperator(Request $request, $id)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255',
                'email' => 'required|email|unique:up_users,email,' . $id,

            ]);

            $user = UpUser::find($id);

            if ($user) {
                $user->username = $request->input('username');
                $user->email = $request->input('email');
                $user->save();

                $operador = Operadore::find($request->input('idOper'));
                $operador->costo_operador = $request->input('cost');
                $operador->telefono = $request->input('phone');
                $operador->save();

                $OperadorSubRuta = OperadoresSubRutaLink::where('operadore_id', $request->input('idOper'))->first();
                $OperadorSubRuta->sub_ruta_id = $request->input('idSubRoute');
                $OperadorSubRuta->save();
            }
            return response()->json(['message' => 'Usuario actualizado con éxito'], 200);

            // }
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error !'], 404);
        }
    }

    public function updateUserActiveStatus(Request $request, $id)
    {
        try {
            // Obtener los datos enviados desde el frontend
            $data = $request->json()->all();

            // Buscar al usuario por su ID
            $user = UpUser::find($id);

            // Verificar si se encontró al usuario
            if ($user) {
                // Actualizar el campo active con el valor enviado desde el frontend
                $user->active = $data['active'];
                $user->save();

                // Retornar una respuesta exitosa
                return response()->json(["message" => "Actualización de estado de activo exitosa"], 200);
            }

            // Si no se encuentra al usuario, retornar un mensaje de error
            return response()->json(["message" => 'No se encontró el usuario'], 404);
        } catch (\Throwable $th) {
            // En caso de error, retornar un mensaje de error
            return response()->json(["message" => 'No se pudo ejecutar la actualización de estado de activo.'], 404);
        }
    }

    public function updateUserActiveStatusTransport(Request $request, $id)
    {
        try {
            // Obtener los datos enviados desde el frontend
            $data = $request->json()->all();

            // Buscar al usuario por su ID
            $user = UpUser::find($id);

            // Verificar si se encontró al usuario
            if ($user) {
                // Actualizar el campo active con el valor enviado desde el frontend
                $user->active = $data['active'];
                $user->save();

                $carrier = Transportadora::find($data['id_carrier']);
                $carrier->active = $data['active'];
                $carrier->save();

                // Retornar una respuesta exitosa
                return response()->json(["message" => "Actualización de estado de activo exitosa"], 200);
            }

            // Si no se encuentra al usuario, retornar un mensaje de error
            return response()->json(["message" => 'No se encontró el usuario'], 404);
        } catch (\Throwable $th) {
            // En caso de error, retornar un mensaje de error
            return response()->json(["message" => 'No se pudo ejecutar la actualización de estado de activo.'], 404);
        }
    }

    public function allReferers(Request $request)
    {
        try {
            $data = $request->json()->all();
            $vendedores = Vendedore::where('referer', $data['id'])->with('up_users')->get();
            return response()->json($vendedores, 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([], 404);
        }
    }

    public function storeUserWP(Request $request)
    {

        error_log("storeUserWP");

        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            error_log("Unauthorized-No credentials provided. Please provide your username and password.");
            return response()->json(['status' => 'Unauthorized', "message" => "No credentials provided. Please provide your username and password."], 401);
        }

        // Verificar las credenciales de autenticación
        $user = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        $access = true;

        if ($user !== 'easywp' || $password !== '$2y$10$d48FVm5dgJPhBOkRFOrDdeOo3fMcBH8fshiRp8GsGEcqDODIE5mXe') {
            error_log("Credenciales de autenticación inválidas.");
            return response()->json(['status' => 'Unauthorized', "message" => "Invalid credentials provided. Please try again."], 401);
        }

        DB::beginTransaction();
        try {

            $messages = [
                'username.required' => 'El nombre de usuario es obligatorio.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El correo electrónico debe ser válido.',
                'email.unique' => 'El correo electrónico ya está registrado en nuestro sistema.',
            ];

            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255',
                'email' => 'required|email|unique:up_users',
            ], $messages);

            if ($validator->fails()) {
                // return response()->json(['storeSellerWPError al crear Usuario' => $validator->errors()->all()], 422);
                error_log("storeUserWPError email existente: " . $request->input('email'));
                return response()->json(['storeUserWPError: email existente'], 422);
            }

            // $request->validate([
            //     'username' => 'required|string|max:255',
            //     'email' => 'required|email|unique:up_users',
            // ]);

            $numerosUtilizados = [];
            while (count($numerosUtilizados) < 10000000) {
                $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
                if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                    $numerosUtilizados[] = $numeroAleatorio;
                    break;
                }
            }
            $resultCode = $numeroAleatorio;

            $typeU = $request->input('userType');

            $rol = RolesFront::where("id", "=", $typeU)->first();

            $activeViewsNames = [];

            if ($rol) {
                $accesos = json_decode($rol->accesos, true);
                // Filtrar los que tienen active = true y obtener solo los view_name
                foreach ($accesos as $acceso) {
                    if (isset($acceso['active']) && $acceso['active'] === true) {
                        $activeViewsNames[] = $acceso['view_name'];
                    }
                }
            }

            $permisosCadena = json_encode($activeViewsNames);

            //  creación del usuario
            $user = new UpUser();
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            $user->codigo_generado = $resultCode;
            error_log("pass:" . $request->input('password'));
            $user->password = bcrypt($request->input('password'));
            $user->confirmed = true;
            $user->estado = "NO VALIDADO";
            $user->provider = "local";
            $user->blocked = "0";
            $user->confirmed = 1;
            $user->fecha_alta = date("d/m/Y");
            $user->permisos = $permisosCadena;
            $user->company_id = 1;
            $user->save();

            $newUpUsersRoleLink = new UpUsersRoleLink();
            $newUpUsersRoleLink->user_id = $user->id;
            $newUpUsersRoleLink->role_id = 1;
            $newUpUsersRoleLink->save();

            $userRoleFront = new UpUsersRolesFrontLink();
            $userRoleFront->user_id = $user->id;
            $userRoleFront->roles_front_id = $typeU;
            $userRoleFront->save();

            // 2	VENDEDOR
            // 5	PROVEEDOR

            if ($typeU == "2") {

                $newSeller = new Vendedore();
                $newSeller->nombre_comercial = $request->input('nombre_comercial');
                $newSeller->telefono_1 = $request->input('telefono1');
                $newSeller->telefono_2 = $request->input('telefono2');
                $newSeller->costo_envio = "6.00";
                $newSeller->costo_devolucion = "6.00";
                $newSeller->fecha_alta = date("d/m/Y");
                $newSeller->id_master = $user->id;
                $newSeller->url_tienda = $request->input('url_tienda');
                $newSeller->referer_cost = "0.10";
                $newSeller->saldo = 0;
                $newSeller->company_id = 1;
                $newSeller->save();

                $upUserVendedoreLinks = new UpUsersVendedoresLink();
                $upUserVendedoreLinks->user_id = $user->id;
                $upUserVendedoreLinks->vendedor_id = $newSeller->id;
                $upUserVendedoreLinks->save();

                if ($newSeller) {
                    try {
                        Mail::to($user->email)->send(new UserValidation($resultCode));
                    } catch (\Exception $e) {
                        error_log("storeSellerWPError al enviar email con el newSeller-resultCode  $user->id: $e");
                    }
                    DB::commit();

                    return response()->json(['message' => 'UserSeller from wp creado con éxito', 'user_id' => $user->id], 200);
                } else {
                    DB::rollback();
                    error_log("storeSellerWPError al crear Usuario");
                    return response()->json(['message' => 'storeSellerWPError al crear Usuario'], 404);
                }
            } else if ($typeU == "5") {
                //
                $provider = new Provider();
                $provider->name = $request->input('provider_name');
                $provider->phone = $request->input('provider_phone');
                $provider->description = $request->input('description');
                // $provider->special = $request->input('special');
                $provider->user_id = $user->id;
                $provider->saldo = 0;
                $provider->company_id = 1;

                $provider->save();
                $user->providers()->attach($provider->id, []);

                if ($provider) {
                    DB::commit();

                    return response()->json(['message' => 'UserProvider from wp creado con éxito', 'user_id' => $user->id], 200);
                } else {
                    DB::rollback();
                    error_log("storeProviderWPError al crear Usuario");
                    return response()->json(['message' => 'storeProviderWPError al crear Usuario'], 404);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            error_log("Error storeUserWP: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function newCode($id)
    {
        error_log("newCode");
        try {
            $numerosUtilizados = [];
            while (count($numerosUtilizados) < 10000000) {
                $numeroAleatorio = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
                if (!in_array($numeroAleatorio, $numerosUtilizados)) {
                    $numerosUtilizados[] = $numeroAleatorio;
                    break;
                }
            }
            $resultCode = $numeroAleatorio;

            $user = UpUser::find($id);
            $user->codigo_generado = $resultCode;
            $user->save();

            Mail::to($user->email)->send(new UserValidation($resultCode));

            return response()->json(['new_code' => $resultCode], 201);
        } catch (\Exception $e) {
            DB::rollback();
            error_log("newCode_error: $e");
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
