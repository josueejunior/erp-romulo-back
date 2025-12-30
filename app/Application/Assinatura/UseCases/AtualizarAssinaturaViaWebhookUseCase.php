<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Payment\Repositories\PaymentLogRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Atualizar Assinatura via Webhook
 */
class AtualizarAssinaturaViaWebhookUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PaymentLogRepositoryInterface $paymentLogRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param string $transacaoId ID da transação no gateway
     * @param PaymentResult $paymentResult Resultado do pagamento do webhook
     * @return void
     */
    public function executar(string $transacaoId, PaymentResult $paymentResult): void
    {
        // Buscar assinatura pelo external_id usando repository DDD
        $assinatura = $this->assinaturaRepository->buscarModeloPorTransacaoId($transacaoId);

        if (!$assinatura) {
            throw new NotFoundException("Assinatura não encontrada para transação: {$transacaoId}");
        }

        DB::transaction(function () use ($assinatura, $paymentResult) {
            if ($paymentResult->isApproved() && $assinatura->status !== 'ativa') {
                // Ativar assinatura
                $assinatura->update([
                    'status' => 'ativa',
                    'data_inicio' => $paymentResult->approvedAt ?? now(),
                ]);

                // Atualizar tenant
                $tenant = $assinatura->tenant;
                if ($tenant) {
                    $tenant->update([
                        'plano_atual_id' => $assinatura->plano_id,
                        'assinatura_atual_id' => $assinatura->id,
                    ]);
                }

                Log::info('Assinatura ativada via webhook', [
                    'assinatura_id' => $assinatura->id,
                    'external_id' => $transacaoId,
                ]);
            } elseif ($paymentResult->isRejected()) {
                // Marcar como suspensa se rejeitado
                $assinatura->update([
                    'status' => 'suspensa',
                    'observacoes' => ($assinatura->observacoes ?? '') . "\nPagamento rejeitado via webhook em " . now()->format('d/m/Y H:i:s') . ": {$paymentResult->errorMessage}",
                ]);

                Log::warning('Assinatura suspensa via webhook (pagamento rejeitado)', [
                    'assinatura_id' => $assinatura->id,
                    'external_id' => $transacaoId,
                    'error' => $paymentResult->errorMessage,
                ]);
            } elseif ($paymentResult->isPending()) {
                // PIX ou outro método pendente - apenas logar
                Log::info('Pagamento pendente via webhook', [
                    'assinatura_id' => $assinatura->id,
                    'external_id' => $transacaoId,
                    'status' => $paymentResult->status,
                ]);
            }

            // Atualizar log de pagamento usando repository DDD
            $paymentLog = $this->paymentLogRepository->buscarPorExternalId($transacaoId);
            if ($paymentLog) {
                $this->paymentLogRepository->criarOuAtualizar([
                    'external_id' => $transacaoId,
                    'status' => $paymentResult->status,
                    'dados_resposta' => array_merge($paymentLog->dados_resposta ?? [], [
                        'webhook_status' => $paymentResult->status,
                        'webhook_received_at' => now()->toIso8601String(),
                    ]),
                ]);
            }
        });
    }
}

