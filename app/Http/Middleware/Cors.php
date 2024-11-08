<?php
namespace App\Http\Middleware;

use Closure;

class Cors
{
    public function handle($request, Closure $next)
    {
        // Si es una solicitud OPTIONS, devolver la respuesta directamente
        if ($request->getMethod() == "OPTIONS") {
            return response()->make('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'X-Requested-With, Content-Type, X-Token-Auth, Authorization',
            ]);
        }
    
        $response = $next($request);
    
        // Asegura que las cabeceras se agreguen a la respuesta para los métodos restantes
        if (method_exists($response, 'headers')) {
            $response->headers->set("Access-Control-Allow-Origin", "*");
            $response->headers->set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
            $response->headers->set("Access-Control-Allow-Headers", "X-Requested-With, Content-Type, X-Token-Auth, Authorization");
        }
    
        return $response;
    }

    
}
