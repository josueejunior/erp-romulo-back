<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Logout de Admin
 * 
 * 🔥 DDD: Orquestra logout de admin
 * 🔥 JWT STATELESS: JWT não precisa ser deletado (stateless)
 */
final class LogoutAdminUseCase
{
    /**
     * Executar o caso de uso
     * 
     * Nota: JWT é stateless, então não há token para deletar no servidor.
     * O frontend deve remover o token do storage local.
     */
    public function executar(Authenticatable $admin): void
    {
        Log::info('LogoutAdminUseCase::executar - Logout admin realizado', [
            'admin_id' => $admin->id,
            'note' => 'JWT stateless - token removido apenas no frontend',
        ]);

        // 🔥 JWT STATELESS: Não há token para deletar
        // O token JWT é stateless e não é armazenado no servidor.
        // O frontend deve remover o token do localStorage/sessionStorage.
        
        // Se no futuro precisar de revogação de tokens, implementar blacklist:
        // - Redis com TTL igual ao tempo de expiração do token
        // - Verificar blacklist no middleware AuthenticateJWT
    }
}

