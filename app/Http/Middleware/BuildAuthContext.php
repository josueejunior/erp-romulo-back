<?php

namespace App\Http\Middleware;

use App\Contracts\IAuthIdentity;
use App\Services\AuthIdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 CAMADA 4 - Identidade / Contexto
 * 
 * Responsabilidade ÚNICA: Criar AuthIdentity e bind no container
 * 
 * ✅ Faz:
 * - Cria AuthIdentity a partir do usuário autenticado
 * - Bind no container: app()->instance(IAuthIdentity::class, $identity)
 * - Define request->scope
 * 
 * ❌ NUNCA faz:
 * - Autenticação (já foi feita por AuthenticateJWT)
 * - Validação de regras de negócio
 * - Tenancy (outro middleware)
 */
class BuildAuthContext
{
    public function __construct(
        private AuthIdentityService $authIdentityService
    ) {}

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $scope = $scope ?? 'api-v1';
        
        Log::info('BuildAuthContext::handle - ✅ INÍCIO', [
            'path' => $request->path(),
            'scope' => $scope,
        ]);

        // Verificar se usuário está autenticado (deve ter sido definido por AuthenticateJWT)
        $user = $request->user();
        
        if (!$user) {
            Log::warning('BuildAuthContext::handle - Usuário não autenticado');
            return response()->json([
                'message' => 'Não autenticado. Faça login para continuar.',
            ], 401);
        }

        // Criar identidade de autenticação
        Log::debug('BuildAuthContext::handle - Criando identidade de autenticação');
        $identity = $this->authIdentityService->createFromRequest($request, $scope);
        
        // Bind no container
        app()->instance(IAuthIdentity::class, $identity);
        $request->scope = $scope;
        
        Log::info('BuildAuthContext::handle - ✅ Identidade criada', [
            'user_id' => $identity->getUserId(),
            'tenant_id' => $identity->getTenantId(),
            'is_admin' => $identity->isAdminCentral(),
        ]);

        return $next($request);
    }
}

