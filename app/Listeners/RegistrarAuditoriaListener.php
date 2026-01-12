<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Auditoria\UseCases\RegistrarAuditoriaUseCase;
use App\Domain\Assinatura\Events\AssinaturaCriada;
use App\Domain\Assinatura\Events\AssinaturaAtualizada;
use App\Domain\Payment\Events\PagamentoProcessado;
use App\Domain\Afiliado\Events\ComissaoGerada;
use App\Domain\Auditoria\Enums\AuditAction;
use App\Domain\Shared\ValueObjects\RequestContext;
use Illuminate\Support\Facades\Log;

/**
 * Listener para registrar auditoria de operações críticas
 * 
 * Segue DDD: Usa Use Case, não conhece detalhes de persistência
 */
class RegistrarAuditoriaListener
{
    public function __construct(
        private readonly RegistrarAuditoriaUseCase $registrarAuditoriaUseCase,
    ) {}

    /**
     * Registra auditoria quando assinatura é criada
     */
    public function handleAssinaturaCriada(AssinaturaCriada $event): void
    {
        try {
            $this->registrarAuditoriaUseCase->executar(
                action: AuditAction::SUBSCRIPTION_ACTIVATED,
                modelType: 'App\\Modules\\Assinatura\\Models\\Assinatura',
                modelId: $event->assinaturaId,
                newValues: [
                    'id' => $event->assinaturaId,
                    'tenant_id' => $event->tenantId,
                    'empresa_id' => $event->empresaId,
                    'plano_id' => $event->planoId,
                    'status' => $event->status,
                ],
                description: "Assinatura criada: {$event->status}",
                context: RequestContext::empty(), // Event disparado em contexto assíncrono
            );
        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria de assinatura criada', [
                'error' => $e->getMessage(),
                'event' => $event->assinaturaId,
            ]);
        }
    }

    /**
     * Registra auditoria quando assinatura é atualizada
     */
    public function handleAssinaturaAtualizada(AssinaturaAtualizada $event): void
    {
        try {
            $this->registrarAuditoriaUseCase->executar(
                action: AuditAction::STATUS_CHANGED,
                modelType: 'App\\Modules\\Assinatura\\Models\\Assinatura',
                modelId: $event->assinaturaId,
                oldValues: ['status' => $event->statusAnterior],
                newValues: ['status' => $event->status],
                description: "Status da assinatura alterado: {$event->statusAnterior} -> {$event->status}",
                context: RequestContext::empty(),
            );
        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria de assinatura atualizada', [
                'error' => $e->getMessage(),
                'event' => $event->assinaturaId,
            ]);
        }
    }

    /**
     * Registra auditoria quando pagamento é processado
     */
    public function handlePagamentoProcessado(PagamentoProcessado $event): void
    {
        try {
            $this->registrarAuditoriaUseCase->executar(
                action: AuditAction::PAYMENT_PROCESSED,
                modelType: 'App\\Models\\PaymentLog',
                modelId: $event->paymentLogId,
                newValues: [
                    'id' => $event->paymentLogId,
                    'tenant_id' => $event->tenantId,
                    'assinatura_id' => $event->assinaturaId,
                    'plano_id' => $event->planoId,
                    'status' => $event->status,
                    'valor' => $event->valor,
                    'metodo_pagamento' => $event->metodoPagamento,
                    'external_id' => $event->externalId,
                ],
                description: "Pagamento processado: {$event->status} - R$ " . number_format($event->valor, 2, ',', '.'),
                context: RequestContext::empty(),
            );
        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria de pagamento processado', [
                'error' => $e->getMessage(),
                'event' => $event->paymentLogId,
            ]);
        }
    }

    /**
     * Registra auditoria quando comissão é gerada
     */
    public function handleComissaoGerada(ComissaoGerada $event): void
    {
        try {
            $this->registrarAuditoriaUseCase->executar(
                action: AuditAction::COMMISSION_GENERATED,
                modelType: 'App\\Modules\\Afiliado\\Models\\Comissao',
                modelId: $event->comissaoId,
                newValues: [
                    'id' => $event->comissaoId,
                    'afiliado_id' => $event->afiliadoId,
                    'tenant_id' => $event->tenantId,
                    'assinatura_id' => $event->assinaturaId,
                    'valor' => $event->valor,
                    'tipo' => $event->tipo,
                    'status' => $event->status,
                    'periodo_competencia' => $event->periodoCompetencia,
                ],
                description: "Comissão gerada: {$event->tipo} - R$ " . number_format($event->valor, 2, ',', '.'),
                context: RequestContext::empty(),
            );
        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria de comissão gerada', [
                'error' => $e->getMessage(),
                'event' => $event->comissaoId,
            ]);
        }
    }
}




