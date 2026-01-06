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
 * 🔥 MIDDLEWARE UNIFICADO: Autenticação JWT + Bootstrap
 * 
 * Este middleware consolida toda a lógica de autenticação JWT e inicialização
 * do contexto em um único lugar.
 * 
 * Responsabilidades:
 * 1. Valida token JWT (stateless)
 * 2. Define usuário autenticado no guard
 * 3. Cria identidade de autenticação
 * 4. Inicializa ApplicationContext (tenancy, empresa, etc.)
 * 5. Continua com a requisição
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
        // 🔥 LOG CRÍTICO: Se este log não aparecer, há problema na injeção de dependências
        error_log('AuthenticateAndBootstrap::__construct - CONSTRUTOR EXECUTADO (error_log)');
        Log::emergency('AuthenticateAndBootstrap::__construct - CONSTRUTOR EXECUTADO (EMERGENCY)', [
            'authIdentityService' => get_class($authIdentityService),
            'context' => get_class($context),
            'jwtService' => get_class($jwtService),
        ]);
        
        $this->authIdentityService = $authIdentityService;
        $this->context = $context;
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 🔥 LOG IMEDIATO: Antes de qualquer coisa
        error_log('AuthenticateAndBootstrap::handle - ✅ INÍCIO (error_log) - PRIMEIRO LOG');
        
        try {
            $startTime = microtime(true);
            
            // 🔥 LOG CRÍTICO: Se este log não aparecer, o middleware não está sendo executado
            Log::emergency('AuthenticateAndBootstrap::handle - ✅ INÍCIO (EMERGENCY)', [
                'path' => $request->path(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => $request->route() ? $request->route()->getName() : 'NO_ROUTE',
                'has_jwt_service' => isset($this->jwtService),
                'has_auth_service' => isset($this->authIdentityService),
                'has_context' => isset($this->context),
            ]);
            
            error_log('AuthenticateAndBootstrap::handle - Após Log::emergency');
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
            
            // Definir usuário autenticado no guard baseado no payload JWT
            $this->setAuthenticatedUserFromPayload($payload);
            
            // Verificar se usuário foi definido corretamente no guard
            $user = auth('sanctum')->user();
            if (!$user) {
                Log::warning('AuthenticateAndBootstrap::handle - Usuário não encontrado após autenticação JWT', [
                    'user_id' => $payload['sub'] ?? null,
                    'is_admin' => $payload['is_admin'] ?? false,
                ]);
                return response()->json([
                    'message' => 'Usuário não encontrado.',
                ], 401);
            }
            
            Log::debug('AuthenticateAndBootstrap::handle - Usuário autenticado', [
                'user_id' => $user->id,
                'user_class' => get_class($user),
                'is_admin' => $payload['is_admin'] ?? false,
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
     * 
     * 🔥 JWT STATELESS: Busca usuário diretamente do banco baseado no user_id do token
     */
    private function setAuthenticatedUserFromPayload(array $payload): void
    {
        try {
            $userId = $payload['sub'] ?? null;
            $isAdmin = $payload['is_admin'] ?? false;
            $tenantId = $payload['tenant_id'] ?? null;
            
            if (!$userId) {
                Log::warning('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - user_id ausente no payload');
                return;
            }

            Log::debug('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - Definindo usuário', [
                'user_id' => $userId,
                'is_admin' => $isAdmin,
                'tenant_id' => $tenantId,
            ]);

            // Se for admin, buscar AdminUser (sem tenancy)
            if ($isAdmin) {
                // Garantir que não há tenancy ativo para admin
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                
                $user = \App\Modules\Auth\Models\AdminUser::find($userId);
                if ($user) {
                    auth()->guard('sanctum')->setUser($user);
                    Log::debug('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - AdminUser definido', [
                        'user_id' => $user->id,
                    ]);
                } else {
                    Log::warning('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - AdminUser não encontrado', [
                        'user_id' => $userId,
                    ]);
                }
                return;
            }

            // Se tiver tenant_id, inicializar tenancy primeiro
            if ($tenantId) {
                $tenant = \App\Models\Tenant::find($tenantId);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                    Log::debug('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - Tenancy inicializado', [
                        'tenant_id' => $tenantId,
                    ]);
                } else {
                    Log::warning('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - Tenant não encontrado', [
                        'tenant_id' => $tenantId,
                    ]);
                }
            }

            // Buscar usuário do tenant
            $user = \App\Modules\Auth\Models\User::find($userId);
            if ($user) {
                auth()->guard('sanctum')->setUser($user);
                Log::debug('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - User definido', [
                    'user_id' => $user->id,
                ]);
            } else {
                Log::warning('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - User não encontrado', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('AuthenticateAndBootstrap::setAuthenticatedUserFromPayload - Erro', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Lançar exceção para não continuar com usuário inválido
        }
    }
}

