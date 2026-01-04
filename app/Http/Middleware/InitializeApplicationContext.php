<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\ApplicationContext;
use App\Domain\Shared\ValueObjects\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware unificado para inicializar todo o contexto da aplicação
 * 
 * Este middleware DEVE rodar APÓS a autenticação (auth:sanctum) e
 * é responsável por:
 * 1. Inicializar o tenant (multi-tenancy)
 * 2. Inicializar o ApplicationContext (empresa_id, user)
 * 3. Sincronizar com TenantContext (para compatibilidade com DDD)
 * 4. Disponibilizar contexto no container
 * 
 * Substitui a lógica fragmentada entre:
 * - InitializeTenancyByRequestData
 * - EnsureEmpresaAtivaContext
 */
class InitializeApplicationContext
{
    public function __construct(
        private ApplicationContext $context
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Extrair dados do request
        $tenantIdFromHeader = $request->header('X-Tenant-ID');
        $empresaIdFromHeader = $request->header('X-Empresa-ID') 
            ? (int) $request->header('X-Empresa-ID') 
            : null;
        
        $user = Auth::user();
        
        Log::debug('InitializeApplicationContext::handle() - INÍCIO', [
            'url' => $request->url(),
            'method' => $request->method(),
            'user_id' => $user?->id,
            'header_X-Tenant-ID' => $tenantIdFromHeader,
            'header_X-Empresa-ID' => $empresaIdFromHeader,
        ]);
        
        // Se não há usuário autenticado, pular (rotas públicas)
        if (!$user) {
            Log::debug('InitializeApplicationContext - Sem usuário, pulando');
            return $next($request);
        }
        
        // Verificar se é admin (não precisa de tenant/empresa)
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            Log::debug('InitializeApplicationContext - Usuário é admin, pulando');
            return $next($request);
        }
        
        // Determinar tenant_id
        $tenantId = $this->resolveTenantId($request, $user, $tenantIdFromHeader);
        
        if (!$tenantId) {
            Log::error('InitializeApplicationContext - Tenant não identificado');
            return response()->json([
                'message' => 'Tenant não identificado. Envie o header X-Tenant-ID.'
            ], 400);
        }
        
        // Inicializar tenancy (se ainda não inicializado)
        if (!tenancy()->tenant || tenancy()->tenant->id !== $tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado.',
                    'tenant_id' => $tenantId
                ], 404);
            }
            
            tenancy()->initialize($tenant);
            
            Log::debug('InitializeApplicationContext - Tenancy inicializado', [
                'tenant_id' => $tenant->id
            ]);
        }
        
        // Inicializar ApplicationContext
        $this->context->initialize($user, $tenantId, $empresaIdFromHeader);
        
        // Sincronizar com TenantContext (compatibilidade DDD)
        $empresaId = $this->context->getEmpresaIdOrNull();
        TenantContext::set($tenantId, $empresaId);
        
        // Disponibilizar no container (compatibilidade com código legado)
        if ($empresaId) {
            app()->instance('current_empresa_id', $empresaId);
            $request->attributes->set('empresa_id', $empresaId);
        }
        
        // Compartilhar contexto de logs
        Log::shareContext([
            'tenant_id' => $tenantId,
            'empresa_id' => $empresaId,
            'user_id' => $user->id,
        ]);
        
        Log::debug('InitializeApplicationContext::handle() - FIM', [
            'tenant_id' => $tenantId,
            'empresa_id' => $empresaId,
            'context_initialized' => $this->context->isInitialized(),
        ]);
        
        return $next($request);
    }
    
    /**
     * Resolver tenant_id das diversas fontes
     */
    private function resolveTenantId(Request $request, $user, ?string $tenantIdFromHeader): ?int
    {
        // Prioridade 1: Header X-Tenant-ID
        if ($tenantIdFromHeader) {
            return (int) $tenantIdFromHeader;
        }
        
        // Prioridade 2: Query parameter tenant_id
        if ($request->has('tenant_id')) {
            return (int) $request->input('tenant_id');
        }
        
        // Prioridade 3: Tenant do token Sanctum
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $abilities = $user->currentAccessToken()->abilities ?? [];
            if (isset($abilities['tenant_id'])) {
                return (int) $abilities['tenant_id'];
            }
        }
        
        // Prioridade 4: empresa_ativa_id do usuário -> buscar tenant da empresa
        if ($user->empresa_ativa_id) {
            $empresa = \App\Models\Empresa::find($user->empresa_ativa_id);
            if ($empresa && isset($empresa->tenant_id)) {
                return $empresa->tenant_id;
            }
        }
        
        // Prioridade 5: Primeira empresa do usuário -> buscar tenant
        $empresa = $user->empresas()->first();
        if ($empresa && isset($empresa->tenant_id)) {
            return $empresa->tenant_id;
        }
        
        return null;
    }
}
