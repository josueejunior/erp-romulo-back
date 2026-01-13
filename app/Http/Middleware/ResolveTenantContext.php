<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 CAMADA 5 - Tenancy
 * 
 * Responsabilidade ÚNICA: Resolver tenant e inicializar tenancy
 * 
 * ✅ Faz:
 * - Resolve tenant (header / rota / payload JWT)
 * - Inicializa tenancy: tenancy()->initialize($tenant)
 * 
 * ❌ NUNCA faz:
 * - Autenticação (já foi feita por AuthenticateJWT)
 * - Validação de regras de negócio
 * 
 * 🔥 IMPORTANTE: Rotas auth.* são ISENTAS de tenant obrigatório
 */
class ResolveTenantContext
{
    // Sem dependências no construtor para evitar problemas de binding

    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('➡ ResolveTenantContext entrou', ['path' => $request->path()]);

        // 🔥 CRÍTICO: Se não há rota resolvida, pular middleware
        if (!$request->route()) {
            Log::debug('⬅ ResolveTenantContext: sem rota, pulando');
            return $next($request);
        }

        // 🔥 CRÍTICO: Rotas de autenticação NÃO exigem tenant
        // O frontend precisa chamar essas rotas ANTES de saber o tenant
        if ($this->isExemptRoute($request)) {
            Log::debug('⬅ ResolveTenantContext: rota isenta', ['route' => $request->route()->getName()]);
            return $next($request);
        }

        // Verificar se usuário está autenticado
        $user = auth('sanctum')->user();
        
        if (!$user) {
            Log::warning('ResolveTenantContext: Usuário não autenticado');
            return response()->json([
                'message' => 'Não autenticado. Faça login para continuar.',
            ], 401);
        }

        // Se for admin, não precisa de tenant
        if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            Log::debug('⬅ ResolveTenantContext: admin detectado');
            return $next($request);
        }

        // Resolver tenant_id de múltiplas fontes
        $tenantId = $this->resolveTenantId($request);
        
        if (!$tenantId) {
            Log::warning('ResolveTenantContext: Tenant não identificado');
            return response()->json([
                'message' => 'Tenant não identificado. Envie o header X-Tenant-ID.',
            ], 400);
        }

        // Inicializar tenancy
        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            Log::warning('ResolveTenantContext: Tenant não encontrado', ['tenant_id' => $tenantId]);
            return response()->json([
                'message' => 'Tenant não encontrado.',
            ], 404);
        }

        // 🔥 SEGURANÇA: Validar que o usuário pertence ao tenant (prevenir Tenant Hopping)
        if (!$this->validarRelacaoUsuarioTenant($user, $tenantId)) {
            Log::warning('ResolveTenantContext: Tentativa de acesso a tenant não autorizado', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'user_email' => $user->email ?? 'N/A',
            ]);
            return response()->json([
                'message' => 'Acesso não autorizado a este tenant.',
            ], 403);
        }

        tenancy()->initialize($tenant);
        
        Log::debug('⬅ ResolveTenantContext: tenancy inicializado', ['tenant_id' => $tenantId]);

        return $next($request);
    }

    /**
     * Verificar se a rota é isenta de tenant obrigatório
     */
    private function isExemptRoute(Request $request): bool
    {
        $routeName = $request->route()->getName();
        
        // Rotas isentas por nome
        $exemptPatterns = [
            'auth.*',           // Login, logout, refresh, etc
            'login',
            'logout',
            'register',
            'password.*',       // Reset de senha
            'verification.*',   // Verificação de email
        ];

        foreach ($exemptPatterns as $pattern) {
            if ($routeName && fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        // Rotas isentas por path
        $exemptPaths = [
            'api/v1/auth/*',
            'api/auth/*',
            'auth/*',
            'api/v1/onboarding/*',  // 🔥 Onboarding não precisa de validação rigorosa de tenant (pode ter múltiplos tenants)
        ];

        $path = $request->path();
        foreach ($exemptPaths as $exemptPath) {
            if (fnmatch($exemptPath, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolver tenant_id de múltiplas fontes (prioridade)
     */
    private function resolveTenantId(Request $request): ?int
    {
        // Prioridade 1: Header X-Tenant-ID
        if ($request->header('X-Tenant-ID')) {
            return (int) $request->header('X-Tenant-ID');
        }

        // Prioridade 2: Payload JWT (já injetado por AuthenticateJWT)
        if ($request->attributes->has('auth')) {
            $payload = $request->attributes->get('auth');
            if (isset($payload['tenant_id'])) {
                return (int) $payload['tenant_id'];
            }
        }

        // Prioridade 3: Parâmetro da rota
        if ($request->route('tenant')) {
            $tenant = $request->route('tenant');
            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }
        }

        return null;
    }

    /**
     * 🔥 SEGURANÇA: Validar que o usuário realmente pertence ao tenant
     * 
     * Previne Tenant Hopping: usuário mal-intencionado não pode manipular JWT
     * para acessar dados de outros tenants.
     * 
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @param int $tenantId
     * @return bool
     */
    private function validarRelacaoUsuarioTenant($user, int $tenantId): bool
    {
        // Admin não precisa de validação (tem acesso a todos os tenants)
        if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
            return true;
        }

        // Buscar na users_lookup para validar relação
        try {
            $lookupRepository = app(\App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface::class);
            
            // Buscar todos os registros do usuário por email
            $email = $user->email;
            $lookups = $lookupRepository->buscarAtivosPorEmail($email);
            
            // Verificar se há registro ativo para este tenant_id e user_id
            foreach ($lookups as $lookup) {
                if ($lookup->tenantId === $tenantId && $lookup->userId === $user->id) {
                    // Relação válida encontrada
                    Log::debug('ResolveTenantContext: Relação usuário-tenant validada', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                    ]);
                    return true;
                }
            }
            
            // Se não encontrou na lookup, validar diretamente no banco do tenant
            // (pode ser caso de usuário criado antes da lookup ser populada)
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                tenancy()->initialize($tenant);
                try {
                    $userNoTenant = \App\Modules\Auth\Models\User::find($user->id);
                    $isValid = $userNoTenant !== null && !$userNoTenant->trashed();
                    
                    if ($isValid) {
                        Log::debug('ResolveTenantContext: Relação validada diretamente no tenant', [
                            'user_id' => $user->id,
                            'tenant_id' => $tenantId,
                        ]);
                    }
                    
                    return $isValid;
                } finally {
                    tenancy()->end();
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('ResolveTenantContext: Erro ao validar relação usuário-tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            
            // Em caso de erro, negar acesso por segurança
            return false;
        }
    }
}
