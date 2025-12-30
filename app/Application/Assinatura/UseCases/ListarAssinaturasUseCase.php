<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Use Case: Listar Assinaturas do Tenant
 */
class ListarAssinaturasUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param int $tenantId ID do tenant
     * @param array $filtros Filtros opcionais (status, etc)
     * @return Collection Collection de arrays com dados das assinaturas
     */
    public function executar(int $tenantId, array $filtros = []): Collection
    {
        // Buscar assinaturas usando repository DDD
        $assinaturas = $this->assinaturaRepository->listarPorTenant($tenantId, $filtros);

        return $assinaturas->map(function ($assinaturaDomain) {
            // Buscar plano usando repository DDD
            $planoDomain = null;
            if ($assinaturaDomain->planoId) {
                $planoDomain = $this->planoRepository->buscarPorId($assinaturaDomain->planoId);
            }

            // Calcular dias restantes
            $diasRestantes = 0;
            if ($assinaturaDomain->dataFim) {
                $hoje = now();
                $diasRestantes = $hoje->diffInDays($assinaturaDomain->dataFim, false);
            }

            return [
                'id' => $assinaturaDomain->id,
                'tenant_id' => $assinaturaDomain->tenantId,
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
        });
    }
}

