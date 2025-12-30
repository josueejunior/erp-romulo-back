<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

/**
 * Use Case: Buscar Assinatura Específica para Admin
 */
class BuscarAssinaturaAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PlanoRepositoryInterface $planoRepository,
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

        // Buscar plano
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

        return [
            'id' => $assinaturaDomain->id,
            'tenant_id' => $tenantDomain->id,
            'tenant_nome' => $tenantDomain->razaoSocial,
            'plano_id' => $assinaturaDomain->planoId,
            'plano_nome' => $planoDomain?->nome ?? 'N/A',
            'status' => $assinaturaDomain->status,
            'valor_pago' => $assinaturaDomain->valorPago ?? 0,
            'data_inicio' => $assinaturaDomain->dataInicio?->format('Y-m-d'),
            'data_fim' => $assinaturaDomain->dataFim?->format('Y-m-d'),
            'metodo_pagamento' => $assinaturaDomain->metodoPagamento ?? 'N/A',
            'transacao_id' => $assinaturaDomain->transacaoId,
            'dias_restantes' => $diasRestantes,
            'observacoes' => $assinaturaDomain->observacoes,
        ];
    }
}

