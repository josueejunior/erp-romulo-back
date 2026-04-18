<?php

namespace App\Application\Auth\UseCases;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Use Case: Logout de Usuário
 * 
 * 🔥 JWT STATELESS: JWT não precisa ser deletado (stateless)
 * O frontend apenas remove o token do storage local.
 * Se necessário revogar tokens, implementar blacklist em Redis (opcional).
 */
class LogoutUseCase
{
    /**
     * Executar o caso de uso
     * 
     * Nota: JWT é stateless, então não há token para deletar no servidor.
     * O frontend deve remover o token do storage local.
     */
    public function executar(Authenticatable $user): void
    {
        // 🔥 JWT STATELESS: Não há token para deletar
        // O token JWT é stateless e não é armazenado no servidor.
        // O frontend deve remover o token do localStorage/sessionStorage.
        
        // Se no futuro precisar de revogação de tokens, implementar blacklist:
        // - Redis com TTL igual ao tempo de expiração do token
        // - Verificar blacklist no middleware AuthenticateJWT
        
        \Log::info('LogoutUseCase::executar - Logout realizado', [
            'user_id' => $user->id,
            'note' => 'JWT stateless - token removido apenas no frontend',
        ]);
    }
}





