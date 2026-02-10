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

        // 🔥 Verificar se é rota pública (não requer autenticação)
        if ($this->isPublicRoute($request)) {
            Log::debug('⬅ AuthenticateJWT: rota pública, pulando autenticação', ['path' => $request->path()]);
            return $next($request);
        }

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
     * Verificar se a rota é pública (não requer autenticação)
     */
    private function isPublicRoute(Request $request): bool
    {
        $path = $request->path();
        $method = $request->method();

        // Rotas públicas que não requerem autenticação
        // 🔥 IMPORTANTE: GET /api/v1/planos e GET /api/v1/planos/{id} devem ser públicas
        // para permitir que a tela de cadastro funcione sem autenticação
        
        // Rotas de planos públicas (apenas GET - listagem e detalhe)
        if (preg_match('#^api/v1/planos(/\d+)?$#', $path) && $method === 'GET') {
            return true;
        }

        // Outras rotas públicas
        $publicPaths = [
            'api/v1/auth/login',
            'api/v1/auth/register',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
            'api/v1/cadastro-publico',
            'api/v1/afiliados/cadastro-publico',
            'api/v1/upload/image',
        ];

        foreach ($publicPaths as $publicPath) {
            if ($path === $publicPath || str_starts_with($path, $publicPath . '/')) {
                return true;
            }
        }

        // Rotas de tenants (apenas algumas são públicas)
        if (preg_match('#^api/v1/tenants#', $path) && in_array($method, ['GET', 'POST'])) {
            return true;
        }

        // Rotas de consulta CNPJ
        if (preg_match('#^api/v1/cadastro-publico/consultar-cnpj/#', $path) && $method === 'GET') {
            return true;
        }

        return false;
    }

    /**
     * Resolver usuário do banco baseado no payload JWT
     */
    private function resolveUser(array $payload): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        $userId = $payload['sub'] ?? null;
        $isAdmin = $payload['is_admin'] ?? false;
        $tenantId = $payload['tenant_id'] ?? null;
        
        if (!$userId) {
            return null;
        }

        // Admin: buscar AdminUser (sem tenancy - banco central)
        if ($isAdmin) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            // Garantir que está usando conexão central
            $centralConnectionName = config('tenancy.database.central_connection', 'pgsql');
            if (config('database.default') !== $centralConnectionName) {
                config(['database.default' => $centralConnectionName]);
                \Illuminate\Support\Facades\DB::purge($centralConnectionName);
            }
            return \App\Modules\Auth\Models\AdminUser::find($userId);
        }

        // Usuário comum: buscar User (banco do tenant)
        // 🔥 IMPORTANTE: Inicializar tenancy e trocar conexão antes de buscar usuário
        if ($tenantId) {
            try {
                $tenant = \App\Models\Tenant::find($tenantId);
                if ($tenant) {
                    // Inicializar tenancy se ainda não estiver inicializado
                    if (!tenancy()->initialized) {
                        tenancy()->initialize($tenant);
                    }
                    
                    // 🔥 MULTI-DATABASE: Trocar para o banco do tenant quando a conexão padrão ainda for a central
                    $centralConnectionName = config('tenancy.database.central_connection', 'pgsql');
                    $defaultConnectionName = config('database.default');
                    $tenantDbName = $tenant->database()->getName();
                    if ($defaultConnectionName === $centralConnectionName) {
                        config(['database.connections.tenant.database' => $tenantDbName]);
                        \Illuminate\Support\Facades\DB::purge('tenant');
                        config(['database.default' => 'tenant']);
                        Log::debug('AuthenticateJWT: Conexão trocada para banco do tenant', [
                            'tenant_id' => $tenantId,
                            'tenant_database' => $tenantDbName,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('AuthenticateJWT: Erro ao inicializar tenancy', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
                // Continuar tentando buscar usuário mesmo se tenancy falhar
            }
        }

        // Buscar usuário no banco do tenant
        return \App\Modules\Auth\Models\User::find($userId);
    }
}
