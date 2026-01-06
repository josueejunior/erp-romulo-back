<?php

namespace App\Http\Middleware;

use App\Contracts\ApplicationContextContract;
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
    public function __construct(
        private ApplicationContextContract $context
    ) {}

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
}
