<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    /**
     * Verifica se o usuário autenticado é um admin central
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Garantir que não há tenancy ativo
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $user = $request->user();
        
        // Verificar se o usuário autenticado é AdminUser
        // O Sanctum retorna o modelo baseado no token, então precisamos verificar a classe
        if (!$user) {
            return response()->json([
                'message' => 'Acesso negado. Faça login para continuar.',
            ], 401);
        }

        // Verificar se é AdminUser verificando o nome da classe ou a tabela
        $userClass = get_class($user);
        if ($userClass !== \App\Models\AdminUser::class && !($user instanceof \App\Models\AdminUser)) {
            return response()->json([
                'message' => 'Acesso negado. Apenas administradores podem acessar esta área.',
            ], 403);
        }

        return $next($request);
    }
}
