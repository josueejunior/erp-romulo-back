<?php

namespace App\Application\Auth\UseCases;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Use Case: Logout de Usuário
 * Remove o token de autenticação atual
 */
class LogoutUseCase
{
    /**
     * Executar o caso de uso
     */
    public function executar(Authenticatable $user): void
    {
        // Remover token atual (infraestrutura - Sanctum)
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
    }
}

