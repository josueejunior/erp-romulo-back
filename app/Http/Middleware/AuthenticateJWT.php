<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 Middleware JWT Stateless
 * 
 * Valida token JWT e injeta dados do usuário no request.
 * Sem estado, sem sessão, sem Redis - perfeito para escalabilidade.
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

        // Obter token do header Authorization
        $token = $request->bearerToken();

        if (!$token) {
            Log::warning('AuthenticateJWT::handle - Token ausente');
            return response()->json([
                'message' => 'Token de autenticação ausente. Faça login para continuar.',
            ], 401);
        }

        try {
            // Validar e decodificar token
            Log::debug('AuthenticateJWT::handle - Validando token JWT');
            $payload = $this->jwtService->validateToken($token);
            
            // Injetar dados do usuário no request
            $request->attributes->set('auth', $payload);
            $request->attributes->set('user_id', $payload['sub'] ?? null);
            $request->attributes->set('tenant_id', $payload['tenant_id'] ?? null);
            $request->attributes->set('empresa_id', $payload['empresa_id'] ?? null);
            $request->attributes->set('is_admin', $payload['is_admin'] ?? false);
            
            // Definir usuário autenticado no guard (compatibilidade com código legado)
            if (isset($payload['sub'])) {
                // Buscar usuário e definir no guard
                $this->setAuthenticatedUser($request, $payload);
            }
            
            Log::info('AuthenticateJWT::handle - Token válido', [
                'user_id' => $payload['sub'] ?? null,
                'tenant_id' => $payload['tenant_id'] ?? null,
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('AuthenticateJWT::handle - Erro ao validar token', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => $e->getMessage() ?: 'Token inválido ou expirado.',
            ], 401);
        }
    }

    /**
     * Definir usuário autenticado no guard (compatibilidade)
     */
    private function setAuthenticatedUser(Request $request, array $payload): void
    {
        try {
            $userId = $payload['sub'] ?? null;
            $isAdmin = $payload['is_admin'] ?? false;
            
            if (!$userId) {
                return;
            }

            // Se for admin, buscar AdminUser
            if ($isAdmin) {
                $user = \App\Modules\Auth\Models\AdminUser::find($userId);
                if ($user) {
                    auth()->guard('sanctum')->setUser($user);
                }
                return;
            }

            // Se tiver tenant_id, inicializar tenancy primeiro
            if (isset($payload['tenant_id'])) {
                $tenant = \App\Models\Tenant::find($payload['tenant_id']);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            }

            // Buscar usuário do tenant
            $user = \App\Modules\Auth\Models\User::find($userId);
            if ($user) {
                auth()->guard('sanctum')->setUser($user);
            }
        } catch (\Exception $e) {
            Log::warning('AuthenticateJWT::setAuthenticatedUser - Erro ao definir usuário', [
                'error' => $e->getMessage(),
            ]);
            // Não lançar exceção - apenas logar o erro
        }
    }
}

