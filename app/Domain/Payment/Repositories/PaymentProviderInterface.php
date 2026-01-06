<?php

namespace App\Domain\Payment\Repositories;

use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Payment\ValueObjects\PaymentRequest;

/**
 * Interface para provedores de pagamento (Gateway Pattern)
 * 
 * Permite trocar de gateway (Mercado Pago, Stripe, Pagar.me) sem quebrar o código
 */
interface PaymentProviderInterface
{
    /**
     * Processa um pagamento
     * 
     * @param PaymentRequest $request Dados do pagamento
     * @param string $idempotencyKey Chave de idempotência para evitar cobranças duplicadas
     * @return PaymentResult Resultado do pagamento
     * @throws \App\Domain\Exceptions\DomainException Em caso de erro
     */
    public function processPayment(PaymentRequest $request, string $idempotencyKey): PaymentResult;

    /**
     * Consulta o status de um pagamento
     * 
     * @param string $externalId ID da transação no gateway externo
     * @return PaymentResult Status atualizado do pagamento
     * @throws \App\Domain\Exceptions\NotFoundException Se o pagamento não for encontrado
     */
    public function getPaymentStatus(string $externalId): PaymentResult;

    /**
     * Processa um webhook do gateway
     * 
     * @param array $payload Dados recebidos do webhook
     * @return PaymentResult Resultado processado
     */
    public function processWebhook(array $payload): PaymentResult;

    /**
     * Valida a assinatura de um webhook (segurança)
     * 
     * @param array $payload Dados do webhook
     * @param string $signature Assinatura recebida
     * @return bool True se válido
     */
    public function validateWebhookSignature(array $payload, string $signature): bool;
}



