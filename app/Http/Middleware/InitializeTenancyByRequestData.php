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
        // 🔥 CRÍTICO: Buscar tenant correto baseado na empresa ativa
        // Prioridade: empresa_id do header > empresa_ativa_id do usuário > tenant_id do header
        
        $tenantId = null;
        $tenant = null;
        
        // Prioridade 1: Se tiver X-Empresa-ID, buscar tenant onde essa empresa está
        $empresaId = $request->header('X-Empresa-ID') 
            ? (int) $request->header('X-Empresa-ID') 
            : null;
        
        if ($empresaId) {
            $tenant = $this->buscarTenantPorEmpresa($empresaId);
            if ($tenant) {
                $tenantId = $tenant->id;
                \Log::info('InitializeTenancyByRequestData - Tenant encontrado via empresa_id do header', [
                    'empresa_id' => $empresaId,
                    'tenant_id' => $tenantId,
                    'url' => $request->url()
                ]);
            }
        }
        
        // Prioridade 2: Se não encontrou via empresa_id, tentar empresa_ativa_id do usuário
        if (!$tenant) {
            $user = $request->user();
            if ($user && $user->empresa_ativa_id) {
                $tenant = $this->buscarTenantPorEmpresa($user->empresa_ativa_id);
                if ($tenant) {
                    $tenantId = $tenant->id;
                    \Log::info('InitializeTenancyByRequestData - Tenant encontrado via empresa_ativa_id do usuário', [
                        'empresa_ativa_id' => $user->empresa_ativa_id,
                        'tenant_id' => $tenantId,
                        'user_id' => $user->id,
                        'url' => $request->url()
                    ]);
                }
            }
        }
        
        // Prioridade 3: Fallback para X-Tenant-ID do header
        if (!$tenant) {
            $tenantId = $request->header('X-Tenant-ID') 
                ?? $request->input('tenant_id')
                ?? $this->getTenantIdFromToken($request)
                ?? $this->getTenantIdFromUser($request);
            
            if ($tenantId) {
                $tenant = \App\Models\Tenant::find($tenantId);
                if ($tenant) {
                    \Log::debug('InitializeTenancyByRequestData - Tenant obtido do header/token (fallback)', [
                        'tenant_id' => $tenantId,
                        'url' => $request->url()
                    ]);
                }
            }
        }

        // Verificar se já há tenancy inicializado
        if (tenancy()->initialized) {
            $currentTenant = tenancy()->tenant;
            $currentTenantId = $currentTenant?->id;
            
            // Se o tenant correto é diferente do tenant atual, reinicializar
            if ($tenant && $tenant->id !== $currentTenantId) {
                \Log::info('Tenant mudou, reinicializando tenancy', [
                    'tenant_id_atual' => $currentTenantId,
                    'tenant_id_correto' => $tenant->id,
                    'empresa_id' => $empresaId,
                    'url' => $request->url()
                ]);
                
                // Finalizar tenant atual
                tenancy()->end();
                
                // Inicializar tenant correto
                tenancy()->initialize($tenant);
                
                // Setar no TenantContext (invisível para o controller)
                \App\Domain\Shared\ValueObjects\TenantContext::set($tenant->id);
                
                \Log::debug('Tenancy reinicializado com sucesso', [
                    'tenant_id' => $tenant->id,
                    'tenant_razao_social' => $tenant->razao_social,
                    'url' => $request->url()
                ]);
            } else {
                \Log::debug('Tenancy já inicializado com tenant correto', [
                    'tenant_id' => $currentTenantId,
                    'url' => $request->url()
                ]);
            }
            
            // Determinar empresa_id e atualizar contexto
            $empresaIdFinal = $this->determinarEmpresaId($request, $tenant?->id ?? $currentTenantId);
            if ($empresaIdFinal) {
                \App\Domain\Shared\ValueObjects\TenantContext::set($tenant?->id ?? $currentTenantId, $empresaIdFinal);
                app()->instance('current_empresa_id', $empresaIdFinal);
                $request->attributes->set('empresa_id', $empresaIdFinal);
            }
            
            return $next($request);
        }

        \Log::debug('Tentando inicializar tenancy', [
            'tenant_id_header' => $request->header('X-Tenant-ID'),
            'empresa_id_header' => $request->header('X-Empresa-ID'),
            'tenant_id_final' => $tenantId,
            'tenant_encontrado' => $tenant ? $tenant->id : null,
            'url' => $request->url()
        ]);

        if (!$tenant) {
            // Se for admin, não precisa de tenant
            $user = $request->user();
            if ($user && $user instanceof \App\Modules\Auth\Models\AdminUser) {
                \Log::debug('Admin user detectado, pulando inicialização de tenancy', [
                    'user_id' => $user->id,
                    'url' => $request->url()
                ]);
                return $next($request);
            }
            
            \Log::warning('Tenant não encontrado', [
                'url' => $request->url(),
                'user_id' => $user?->id,
                'empresa_id_header' => $request->header('X-Empresa-ID'),
                'tenant_id_header' => $request->header('X-Tenant-ID'),
                'headers' => $request->headers->all()
            ]);
            return response()->json([
                'message' => 'Tenant não encontrado. Verifique se a empresa existe e está vinculada a um tenant.',
                'code' => 'TENANT_NOT_FOUND'
            ], 404);
        }

        // Inicializar tenancy
        try {
            tenancy()->initialize($tenant);
            
            // Determinar empresa_id do contexto
            $empresaId = $this->determinarEmpresaId($request, $tenant->id);
            
            // Setar no TenantContext COM empresa_id (invisível para o controller)
            \App\Domain\Shared\ValueObjects\TenantContext::set($tenant->id, $empresaId);
            
            // Inicializar ApplicationContext (novo serviço centralizado)
            $user = $request->user();
            $empresaIdFromHeader = $request->header('X-Empresa-ID') 
                ? (int) $request->header('X-Empresa-ID') 
                : null;
            
            if (app()->bound(\App\Services\ApplicationContext::class)) {
                $appContext = app(\App\Services\ApplicationContext::class);
                $appContext->initialize($user, $tenant->id, $empresaIdFromHeader);
                
                // Usar empresa_id do ApplicationContext se disponível
                if ($appContext->getEmpresaIdOrNull()) {
                    $empresaId = $appContext->getEmpresaId();
                }
            }
            
            // Também disponibilizar via app() para Global Scopes e UseCases
            if ($empresaId) {
                app()->instance('current_empresa_id', $empresaId);
                $request->attributes->set('empresa_id', $empresaId);
            }
            
            \Log::debug('Tenancy inicializado com sucesso', [
                'tenant_id' => $tenant->id,
                'tenant_razao_social' => $tenant->razao_social,
                'empresa_id' => $empresaId,
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
     * Determinar empresa_id baseado no header, usuário autenticado ou tenant
     */
    protected function determinarEmpresaId(Request $request, int $tenantId): ?int
    {
        $empresaId = null;
        
        // Prioridade 1: Header X-Empresa-ID
        if ($request->header('X-Empresa-ID')) {
            $empresaId = (int) $request->header('X-Empresa-ID');
            \Log::debug('InitializeTenancyByRequestData - empresaId do header X-Empresa-ID', [
                'empresa_id' => $empresaId
            ]);
            return $empresaId;
        }
        
        // Prioridade 2: empresa_ativa_id do usuário autenticado
        $user = $request->user();
        if ($user && $user->empresa_ativa_id) {
            $empresaId = $user->empresa_ativa_id;
            \Log::debug('InitializeTenancyByRequestData - empresaId do user.empresa_ativa_id', [
                'empresa_id' => $empresaId,
                'user_id' => $user->id
            ]);
            return $empresaId;
        }
        
        // Prioridade 3: Primeira empresa do usuário
        if ($user) {
            $empresa = $user->empresas()->first();
            if ($empresa) {
                $empresaId = $empresa->id;
                
                // Atualizar empresa_ativa_id do usuário
                $user->empresa_ativa_id = $empresaId;
                $user->save();
                
                \Log::debug('InitializeTenancyByRequestData - empresaId da primeira empresa do usuário', [
                    'empresa_id' => $empresaId,
                    'user_id' => $user->id
                ]);
                return $empresaId;
            }
        }
        
        \Log::warning('InitializeTenancyByRequestData - Nenhum empresaId encontrado', [
            'tenant_id' => $tenantId,
            'user_id' => $user?->id
        ]);
        
        return null;
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

    /**
     * Buscar tenant correto baseado na empresa
     * Itera por todos os tenants procurando a empresa
     * 
     * 🔥 CRÍTICO: Garante que o tenant retornado seja o correto da empresa,
     * não apenas o tenant do header (que pode estar desatualizado)
     */
    protected function buscarTenantPorEmpresa(int $empresaId): ?\App\Models\Tenant
    {
        // 🔥 CRÍTICO: Priorizar o tenant atual se já estiver inicializado e tiver a empresa
        $tenantAtual = tenancy()->tenant;
        if ($tenantAtual && tenancy()->initialized) {
            try {
                $empresa = \App\Models\Empresa::find($empresaId);
                if ($empresa) {
                    \Log::info('InitializeTenancyByRequestData::buscarTenantPorEmpresa() - Empresa encontrada no tenant atual', [
                        'empresa_id' => $empresaId,
                        'tenant_id' => $tenantAtual->id,
                        'tenant_razao_social' => $tenantAtual->razao_social,
                    ]);
                    return $tenantAtual;
                }
            } catch (\Exception $e) {
                \Log::debug('InitializeTenancyByRequestData::buscarTenantPorEmpresa() - Erro ao verificar tenant atual', [
                    'tenant_id' => $tenantAtual->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Se não encontrou no tenant atual, buscar em todos os tenants
        $tenants = \App\Models\Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Pular o tenant atual se já verificamos
            if ($tenantAtual && $tenant->id === $tenantAtual->id) {
                continue;
            }
            
            try {
                // Inicializar contexto do tenant
                tenancy()->initialize($tenant);
                
                try {
                    // Tentar buscar empresa neste tenant
                    $empresa = \App\Models\Empresa::find($empresaId);
                    if ($empresa) {
                        // Empresa encontrada neste tenant - este é o tenant correto
                        tenancy()->end();
                        
                        \Log::info('InitializeTenancyByRequestData::buscarTenantPorEmpresa() - Tenant encontrado', [
                            'empresa_id' => $empresaId,
                            'tenant_id' => $tenant->id,
                            'tenant_razao_social' => $tenant->razao_social,
                        ]);
                        
                        return $tenant;
                    }
                } finally {
                    // Sempre finalizar contexto
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            } catch (\Exception $e) {
                // Se houver erro ao acessar o tenant, continuar para o próximo
                \Log::debug("Erro ao buscar empresa no tenant {$tenant->id}: " . $e->getMessage());
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                continue;
            }
        }
        
        \Log::warning('InitializeTenancyByRequestData::buscarTenantPorEmpresa() - Empresa não encontrada em nenhum tenant', [
            'empresa_id' => $empresaId,
        ]);
        
        return null; // Empresa não encontrada em nenhum tenant
    }
}







