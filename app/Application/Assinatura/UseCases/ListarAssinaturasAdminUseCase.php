<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Services\AdminTenancyRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Listar Assinaturas para Admin
 * 
 * Lista todas as assinaturas de todos os tenants para o painel administrativo
 * 
 * 🔥 ARQUITETURA LIMPA:
 * - Use Case orquestra apenas lógica de negócio
 * - AdminTenancyRunner isola lógica de infraestrutura (tenancy)
 * - Repositories não conhecem tenancy
 * - Domain isolado de Eloquent
 */
class ListarAssinaturasAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PlanoRepositoryInterface $planoRepository,
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param array $filtros Filtros: tenant_id, status, search
     * @return Collection Collection de arrays com dados das assinaturas
     */
    public function executar(array $filtros = []): Collection
    {
        // Cache removido - sempre buscar dados atualizados do banco
        return $this->buscarAssinaturas($filtros);
    }

    /**
     * Busca assinaturas (sem cache)
     * 
     * @param array $filtros Filtros: tenant_id, status, search
     * @return Collection Collection de arrays com dados das assinaturas
     */
    private function buscarAssinaturas(array $filtros = []): Collection
    {
        Log::debug('ListarAssinaturasAdminUseCase - Buscando assinaturas', ['filtros' => $filtros]);
        
        // Buscar todos os tenants ativos
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'status' => 'ativa',
            'per_page' => 1000, // Buscar todos para admin
        ]);

        $todasAssinaturas = collect();

        // Iterar sobre os itens do paginador
        foreach ($tenantsPaginator->items() as $tenantDomain) {
            try {
                // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
                // O use case não conhece tenancy(), apenas orquestra lógica de negócio
                $resultado = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($tenantDomain) {
                    // Buscar assinatura atual do tenant usando repository DDD
                    // O tenancy já está inicializado pelo AdminTenancyRunner
                    $assinaturaDomain = $this->assinaturaRepository->buscarAssinaturaAtual($tenantDomain->id);

                    if (!$assinaturaDomain) {
                        return null;
                    }

                    // Buscar plano usando repository DDD
                    $planoDomain = null;
                    if ($assinaturaDomain->planoId) {
                        $planoDomain = $this->planoRepository->buscarPorId($assinaturaDomain->planoId);
                    }

                    // Calcular dias restantes (apenas dias completos, sem horas)
                    // A lógica de duração (trial 3 dias, ilimitado, etc.) já está
                    // centralizada em AssinaturaDomainService::calcularDataFim via limite_dias.
                    $diasRestantes = 0;
                    if ($assinaturaDomain->dataFim) {
                        $hoje = now()->startOfDay();
                        $dataFim = $assinaturaDomain->dataFim->copy()->startOfDay();
                        $diasRestantes = (int) $hoje->diffInDays($dataFim, false);
                    }

                    // Retornar dados da assinatura (filtros serão aplicados fora do callback)
                    return [
                        'assinatura' => $assinaturaDomain,
                        'plano' => $planoDomain,
                        'dias_restantes' => $diasRestantes,
                    ];
                });

                // Se não encontrou assinatura, pular
                if (!$resultado) {
                    continue;
                }

                $assinaturaDomain = $resultado['assinatura'];
                $planoDomain = $resultado['plano'];
                $diasRestantes = $resultado['dias_restantes'];

                // Aplicar filtros (lógica de negócio do use case)
                if (isset($filtros['tenant_id']) && $filtros['tenant_id'] && $tenantDomain->id != $filtros['tenant_id']) {
                    continue;
                }

                if (isset($filtros['status']) && $filtros['status'] && $assinaturaDomain->status !== $filtros['status']) {
                    continue;
                }

                if (isset($filtros['search']) && $filtros['search']) {
                    $search = strtolower($filtros['search']);
                    $tenantNome = strtolower($tenantDomain->razaoSocial);
                    $planoNome = strtolower($planoDomain?->nome ?? '');
                    
                    if (!str_contains($tenantNome, $search) && !str_contains($planoNome, $search)) {
                        continue;
                    }
                }

                // Montar array de dados (orquestração do use case)
                $todasAssinaturas->push([
                    'id' => $assinaturaDomain->id,
                    'tenant_id' => $tenantDomain->id,
                    'tenant_nome' => $tenantDomain->razaoSocial,
                    'tenant_cnpj' => $tenantDomain->cnpj,
                    'plano_id' => $assinaturaDomain->planoId,
                    'plano_nome' => $planoDomain?->nome ?? null,
                    'status' => $assinaturaDomain->status,
                    'valor_pago' => $assinaturaDomain->valorPago ?? 0,
                    'data_inicio' => $assinaturaDomain->dataInicio?->format('Y-m-d'),
                    'data_fim' => $assinaturaDomain->dataFim?->format('Y-m-d'),
                    'metodo_pagamento' => $assinaturaDomain->metodoPagamento ?? null,
                    'transacao_id' => $assinaturaDomain->transacaoId,
                    'dias_restantes' => $diasRestantes,
                ]);
            } catch (\Exception $e) {
                Log::warning('Erro ao buscar assinatura do tenant no admin', [
                    'tenant_id' => $tenantDomain->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // AdminTenancyRunner já garantiu finalização do tenancy no finally
                continue;
            }
        }

        return $todasAssinaturas;
    }

}

