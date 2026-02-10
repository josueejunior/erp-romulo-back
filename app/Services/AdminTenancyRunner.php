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
                // 🔥 CRÍTICO: Sempre finalizar tenancy anterior e limpar conexões antes de inicializar novo
                if ($jaInicializado) {
                    tenancy()->end();
                }
                
                // 🔥 CRÍTICO: Limpar conexão 'tenant' ANTES de inicializar novo tenant
                // Isso garante que a conexão não mantenha referência ao banco anterior
                \Illuminate\Support\Facades\DB::purge('tenant');
                
                // Inicializar tenancy para o novo tenant
                tenancy()->initialize($tenantModel);
                
                // 🔥 MULTI-DATABASE: Configurar conexão do banco do tenant
                // Assim como ResolveTenantContext faz, precisamos trocar a conexão padrão
                // para o banco do tenant quando a conexão padrão ainda for a central
                $centralConnectionName = config('tenancy.database.central_connection', 'pgsql');
                $defaultConnectionName = config('database.default');
                $tenantDbName = $tenantModel->database()->getName();
                
                // 🔥 CRÍTICO: Sempre configurar a conexão tenant com o banco correto
                config(['database.connections.tenant.database' => $tenantDbName]);
                \Illuminate\Support\Facades\DB::purge('tenant'); // Limpar novamente após configurar
                config(['database.default' => 'tenant']); // Definir 'tenant' como conexão padrão
                
                // 🔥 VERIFICAÇÃO: Garantir que a conexão está correta
                $databaseVerificado = \Illuminate\Support\Facades\DB::connection('tenant')->getDatabaseName();
                if ($databaseVerificado !== $tenantDbName) {
                    Log::error('AdminTenancyRunner::runForTenant() - Conexão não configurada corretamente', [
                        'tenant_id' => $tenantDomain->id,
                        'tenant_database_esperado' => $tenantDbName,
                        'tenant_database_atual' => $databaseVerificado,
                    ]);
                    // Tentar corrigir
                    \Illuminate\Support\Facades\DB::purge('tenant');
                    config(['database.connections.tenant.database' => $tenantDbName]);
                }
                
                Log::debug('AdminTenancyRunner::runForTenant() - Tenancy inicializado e conexão configurada', [
                    'tenant_id' => $tenantDomain->id,
                    'tenant_database' => $tenantDbName,
                    'database_verificado' => $databaseVerificado,
                    'default_connection_after_init' => config('database.default'),
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


