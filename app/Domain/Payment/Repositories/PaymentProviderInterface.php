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

    /**
     * Cria um Customer no gateway e salva o cartão
     * 
     * 🔥 MELHORIA: External Vaulting - Salva apenas customer_id e card_id (não são dados sensíveis)
     * 
     * @param string $email Email do cliente
     * @param string $cardToken Token do cartão (gerado pelo frontend)
     * @param string|null $cpf CPF do cliente (opcional)
     * @param string|null $existingCustomerId Customer já vinculado (ex.: assinatura) — só anexa o cartão
     * @return array ['customer_id' => string, 'card_id' => string]
     * @throws \App\Domain\Exceptions\DomainException Em caso de erro
     */
    public function createCustomerAndCard(string $email, string $cardToken, ?string $cpf = null, ?string $existingCustomerId = null): array;

    /**
     * Metadados não sensíveis do cartão salvo no gateway (últimos dígitos, bandeira) para exibição na UI.
     *
     * @return array{
     *   ultimos_quatro?: string|null,
     *   payment_method_id?: string|null,
     *   payment_method_name?: string|null,
     *   expiration_month?: int|null,
     *   expiration_year?: int|null
     * }|null
     */
    public function getSavedCardSummary(?string $customerId, ?string $cardId): ?array;

    /**
     * Processa um pagamento usando um card_id salvo (one-click buy)
     * 
     * @param PaymentRequest $request Dados do pagamento (sem cardToken, usar card_id)
     * @param string $customerId ID do Customer no gateway
     * @param string $cardId ID do Cartão salvo no gateway
     * @param string $idempotencyKey Chave de idempotência
     * @return PaymentResult Resultado do pagamento
     * @throws \App\Domain\Exceptions\DomainException Em caso de erro
     */
    public function processPaymentWithSavedCard(
        PaymentRequest $request,
        string $customerId,
        string $cardId,
        string $idempotencyKey
    ): PaymentResult;
}




