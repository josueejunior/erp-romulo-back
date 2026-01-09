<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            // Se for API, retornar JSON. Se for web, redirecionar para /login
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Usuário não autenticado.'], 401);
            }
            return redirect('/login');
        }

        $user = auth()->user();

        // Verificar se o usuário tem a permissão através de um role
        if (!$user->hasPermissionTo($permission)) {
            abort(403, 'Você não tem permissão para acessar esta página.');
        }

        return $next($request);
    }
}
