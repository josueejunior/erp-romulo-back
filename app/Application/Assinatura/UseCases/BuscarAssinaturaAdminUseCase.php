<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Services\AdminTenancyRunner;

/**
 * Use Case: Buscar Assinatura Específica para Admin
 */
class BuscarAssinaturaAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PlanoRepositoryInterface $planoRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Executa o caso de uso
     */
    public function executar(int $tenantId, int $assinaturaId): array
    {
        // Buscar tenant
        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        
        if (!$tenantDomain) {
            throw new NotFoundException('Tenant não encontrado.');
        }

        // Buscar assinatura
        $assinaturaDomain = $this->assinaturaRepository->buscarPorId($assinaturaId);
        
        if (!$assinaturaDomain || $assinaturaDomain->tenantId !== $tenantId) {
            throw new NotFoundException('Assinatura não encontrada.');
        }

        // Buscar plano completo
        $planoDomain = null;
        $planoModel = null;
        if ($assinaturaDomain->planoId) {
            $planoDomain = $this->planoRepository->buscarPorId($assinaturaDomain->planoId);
            $planoModel = $this->planoRepository->buscarModeloPorId($assinaturaDomain->planoId);
        }

        // 🔥 Buscar empresa dentro do tenant (se a assinatura tem empresa_id)
        // IMPORTANTE: Empresas estão no banco do tenant, precisamos inicializar tenancy
        $empresaModel = null;
        try {
            if ($assinaturaDomain->empresaId) {
                $empresaModel = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($assinaturaDomain) {
                    return $this->empresaRepository->buscarModeloPorId($assinaturaDomain->empresaId);
                });
            }
            
            // Se não encontrou empresa pela empresa_id, buscar primeira empresa do tenant
            if (!$empresaModel) {
                $empresaModel = $this->adminTenancyRunner->runForTenant($tenantDomain, function () {
                    // Buscar primeira empresa não excluída
                    return \App\Models\Empresa::whereNull('excluido_em')->first();
                });
            }
        } catch (\Exception $e) {
            \Log::warning('BuscarAssinaturaAdminUseCase - Erro ao buscar empresa do tenant', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'empresa_id' => $assinaturaDomain->empresaId,
                'error' => $e->getMessage(),
            ]);
            // Continuar mesmo se não conseguir buscar empresa (empresa será null)
        }

        // Calcular dias restantes (apenas dias completos, sem horas)
        $diasRestantes = 0;
        if ($assinaturaDomain->dataFim) {
            $hoje = now()->startOfDay();
            $dataFim = $assinaturaDomain->dataFim->copy()->startOfDay();
            $diasRestantes = (int) $hoje->diffInDays($dataFim, false);
            
            // 🔥 CORREÇÃO: Para planos gratuitos, sempre mostrar 3 dias (período fixo de trial)
            if ($planoModel && (!$planoModel->preco_mensal || $planoModel->preco_mensal == 0)) {
                // Para planos gratuitos, mostrar sempre 3 dias (duração fixa do trial)
                $diasRestantes = max(0, (int) $hoje->diffInDays($dataFim, false));
                // Se ainda está dentro do período de 3 dias, manter o cálculo, mas não permitir mais de 3
                if ($diasRestantes > 3) {
                    $diasRestantes = 3;
                }
            }
        }

        // Montar resposta com objetos completos
        return [
            'id' => $assinaturaDomain->id,
            'tenant_id' => $tenantDomain->id,
            'tenant_nome' => $tenantDomain->razaoSocial,
            'tenant_cnpj' => $tenantDomain->cnpj,
            'empresa_id' => $assinaturaDomain->empresaId,
            'empresa' => $empresaModel ? [
                'id' => $empresaModel->id,
                'razao_social' => $empresaModel->razao_social,
                'nome_fantasia' => $empresaModel->nome_fantasia,
                'cnpj' => $empresaModel->cnpj,
            ] : null,
            'plano_id' => $assinaturaDomain->planoId,
            'plano' => $planoModel ? [
                'id' => $planoModel->id,
                'nome' => $planoModel->nome,
                'preco_mensal' => $planoModel->preco_mensal,
                'preco_anual' => $planoModel->preco_anual,
                'ativo' => $planoModel->ativo,
            ] : null,
            'plano_nome' => $planoDomain?->nome ?? 'N/A', // Mantido para compatibilidade
            'status' => $assinaturaDomain->status,
            'valor_pago' => $assinaturaDomain->valorPago ?? 0,
            'data_inicio' => $assinaturaDomain->dataInicio?->format('Y-m-d'),
            'data_fim' => $assinaturaDomain->dataFim?->format('Y-m-d'),
            'metodo_pagamento' => $assinaturaDomain->metodoPagamento ?? 'N/A',
            'transacao_id' => $assinaturaDomain->transacaoId,
            'dias_restantes' => $diasRestantes,
            'dias_grace_period' => $assinaturaDomain->diasGracePeriod ?? 7,
            'observacoes' => $assinaturaDomain->observacoes,
        ];
    }
}

