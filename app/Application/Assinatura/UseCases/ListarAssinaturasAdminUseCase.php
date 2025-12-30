<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Listar Assinaturas para Admin
 * 
 * Lista todas as assinaturas de todos os tenants para o painel administrativo
 */
class ListarAssinaturasAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param array $filtros Filtros: tenant_id, status, search
     * @return Collection Collection de arrays com dados das assinaturas
     */
    public function executar(array $filtros = []): Collection
    {
        // Buscar todos os tenants ativos
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'status' => 'ativa',
            'per_page' => 1000, // Buscar todos para admin
        ]);

        $todasAssinaturas = collect();

        // Iterar sobre os itens do paginador
        foreach ($tenantsPaginator->items() as $tenantDomain) {
            try {
                // Buscar assinatura atual do tenant usando repository DDD
                $assinaturaDomain = $this->assinaturaRepository->buscarAssinaturaAtual($tenantDomain->id);

                if (!$assinaturaDomain) {
                    continue;
                }

                // Buscar plano usando repository DDD
                $planoDomain = null;
                if ($assinaturaDomain->planoId) {
                    $planoDomain = $this->planoRepository->buscarPorId($assinaturaDomain->planoId);
                }

                // Calcular dias restantes (apenas dias completos, sem horas)
                $diasRestantes = 0;
                if ($assinaturaDomain->dataFim) {
                    $hoje = now()->startOfDay();
                    $dataFim = $assinaturaDomain->dataFim->copy()->startOfDay();
                    $diasRestantes = (int) $hoje->diffInDays($dataFim, false);
                }

                // Aplicar filtros
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

                // Montar array de dados
                $todasAssinaturas->push([
                    'id' => $assinaturaDomain->id,
                    'tenant_id' => $tenantDomain->id,
                    'tenant_nome' => $tenantDomain->razaoSocial,
                    'tenant_cnpj' => $tenantDomain->cnpj,
                    'plano_id' => $assinaturaDomain->planoId,
                    'plano_nome' => $planoDomain?->nome ?? 'N/A',
                    'status' => $assinaturaDomain->status,
                    'valor_pago' => $assinaturaDomain->valorPago ?? 0,
                    'data_inicio' => $assinaturaDomain->dataInicio?->format('Y-m-d'),
                    'data_fim' => $assinaturaDomain->dataFim?->format('Y-m-d'),
                    'metodo_pagamento' => $assinaturaDomain->metodoPagamento ?? 'N/A',
                    'transacao_id' => $assinaturaDomain->transacaoId,
                    'dias_restantes' => $diasRestantes,
                ]);
            } catch (\Exception $e) {
                Log::warning('Erro ao buscar assinatura do tenant no admin', [
                    'tenant_id' => $tenantDomain->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $todasAssinaturas;
    }
}

