<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Middleware de autenticação opcional
 * Tenta autenticar o usuário se houver token, mas não bloqueia se não houver
 */
class OptionalAuthenticate
{
    public function __construct(
        private JWTService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token) {
            try {
                $payload = $this->jwtService->validateToken($token);
                
                // Injetar payload no request
                $request->attributes->set('auth', $payload);
                $request->attributes->set('user_id', $payload['sub'] ?? null);
                $request->attributes->set('tenant_id', $payload['tenant_id'] ?? null);
                $request->attributes->set('empresa_id', $payload['empresa_id'] ?? null);
                $request->attributes->set('is_admin', $payload['is_admin'] ?? false);
                
                // Resolver e definir usuário no guard
                $user = $this->resolveUser($payload);
                
                if ($user) {
                    auth()->setUser($user);
                    auth()->guard('sanctum')->setUser($user);
                    $request->setUserResolver(fn () => $user);
                    Log::debug('OptionalAuthenticate: usuário autenticado', ['user_id' => $user->id]);
                }
            } catch (\Exception $e) {
                // Token inválido - continuar sem autenticação
                Log::debug('OptionalAuthenticate: token inválido, continuando sem autenticação', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $next($request);
    }

    private function resolveUser(array $payload): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        $userId = $payload['sub'] ?? null;
        $isAdmin = $payload['is_admin'] ?? false;
        $tenantId = $payload['tenant_id'] ?? null;
        
        if (!$userId) {
            return null;
        }

        if ($isAdmin) {
            // Admin não precisa de tenancy - garantir que não há tenancy ativo
            if (Tenancy::initialized()) {
                Tenancy::end();
            }
            return \App\Modules\Auth\Models\AdminUser::find($userId);
        }

        // Para usuário comum, tentar inicializar tenancy se tivermos tenant_id
        // Isso é necessário porque o modelo User pode estar no banco do tenant
        if ($tenantId && !Tenancy::initialized()) {
            try {
                $tenant = \App\Models\Tenant::find($tenantId);
                if ($tenant) {
                    Tenancy::initialize($tenant);
                }
            } catch (\Exception $e) {
                Log::debug('OptionalAuthenticate: erro ao inicializar tenancy', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
                // Continuar sem tenancy - pode funcionar dependendo da configuração
            }
        }

        try {
            return \App\Modules\Auth\Models\User::find($userId);
        } catch (\Exception $e) {
            Log::debug('OptionalAuthenticate: erro ao buscar usuário', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

