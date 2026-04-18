<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para buscar o tenant correto baseado no usuário autenticado
 * 
 * Responsabilidades:
 * - Buscar tenant onde a empresa ativa do usuário está
 * - Otimizar busca (verificar tenant atual primeiro)
 * - Retornar modelo Eloquent para uso em controllers
 * 
 * 🔥 CRÍTICO: A validação de assinatura é baseada no USUÁRIO, não no tenant/empresa do header.
 */
class BuscarTenantDoUsuarioUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * Busca o tenant correto baseado no USUÁRIO autenticado
     * 
     * @param Authenticatable $user Usuário autenticado
     * @return \App\Models\Tenant|null Modelo Eloquent do tenant ou null
     */
    public function executar(Authenticatable $user): ?\App\Models\Tenant
    {
        // Obter empresa ativa do usuário (fonte de verdade)
        $empresaAtivaId = $user->empresa_ativa_id ?? null;
        if (!$empresaAtivaId) {
            Log::debug('BuscarTenantDoUsuarioUseCase: Usuário não tem empresa ativa', [
                'user_id' => $user->id,
            ]);
            return null;
        }

        // Prioridade 1: Verificar se empresa existe no tenant atual (otimização)
        $tenantAtual = tenancy()->tenant;
        if ($tenantAtual && tenancy()->initialized) {
            try {
                $empresaNoTenantAtual = \App\Models\Empresa::find($empresaAtivaId);
                if ($empresaNoTenantAtual) {
                    Log::info('BuscarTenantDoUsuarioUseCase: Empresa encontrada no tenant atual', [
                        'user_id' => $user->id,
                        'empresa_id' => $empresaAtivaId,
                        'tenant_id' => $tenantAtual->id,
                    ]);
                    return $tenantAtual;
                }
            } catch (\Exception $e) {
                Log::debug('BuscarTenantDoUsuarioUseCase: Erro ao buscar no tenant atual', [
                    'tenant_id' => $tenantAtual->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Prioridade 2: Buscar empresa em outros tenants
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros(['per_page' => 10000]);
        $tenants = $tenantsPaginator->getCollection();
        
        foreach ($tenants as $tenantDomain) {
            // Pular o tenant atual (já verificamos)
            if ($tenantAtual && $tenantDomain->id == $tenantAtual->id) {
                continue;
            }
            
            try {
                $tenant = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                if (!$tenant) {
                    continue;
                }
                
                tenancy()->initialize($tenant);
                $empresa = \App\Models\Empresa::find($empresaAtivaId);
                
                if ($empresa) {
                    // Encontrou a empresa neste tenant - este é o tenant correto do usuário
                    tenancy()->end();
                    
                    Log::info('BuscarTenantDoUsuarioUseCase: Tenant encontrado para o usuário', [
                        'user_id' => $user->id,
                        'empresa_id' => $empresaAtivaId,
                        'tenant_id_encontrado' => $tenant->id,
                        'tenant_razao_social' => $tenant->razao_social,
                    ]);
                    
                    return $tenant;
                }
                
                tenancy()->end();
            } catch (\Exception $e) {
                tenancy()->end();
                Log::debug('BuscarTenantDoUsuarioUseCase: Erro ao buscar no tenant', [
                    'tenant_id' => $tenantDomain->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::warning('BuscarTenantDoUsuarioUseCase: Tenant não encontrado para o usuário', [
            'user_id' => $user->id,
            'empresa_ativa_id' => $empresaAtivaId,
        ]);

        return null;
    }
}

