<?php

namespace App\Http\Middleware;

use App\Contracts\IAuthIdentity;
use App\Contracts\ApplicationContextContract;
use App\Services\AuthIdentityService;
use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 MIDDLEWARE UNIFICADO: Autenticação + Bootstrap
 * 
 * Este middleware consolida toda a lógica de autenticação e inicialização
 * do contexto em um único lugar, evitando problemas de travamento entre
 * middlewares separados.
 * 
 * Responsabilidades:
 * 1. Autentica o usuário via Sanctum
 * 2. Cria identidade de autenticação
 * 3. Inicializa ApplicationContext (tenancy, empresa, etc.)
 * 4. Continua com a requisição
 */
class AuthenticateAndBootstrap
{
    protected AuthIdentityService $authIdentityService;
    protected ApplicationContextContract $context;
    protected JWTService $jwtService;

    public function __construct(
        AuthIdentityService $authIdentityService,
        ApplicationContextContract $context,
        JWTService $jwtService
    ) {
        $this->authIdentityService = $authIdentityService;
        $this->context = $context;
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        Log::info('AuthenticateAndBootstrap::handle - ✅ INÍCIO', [
            'path' => $request->path(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);

        try {
            // 🔥 JWT STATELESS: Validar token JWT em vez de Sanctum
            Log::debug('AuthenticateAndBootstrap::handle - Validando token JWT');
            $token = $request->bearerToken();
            
            if (!$token) {
                Log::warning('AuthenticateAndBootstrap::handle - Token ausente');
                return response()->json([
                    'message' => 'Token de autenticação ausente. Faça login para continuar.',
                ], 401);
            }
            
            try {
                $payload = $this->jwtService->validateToken($token);
                Log::debug('AuthenticateAndBootstrap::handle - Token JWT válido', [
                    'user_id' => $payload['sub'] ?? null,
                    'tenant_id' => $payload['tenant_id'] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::warning('AuthenticateAndBootstrap::handle - Token inválido', [
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'message' => 'Token inválido ou expirado. Faça login novamente.',
                ], 401);
            }
            
            // Definir usuário autenticado (compatibilidade com código legado)
            $this->setAuthenticatedUserFromPayload($payload);
            
            $user = auth('sanctum')->user();
            if (!$user) {
                Log::warning('AuthenticateAndBootstrap::handle - Usuário não encontrado após autenticação JWT');
                return response()->json([
                    'message' => 'Usuário não encontrado.',
                ], 401);
            }
            
            Log::debug('AuthenticateAndBootstrap::handle - Usuário autenticado', [
                'user_id' => $user->id,
            ]);

            // 2. Criar identidade de autenticação
            Log::debug('AuthenticateAndBootstrap::handle - Criando identidade de autenticação');
            $identityStartTime = microtime(true);
            $identity = $this->authIdentityService->createFromRequest($request, 'api-v1');
            $identityElapsed = microtime(true) - $identityStartTime;
            Log::debug('AuthenticateAndBootstrap::handle - Identidade criada', [
                'elapsed_time' => round($identityElapsed, 3) . 's',
            ]);
            
            app()->instance(IAuthIdentity::class, $identity);
            $request->scope = 'api-v1';

            // 3. Bootstrap do ApplicationContext (tenancy, empresa, etc.)
            Log::info('AuthenticateAndBootstrap::handle - Iniciando bootstrap do ApplicationContext');
            $bootstrapStartTime = microtime(true);
            try {
                $this->context->bootstrap($request);
                $bootstrapElapsed = microtime(true) - $bootstrapStartTime;
                Log::info('AuthenticateAndBootstrap::handle - Bootstrap concluído', [
                    'elapsed_time' => round($bootstrapElapsed, 3) . 's',
                ]);
            } catch (\Exception $e) {
                Log::error('AuthenticateAndBootstrap::handle - Erro no bootstrap', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            // 4. Continuar com a requisição
            Log::debug('AuthenticateAndBootstrap::handle - Chamando $next($request)');
            $nextStartTime = microtime(true);
            $response = $next($request);
            $nextElapsed = microtime(true) - $nextStartTime;
            
            $totalElapsed = microtime(true) - $startTime;
            
            Log::info('AuthenticateAndBootstrap::handle - ✅ FIM', [
                'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'next_elapsed_time' => round($nextElapsed, 3) . 's',
                'total_elapsed_time' => round($totalElapsed, 3) . 's',
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('AuthenticateAndBootstrap::handle - ❌ ERRO', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Definir usuário autenticado no guard baseado no payload JWT
     */
    private function setAuthenticatedUserFromPayload(array $payload): void
    {
        try {
            $userId = $payload['sub'] ?? null;
            $isAdmin = $payload['is_admin'] ?? false;
            $tenantId = $payload['tenant_id'] ?? null;
            
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
            if ($tenantId) {
                $tenant = \App\Models\Tenant::find($tenantId);
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
            Log::warning('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - Erro', [
                'error' => $e->getMessage(),
            ]);
            // Não lançar exceção - apenas logar
        }
    }
}

