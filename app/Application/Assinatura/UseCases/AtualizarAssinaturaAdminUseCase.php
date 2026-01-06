<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Atualizar Assinatura (Admin)
 * 
 * Permite ao admin atualizar dados de uma assinatura
 */
class AtualizarAssinaturaAdminUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PlanoRepositoryInterface $planoRepository,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param int $tenantId ID do tenant
     * @param int $assinaturaId ID da assinatura
     * @param array $dados Dados para atualizar
     * @return \App\Domain\Assinatura\Entities\Assinatura
     */
    public function executar(int $tenantId, int $assinaturaId, array $dados): \App\Domain\Assinatura\Entities\Assinatura
    {
        // Validar tenant
        $tenant = $this->tenantRepository->buscarPorId($tenantId);
        if (!$tenant) {
            throw new NotFoundException("Tenant não encontrado.");
        }

        // Buscar assinatura
        $assinaturaDomain = $this->assinaturaRepository->buscarPorId($assinaturaId);
        if (!$assinaturaDomain) {
            throw new NotFoundException("Assinatura não encontrada.");
        }

        // Validar que a assinatura pertence ao tenant
        if ($assinaturaDomain->tenantId !== $tenantId) {
            throw new DomainException("A assinatura não pertence a este tenant.");
        }

        // Buscar modelo para atualização
        $assinaturaModel = $this->assinaturaRepository->buscarModeloPorId($assinaturaId);
        if (!$assinaturaModel) {
            throw new NotFoundException("Assinatura não encontrada.");
        }

        // Atualizar campos permitidos
        if (isset($dados['plano_id'])) {
            // Validar plano
            $plano = $this->planoRepository->buscarPorId($dados['plano_id']);
            if (!$plano) {
                throw new NotFoundException("Plano não encontrado.");
            }
            $assinaturaModel->plano_id = $dados['plano_id'];
        }

        if (isset($dados['status'])) {
            $statusValidos = ['ativa', 'suspensa', 'expirada', 'cancelada'];
            if (!in_array($dados['status'], $statusValidos)) {
                throw new DomainException("Status inválido. Valores permitidos: " . implode(', ', $statusValidos));
            }
            $assinaturaModel->status = $dados['status'];
        }

        if (isset($dados['data_inicio'])) {
            $assinaturaModel->data_inicio = $dados['data_inicio'];
        }

        if (isset($dados['data_fim'])) {
            $assinaturaModel->data_fim = $dados['data_fim'];
        }

        if (isset($dados['valor_pago'])) {
            if ($dados['valor_pago'] < 0) {
                throw new DomainException("O valor pago não pode ser negativo.");
            }
            $assinaturaModel->valor_pago = $dados['valor_pago'];
        }

        if (isset($dados['metodo_pagamento'])) {
            $assinaturaModel->metodo_pagamento = $dados['metodo_pagamento'];
        }

        if (isset($dados['dias_grace_period'])) {
            if ($dados['dias_grace_period'] < 0) {
                throw new DomainException("O período de graça não pode ser negativo.");
            }
            $assinaturaModel->dias_grace_period = $dados['dias_grace_period'];
        }

        // Salvar alterações
        $assinaturaModel->save();

        // Se a assinatura foi marcada como ativa e é a assinatura atual do tenant, atualizar tenant
        if (isset($dados['status']) && $dados['status'] === 'ativa') {
            $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantId);
            if ($tenantModel) {
                $tenantModel->update([
                    'plano_atual_id' => $assinaturaModel->plano_id,
                    'assinatura_atual_id' => $assinaturaModel->id,
                ]);
            }
        }

        // Retornar entidade atualizada
        return $this->assinaturaRepository->buscarPorId($assinaturaId);
    }
}


