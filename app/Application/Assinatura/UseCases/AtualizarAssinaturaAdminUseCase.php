<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Assinatura\Events\AssinaturaAtualizada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use App\Modules\Auth\Models\User;
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
        private EventDispatcherInterface $eventDispatcher,
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
            
            // 🔥 CRÍTICO: Atualizar valor_pago automaticamente com o valor do plano
            // Se não foi fornecido valor_pago manualmente, usar o valor do plano
            if (!isset($dados['valor_pago']) || $dados['valor_pago'] === null || $dados['valor_pago'] === '') {
                $assinaturaModel->valor_pago = $plano->precoMensal ?? 0;
                Log::info('AtualizarAssinaturaAdminUseCase - Valor pago atualizado automaticamente com valor do plano', [
                    'plano_id' => $dados['plano_id'],
                    'valor_pago' => $assinaturaModel->valor_pago,
                ]);
            }
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
            // Se valor_pago foi fornecido, usar ele
            // Caso contrário, se plano foi atualizado, já foi definido acima
            $assinaturaModel->valor_pago = $dados['valor_pago'];
        } elseif (!isset($dados['plano_id'])) {
            // Se não atualizou plano e não forneceu valor_pago, garantir que tem valor
            // Buscar valor do plano atual se valor_pago estiver vazio
            if (!$assinaturaModel->valor_pago || $assinaturaModel->valor_pago == 0) {
                $planoAtual = $this->planoRepository->buscarPorId($assinaturaModel->plano_id);
                if ($planoAtual) {
                    $assinaturaModel->valor_pago = $planoAtual->precoMensal ?? 0;
                    Log::info('AtualizarAssinaturaAdminUseCase - Valor pago preenchido com valor do plano atual', [
                        'plano_id' => $assinaturaModel->plano_id,
                        'valor_pago' => $assinaturaModel->valor_pago,
                    ]);
                }
            }
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

        // Buscar entidade atualizada
        $assinaturaAtualizada = $this->assinaturaRepository->buscarPorId($assinaturaId);

        // Buscar email do usuário para notificação
        $emailDestino = null;
        if ($assinaturaDomain->userId) {
            $user = User::find($assinaturaDomain->userId);
            if ($user) {
                $emailDestino = $user->email;
            }
        }

        // Buscar assinatura original para obter status anterior
        $assinaturaOriginal = \App\Modules\Assinatura\Models\Assinatura::find($assinaturaId);
        $statusAnterior = $assinaturaOriginal?->getOriginal('status') ?? $assinaturaOriginal?->status ?? 'pendente';

        // Disparar evento de assinatura atualizada
        $this->eventDispatcher->dispatch(
            new AssinaturaAtualizada(
                assinaturaId: $assinaturaId,
                tenantId: $tenantId,
                empresaId: $assinaturaDomain->empresaId ?? 0,
                statusAnterior: $statusAnterior,
                status: $assinaturaDomain->status->value,
                userId: $assinaturaDomain->userId,
                planoId: $assinaturaModel->plano_id,
                emailDestino: $emailDestino,
            )
        );

        return $assinaturaAtualizada;
    }
}



