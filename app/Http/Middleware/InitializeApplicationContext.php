<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Contracts\ApplicationContextContract;
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
/**
 * Middleware unificado para inicializar todo o contexto da aplicação
 * 
 * 🔥 REFATORADO: Este middleware agora usa ApplicationContextContract
 * e chama bootstrap() ao invés de initialize().
 * 
 * @deprecated Este middleware está sendo substituído pelos middlewares thin.
 * Considere usar EnsureEmpresaAtivaContext + InitializeTenancyByRequestData
 */
class InitializeApplicationContext
{
    public function __construct(
        private ApplicationContextContract $context
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
        
        // Inicializar ApplicationContext via bootstrap (método principal)
        // O bootstrap() já resolve tenant_id, inicializa tenancy e resolve empresa_id
        // Não precisamos fazer isso manualmente aqui
        $this->context->bootstrap($request);
        
        // Verificar se o bootstrap foi bem-sucedido
        if (!$this->context->isInitialized()) {
            Log::error('InitializeApplicationContext - Bootstrap falhou');
            return response()->json([
                'message' => 'Erro ao inicializar contexto da aplicação.'
            ], 500);
        }
        
        // Obter tenant_id do contexto (já resolvido pelo bootstrap)
        $tenantId = $this->context->getTenantIdOrNull();
        
        if (!$tenantId) {
            Log::error('InitializeApplicationContext - Tenant não identificado após bootstrap');
            return response()->json([
                'message' => 'Tenant não identificado. Envie o header X-Tenant-ID.'
            ], 400);
        }
        
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
