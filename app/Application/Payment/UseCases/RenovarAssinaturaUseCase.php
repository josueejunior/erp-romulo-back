<?php

namespace App\Application\Payment\UseCases;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Shared\ValueObjects\Money;
use App\Modules\Assinatura\Models\Assinatura;
use App\Models\PaymentLog;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Use Case: Renovar Assinatura
 * 
 * Application Layer - Orquestra o fluxo de renovação de assinatura com pagamento
 */
class RenovarAssinaturaUseCase
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
    ) {}

    /**
     * Renova uma assinatura existente
     * 
     * @param Assinatura $assinatura Assinatura a ser renovada
     * @param PaymentRequest $paymentRequest Dados do pagamento
     * @param int $meses Número de meses para renovar (1 ou 12)
     * @return Assinatura Assinatura renovada
     */
    public function executar(
        Assinatura $assinatura,
        PaymentRequest $paymentRequest,
        int $meses = 1
    ): Assinatura {
        // Validar assinatura
        if ($assinatura->status === 'cancelada') {
            throw new BusinessRuleException('Não é possível renovar uma assinatura cancelada.');
        }

        // Carregar plano
        $plano = $assinatura->plano;
        if (!$plano) {
            throw new NotFoundException('Plano da assinatura não encontrado.');
        }

        if (!$plano->isAtivo()) {
            throw new BusinessRuleException('O plano da assinatura não está mais ativo.');
        }

        // Calcular valor baseado no período
        $valor = $meses === 12 ? $plano->preco_anual : $plano->preco_mensal;
        
        // Se renovação anual, multiplicar por 12 meses
        if ($meses === 12 && $plano->preco_anual) {
            $valor = $plano->preco_anual;
        } else {
            $valor = $plano->preco_mensal * $meses;
        }

        // Validar valor do pagamento
        if ($paymentRequest->amount->toReais() != $valor) {
            throw new DomainException('O valor do pagamento não corresponde ao valor da renovação.');
        }

        // Gerar chave de idempotência única
        $idempotencyKey = $this->generateIdempotencyKey($assinatura->id, $meses);

        // Log da tentativa de pagamento
        $paymentLog = PaymentLog::create([
            'tenant_id' => $assinatura->tenant_id,
            'plano_id' => $plano->id,
            'valor' => $valor,
            'periodo' => $meses === 12 ? 'anual' : 'mensal',
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'metodo_pagamento' => $paymentRequest->paymentMethodId ?? 'credit_card',
            'dados_requisicao' => [
                'assinatura_id' => $assinatura->id,
                'meses' => $meses,
                'payer_email' => $paymentRequest->payerEmail,
                'description' => $paymentRequest->description,
            ],
        ]);

        try {
            // Processar pagamento
            $paymentResult = $this->paymentProvider->processPayment($paymentRequest, $idempotencyKey);

            // Atualizar log
            $paymentLog->update([
                'external_id' => $paymentResult->externalId,
                'status' => $paymentResult->status,
                'dados_resposta' => [
                    'status' => $paymentResult->status,
                    'payment_method' => $paymentResult->paymentMethod,
                    'error_message' => $paymentResult->errorMessage,
                ],
            ]);

            // Se aprovado, renovar assinatura
            if ($paymentResult->isApproved()) {
                return $this->renovarAssinatura($assinatura, $paymentResult, $meses, $valor);
            }

            // Se pendente (ex: PIX), manter assinatura e aguardar webhook
            if ($paymentResult->isPending()) {
                Log::info('Renovação pendente - aguardando confirmação via webhook', [
                    'assinatura_id' => $assinatura->id,
                    'external_id' => $paymentResult->externalId,
                ]);
                
                // Atualizar observações
                $assinatura->update([
                    'observacoes' => "Renovação pendente - aguardando confirmação de pagamento. ID: {$paymentResult->externalId}",
                ]);

                return $assinatura;
            }

            // Se rejeitado, lançar exceção
            throw new DomainException(
                $paymentResult->errorMessage ?? 'Pagamento rejeitado pelo gateway.'
            );

        } catch (\Exception $e) {
            // Atualizar log com erro
            $paymentLog->update([
                'status' => 'failed',
                'dados_resposta' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            Log::error('Erro ao renovar assinatura', [
                'assinatura_id' => $assinatura->id,
                'meses' => $meses,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Renova a assinatura após pagamento aprovado
     */
    private function renovarAssinatura(
        Assinatura $assinatura,
        PaymentResult $paymentResult,
        int $meses,
        float $valor
    ): Assinatura {
        return DB::transaction(function () use ($assinatura, $paymentResult, $meses, $valor) {
            // Calcular nova data de vencimento
            $dataFimAtual = Carbon::parse($assinatura->data_fim);
            
            // Se a assinatura já expirou, começar de hoje
            // Se não, estender a partir da data de vencimento atual
            if ($dataFimAtual->isPast()) {
                $novaDataFim = Carbon::now()->addMonths($meses);
            } else {
                $novaDataFim = $dataFimAtual->copy()->addMonths($meses);
            }

            // Atualizar assinatura
            $assinatura->update([
                'data_fim' => $novaDataFim,
                'status' => 'ativa',
                'data_cancelamento' => null,
                'valor_pago' => $valor,
                'metodo_pagamento' => $paymentResult->paymentMethod,
                'transacao_id' => $paymentResult->externalId,
                'observacoes' => "Renovada por {$meses} " . ($meses === 1 ? 'mês' : 'meses') . " em " . Carbon::now()->format('d/m/Y H:i'),
            ]);

            // Atualizar tenant para garantir que está vinculado à assinatura
            $tenant = $assinatura->tenant;
            if ($tenant) {
                $tenant->update([
                    'plano_atual_id' => $assinatura->plano_id,
                    'assinatura_atual_id' => $assinatura->id,
                ]);
            }

            Log::info('Assinatura renovada com sucesso', [
                'assinatura_id' => $assinatura->id,
                'meses' => $meses,
                'nova_data_fim' => $novaDataFim->format('Y-m-d'),
                'external_id' => $paymentResult->externalId,
            ]);

            return $assinatura->fresh();
        });
    }

    /**
     * Gera chave de idempotência única para renovação
     */
    private function generateIdempotencyKey(int $assinaturaId, int $meses): string
    {
        return 'renewal_' . $assinaturaId . '_' . $meses . '_' . time() . '_' . uniqid();
    }
}




