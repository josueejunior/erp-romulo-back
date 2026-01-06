<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Auth\Models\AdminUser;

/**
 * Middleware para garantir que o usuário autenticado é um admin
 * Validação de segurança no backend - nunca confiar no frontend
 */
class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 🔥 IMPORTANTE: Usar guard 'sanctum' explicitamente (mesmo guard usado por AuthenticateJWT)
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Não autenticado. Faça login para continuar.',
                'code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Verificar se é AdminUser (não apenas verificar flag)
        if (!($user instanceof AdminUser)) {
            return response()->json([
                'message' => 'Acesso negado. Apenas administradores podem acessar esta área.',
                'code' => 'FORBIDDEN'
            ], 403);
        }

        return $next($request);
    }
}





