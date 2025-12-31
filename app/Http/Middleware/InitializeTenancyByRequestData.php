<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByRequestData extends IdentificationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se há tenant_id no header ou no request
        $tenantId = $request->header('X-Tenant-ID') 
            ?? $request->input('tenant_id')
            ?? $this->getTenantIdFromToken($request)
            ?? $this->getTenantIdFromUser($request);

        // Verificar se já há tenancy inicializado
        if (tenancy()->initialized) {
            $currentTenant = tenancy()->tenant;
            $currentTenantId = $currentTenant?->id;
            
            // Se o tenant_id do header é diferente do tenant atual, reinicializar
            if ($tenantId && (string)$currentTenantId !== (string)$tenantId) {
                \Log::info('Tenant mudou, reinicializando tenancy', [
                    'tenant_id_atual' => $currentTenantId,
                    'tenant_id_header' => $tenantId,
                    'url' => $request->url()
                ]);
                
                // Finalizar tenant atual
                tenancy()->end();
                
                // Buscar novo tenant e inicializar
                $newTenant = \App\Models\Tenant::find($tenantId);
                if ($newTenant) {
                    tenancy()->initialize($newTenant);
                    
                    // Setar no TenantContext (invisível para o controller)
                    \App\Domain\Shared\ValueObjects\TenantContext::set($newTenant->id);
                    
                    \Log::debug('Tenancy reinicializado com sucesso', [
                        'tenant_id' => $newTenant->id,
                        'tenant_razao_social' => $newTenant->razao_social,
                        'url' => $request->url()
                    ]);
                } else {
                    \Log::warning('Novo tenant não encontrado, mantendo tenant atual', [
                        'tenant_id_procurado' => $tenantId,
                        'tenant_id_atual' => $currentTenantId,
                        'url' => $request->url()
                    ]);
                }
            } else {
                \Log::debug('Tenancy já inicializado com tenant correto', [
                    'tenant_id' => $currentTenantId,
                    'tenant_id_header' => $tenantId,
                    'url' => $request->url()
                ]);
            }
            return $next($request);
        }

        \Log::debug('Tentando inicializar tenancy', [
            'tenant_id_header' => $request->header('X-Tenant-ID'),
            'tenant_id_input' => $request->input('tenant_id'),
            'tenant_id_final' => $tenantId,
            'url' => $request->url()
        ]);

        if (!$tenantId) {
            // Se for admin, não precisa de tenant
            $user = $request->user();
            if ($user && $user instanceof \App\Modules\Auth\Models\AdminUser) {
                \Log::debug('Admin user detectado, pulando inicialização de tenancy', [
                    'user_id' => $user->id,
                    'url' => $request->url()
                ]);
                return $next($request);
            }
            
            \Log::warning('Tenant ID não fornecido', [
                'url' => $request->url(),
                'user_id' => $user?->id,
                'user_type' => $user ? get_class($user) : null,
                'headers' => $request->headers->all()
            ]);
            return response()->json([
                'message' => 'Tenant ID não fornecido. Use o header X-Tenant-ID ou inclua tenant_id no request.',
                'code' => 'TENANT_ID_REQUIRED'
            ], 400);
        }

        // Buscar tenant no banco central
        // O modelo Tenant usa a conexão padrão (central) por padrão
        $tenant = \App\Models\Tenant::find($tenantId);

        if (!$tenant) {
            \Log::warning('Tenant não encontrado no middleware', [
                'tenant_id' => $tenantId,
                'url' => $request->url(),
                'path' => $request->path(),
                'method' => $request->method(),
                'headers' => $request->headers->all()
            ]);
            return response()->json([
                'message' => 'Tenant não encontrado.',
                'tenant_id_procurado' => $tenantId
            ], 404);
        }

        // Inicializar tenancy
        try {
            tenancy()->initialize($tenant);
            
            // Setar no TenantContext (invisível para o controller)
            \App\Domain\Shared\ValueObjects\TenantContext::set($tenant->id);
            
            \Log::debug('Tenancy inicializado com sucesso', [
                'tenant_id' => $tenant->id,
                'tenant_razao_social' => $tenant->razao_social,
                'url' => $request->url()
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao inicializar tenancy', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erro ao inicializar tenancy: ' . $e->getMessage()
            ], 500);
        }

        return $next($request);
    }

    /**
     * Extrair tenant_id do token (se armazenado no token)
     */
    protected function getTenantIdFromToken(Request $request): ?string
    {
        // Tentar extrair do token Sanctum
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            // Verificar se há tenant_id nos abilities do token
            $abilities = $user->currentAccessToken()->abilities;
            if (isset($abilities['tenant_id'])) {
                \Log::debug('Tenant ID encontrado no token', [
                    'tenant_id' => $abilities['tenant_id'],
                    'url' => $request->url()
                ]);
                return $abilities['tenant_id'];
            }
        }
        return null;
    }

    /**
     * Tentar obter tenant_id do usuário autenticado através de cookies
     * Nota: APIs não têm sessão por padrão, então não tentamos acessar sessão
     */
    protected function getTenantIdFromUser(Request $request): ?string
    {
        // Se o usuário está autenticado, buscar o tenant pelo cookie
        // Isso é um fallback caso o header não esteja presente
        if ($request->user()) {
            // Para APIs, não usamos sessão (não está disponível)
            // Apenas tentar cookie como fallback
            // O tenant_id deve vir principalmente do header X-Tenant-ID ou do token
            return $request->cookie('tenant_id');
        }
        
        return null;
    }
}







