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
 * - Bind no container
 * 
 * ❌ NUNCA faz:
 * - Autenticação (já foi feita por AuthenticateJWT)
 * - Validação de regras de negócio
 * - Bootstrap de empresa (isso é ApplicationContext)
 */
class ResolveTenantContext
{
    public function __construct(
        private ApplicationContextContract $context
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Log::info('ResolveTenantContext::handle - ✅ INÍCIO', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        // Verificar se usuário está autenticado
        $user = $request->user();
        
        if (!$user) {
            Log::warning('ResolveTenantContext::handle - Usuário não autenticado');
            return response()->json([
                'message' => 'Não autenticado. Faça login para continuar.',
            ], 401);
        }

        // Se for admin, não precisa de tenant
        if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
            // Garantir que não há tenancy ativo para admin
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            Log::debug('ResolveTenantContext::handle - Admin detectado, pulando tenancy');
            return $next($request);
        }

        // Resolver tenant_id de múltiplas fontes (prioridade)
        $tenantId = $this->resolveTenantId($request);
        
        if (!$tenantId) {
            Log::warning('ResolveTenantContext::handle - Tenant não identificado');
            return response()->json([
                'message' => 'Tenant não identificado. Envie o header X-Tenant-ID.',
            ], 400);
        }

        // Inicializar tenancy
        Log::debug('ResolveTenantContext::handle - Inicializando tenancy', [
            'tenant_id' => $tenantId,
        ]);
        
        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            Log::warning('ResolveTenantContext::handle - Tenant não encontrado', [
                'tenant_id' => $tenantId,
            ]);
            return response()->json([
                'message' => 'Tenant não encontrado.',
            ], 404);
        }

        tenancy()->initialize($tenant);
        
        Log::info('ResolveTenantContext::handle - ✅ Tenancy inicializado', [
            'tenant_id' => $tenantId,
        ]);

        return $next($request);
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

