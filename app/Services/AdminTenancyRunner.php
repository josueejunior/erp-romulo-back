<?php

namespace App\Services;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Service para executar código dentro do contexto de um tenant específico
 * 
 * 🔥 RESPONSABILIDADE ÚNICA: Gerenciar inicialização/finalização de tenancy
 * para casos administrativos que precisam iterar múltiplos tenants.
 * 
 * ✅ Use Cases administrativos usam este service para isolar lógica de infraestrutura
 * ❌ Use Cases comuns NUNCA usam este service (usam ApplicationContext)
 * 
 * @example
 * $this->adminTenancyRunner->runForTenant($tenantDomain, function () {
 *     return $this->assinaturaRepository->buscarAssinaturaAtual($tenantId);
 * });
 */
class AdminTenancyRunner
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Executa um callback dentro do contexto de um tenant específico
     * 
     * 🔥 GARANTIAS:
     * - Sempre finaliza o tenancy, mesmo em caso de exceção
     * - Não interfere com tenancy já inicializado (finaliza antes)
     * - Logs detalhados para debugging
     * 
     * @param Tenant $tenantDomain Entidade do tenant (Domain)
     * @param \Closure $callback Código a ser executado dentro do contexto do tenant
     * @return mixed Retorno do callback
     * @throws \RuntimeException Se o tenant não for encontrado ou houver erro ao inicializar
     */
    public function runForTenant(Tenant $tenantDomain, \Closure $callback): mixed
    {
        // Buscar modelo Eloquent (necessário para tenancy()->initialize())
        $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
        
        if (!$tenantModel) {
            Log::warning('AdminTenancyRunner::runForTenant() - Tenant não encontrado', [
                'tenant_id' => $tenantDomain->id,
            ]);
            throw new \RuntimeException("Tenant não encontrado: {$tenantDomain->id}");
        }

        // Verificar se já está inicializado e se é o tenant correto
        $jaInicializado = tenancy()->initialized;
        $tenantAtual = tenancy()->tenant;
        $precisaInicializar = !$jaInicializado || ($tenantAtual && $tenantAtual->id !== $tenantDomain->id);

        Log::debug('AdminTenancyRunner::runForTenant() - Preparando contexto', [
            'tenant_id' => $tenantDomain->id,
            'ja_inicializado' => $jaInicializado,
            'tenant_id_atual' => $tenantAtual?->id,
            'precisa_inicializar' => $precisaInicializar,
        ]);

        try {
            // Inicializar tenancy se necessário
            if ($precisaInicializar) {
                if ($jaInicializado) {
                    tenancy()->end();
                }
                tenancy()->initialize($tenantModel);
                
                Log::debug('AdminTenancyRunner::runForTenant() - Tenancy inicializado', [
                    'tenant_id' => $tenantDomain->id,
                ]);
            }

            // Executar callback dentro do contexto do tenant
            return $callback();
            
        } finally {
            // 🔥 CRÍTICO: Sempre finalizar o contexto se foi inicializado aqui
            // Isso evita vazamento de contexto e bugs silenciosos
            if ($precisaInicializar && tenancy()->initialized) {
                tenancy()->end();
                
                Log::debug('AdminTenancyRunner::runForTenant() - Tenancy finalizado', [
                    'tenant_id' => $tenantDomain->id,
                ]);
            }
        }
    }
}


