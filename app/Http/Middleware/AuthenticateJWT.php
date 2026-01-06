<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 CAMADA 3 - Autenticação (Isolada)
 * 
 * Responsabilidade ÚNICA: Validar JWT e definir usuário no guard
 * 
 * ✅ Faz:
 * - Lê token do header
 * - Valida assinatura JWT
 * - Valida exp/nbf
 * - Resolve User do banco
 * - Define auth()->setUser($user)
 * 
 * ❌ NUNCA faz:
 * - Tenant (outro middleware)
 * - Empresa (outro middleware)
 * - Admin (outro middleware)
 * - Subscription (outro middleware)
 * 
 * 🎯 Princípio: JWT não sabe o que é empresa/tenant
 */
class AuthenticateJWT
{
    public function __construct(
        private JWTService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Log::info('AuthenticateJWT::handle - ✅ INÍCIO', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        // 1. Obter token do header Authorization
        $token = $request->bearerToken();

        if (!$token) {
            Log::warning('AuthenticateJWT::handle - Token ausente');
            return response()->json([
                'message' => 'Token de autenticação ausente. Faça login para continuar.',
            ], 401);
        }

        try {
            // 2. Validar e decodificar token JWT
            Log::debug('AuthenticateJWT::handle - Validando token JWT');
            $payload = $this->jwtService->validateToken($token);
            
            // 3. Injetar payload no request (para outros middlewares)
            $request->attributes->set('auth', $payload);
            $request->attributes->set('user_id', $payload['sub'] ?? null);
            $request->attributes->set('tenant_id', $payload['tenant_id'] ?? null);
            $request->attributes->set('empresa_id', $payload['empresa_id'] ?? null);
            $request->attributes->set('is_admin', $payload['is_admin'] ?? false);
            
            // 4. Resolver e definir usuário no guard
            $user = $this->resolveUser($payload);
            
            if (!$user) {
                Log::warning('AuthenticateJWT::handle - Usuário não encontrado', [
                    'user_id' => $payload['sub'] ?? null,
                ]);
                return response()->json([
                    'message' => 'Usuário não encontrado.',
                ], 401);
            }
            
            // 5. Definir usuário no guard
            auth()->guard('sanctum')->setUser($user);
            
            Log::info('AuthenticateJWT::handle - ✅ Usuário autenticado', [
                'user_id' => $user->id,
                'user_class' => get_class($user),
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('AuthenticateJWT::handle - Erro ao validar token', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'message' => $e->getMessage() ?: 'Token inválido ou expirado.',
            ], 401);
        }
    }

    /**
     * Resolver usuário do banco baseado no payload JWT
     * 
     * 🔥 Responsabilidade única: Buscar User ou AdminUser
     * ❌ NÃO inicializa tenancy (isso é responsabilidade de ResolveTenantContext)
     */
    private function resolveUser(array $payload): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        $userId = $payload['sub'] ?? null;
        $isAdmin = $payload['is_admin'] ?? false;
        
        if (!$userId) {
            return null;
        }

        // Admin: buscar AdminUser (sem tenancy)
        if ($isAdmin) {
            // Garantir que não há tenancy ativo para admin
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            
            $user = \App\Modules\Auth\Models\AdminUser::find($userId);
            if ($user) {
                Log::debug('AuthenticateJWT::resolveUser - AdminUser encontrado', [
                    'user_id' => $user->id,
                ]);
            }
            return $user;
        }

        // Usuário comum: buscar User (tenancy será inicializado por ResolveTenantContext)
        // NÃO inicializar tenancy aqui - isso é responsabilidade de outro middleware
        $user = \App\Modules\Auth\Models\User::find($userId);
        if ($user) {
            Log::debug('AuthenticateJWT::resolveUser - User encontrado', [
                'user_id' => $user->id,
            ]);
        }
        return $user;
    }
}

