<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UpUser;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordAPIController extends Controller
{
    public function sendResetEmail(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
            ]);

            $email = $data['email'];

            $user = UpUser::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    "response" => "Correo no encontrado",
                ]);
            }

            $token = Str::random(60);

            // Invalida cualquier token anterior y genera un nuevo token válido
            DB::table('password_resets')
                ->where('email', $email)
                ->update(['is_valid' => 0]);

            DB::table('password_resets')->updateOrInsert(
                ['email' => $email],
                ['token' => $token, 'created_at' => now(), 'is_valid' => 1]
            );

            $resetLink = url("https://app.easyecomerce.com/reset-password/{$token}");

            $subject = 'Restablecimiento de contraseña';
            $messageContent = "Hola, haz clic en el siguiente enlace para restablecer tu contraseña: $resetLink";

            Mail::raw($messageContent, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            return response()->json([
                "response" => "Correo enviado exitosamente",
            ]);
        } catch (\Exception $e) {
            error_log("Error al enviar email: " . $e);

            return response()->json([
                "response" => "Error al enviar email",
                "error" => $e->getMessage()
            ]);
        }
    }


    public function showResetForm($token)
    {
        $reset = DB::table('password_resets')
            ->where('token', $token)
            ->where('is_valid', 1)
            ->first();

        if (!$reset) {
            return response()->json([
                'response' => 'Token inválido o expirado',
            ], 400);
        }

        return response()->json([
            'response' => 'ok',
        ], 201);
    }


    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $reset = DB::table('password_resets')
            ->where('token', $data['token'])
            ->where('is_valid', 1)
            ->first();

        if (!$reset) {
            return response()->json([
                'response' => 'Token inválido o expirado',
            ], 400);
        }

        $user = UpUser::where('email', $reset->email)->first();

        if (!$user) {
            return response()->json([
                'response' => 'Usuario no encontrado',
            ], 404);
        }

        $user->password = bcrypt($data['password']);
        $user->save();

        // Invalida el token para evitar su reutilización
        DB::table('password_resets')->where('token', $data['token'])->update(['is_valid' => 0]);

        return response()->json([
            'response' => 'Contraseña actualizada exitosamente',
        ]);
    }
}