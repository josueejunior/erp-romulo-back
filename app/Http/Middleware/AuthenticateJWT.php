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
 */
class AuthenticateJWT
{
    public function __construct(
        private JWTService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('➡ AuthenticateJWT entrou', ['path' => $request->path()]);

        // 1. Obter token do header Authorization
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Token de autenticação ausente. Faça login para continuar.',
            ], 401);
        }

        try {
            // 2. Validar e decodificar token JWT
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
                Log::warning('JWT: usuário não encontrado', [
                    'user_id' => $payload['sub'] ?? null,
                ]);
                return response()->json([
                    'message' => 'Usuário não encontrado.',
                ], 401);
            }
            
            // 5. Definir usuário no guard (ambos: padrão e sanctum)
            auth()->setUser($user); // Guard padrão - para $request->user()
            auth()->guard('sanctum')->setUser($user); // Guard sanctum
            
            // 6. Também vincular no request para $request->user() funcionar
            $request->setUserResolver(fn () => $user);

            Log::debug('⬅ AuthenticateJWT: autenticado', ['user_id' => $user->id]);
            return $next($request);

        } catch (\Exception $e) {
            Log::warning('JWT: token inválido', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
            
            return response()->json([
                'message' => $e->getMessage() ?: 'Token inválido ou expirado.',
            ], 401);
        }
    }

    /**
     * Resolver usuário do banco baseado no payload JWT
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
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            return \App\Modules\Auth\Models\AdminUser::find($userId);
        }

        // Usuário comum: buscar User
        return \App\Modules\Auth\Models\User::find($userId);
    }
}
