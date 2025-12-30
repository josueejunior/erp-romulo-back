<?php

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Exceptions\DomainException;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoClient;

/**
 * Implementação do gateway Mercado Pago
 * 
 * Infrastructure Layer - Responsável pela comunicação com o Mercado Pago
 * Compatível com SDK versão 3.8.0+
 */
class MercadoPagoGateway implements PaymentProviderInterface
{
    private string $accessToken;
    private bool $isSandbox;
    private PaymentClient $paymentClient;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        $this->isSandbox = config('services.mercadopago.sandbox', true);

        if (empty($this->accessToken)) {
            throw new DomainException('Mercado Pago access token não configurado.');
        }

        // Inicializar SDK do Mercado Pago (versão 3.8.0+)
        MercadoPagoConfig::setAccessToken($this->accessToken);
        
        // Criar cliente de pagamento
        $this->paymentClient = new PaymentClient();
    }

    /**
     * Processa um pagamento
     */
    public function processPayment(PaymentRequest $request, string $idempotencyKey): PaymentResult
    {
        try {
            // Preparar dados do pagamento para a nova API
            $paymentData = [
                'transaction_amount' => $request->amount->toReais(),
                'description' => $request->description,
                'installments' => $request->installments ?? 1,
                'payment_method_id' => $request->paymentMethodId ?? 'credit_card',
                'payer' => [
                    'email' => $request->payerEmail,
                ],
                'external_reference' => $request->externalReference ?? $idempotencyKey,
            ];

            // Token do cartão (obtido via MercadoPago.js no frontend)
            if ($request->cardToken) {
                $paymentData['token'] = $request->cardToken;
            }

            // CPF do pagador
            if ($request->payerCpf) {
                $paymentData['payer']['identification'] = [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D/', '', $request->payerCpf),
                ];
            }

            // Metadados
            if ($request->metadata) {
                $paymentData['metadata'] = $request->metadata;
            }

            // Criar pagamento usando a nova API
            $payment = $this->paymentClient->create($paymentData);

            // Verificar erros
            if (isset($payment['error'])) {
                $errorMessage = $payment['error']['message'] ?? 'Erro desconhecido no pagamento';
                Log::error('Erro ao processar pagamento no Mercado Pago', [
                    'error' => $payment['error'],
                    'idempotency_key' => $idempotencyKey,
                    'request' => $request,
                ]);

                throw new DomainException("Erro no pagamento: {$errorMessage}");
            }

            // Retornar resultado
            return $this->mapPaymentToResult($payment);

        } catch (\Exception $e) {
            Log::error('Exceção ao processar pagamento no Mercado Pago', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'idempotency_key' => $idempotencyKey,
            ]);

            throw new DomainException("Erro ao processar pagamento: {$e->getMessage()}");
        }
    }

    /**
     * Consulta o status de um pagamento
     */
    public function getPaymentStatus(string $externalId): PaymentResult
    {
        try {
            $payment = $this->paymentClient->get($externalId);

            if (!$payment || isset($payment['error'])) {
                throw new NotFoundException("Pagamento não encontrado: {$externalId}");
            }

            return $this->mapPaymentToResult($payment);

        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erro ao consultar status do pagamento no Mercado Pago', [
                'external_id' => $externalId,
                'exception' => $e->getMessage(),
            ]);

            throw new DomainException("Erro ao consultar pagamento: {$e->getMessage()}");
        }
    }

    /**
     * Processa um webhook do Mercado Pago
     */
    public function processWebhook(array $payload): PaymentResult
    {
        try {
            // O Mercado Pago envia o tipo de evento e o ID do pagamento
            $type = $payload['type'] ?? null;
            $paymentId = $payload['data']['id'] ?? null;

            if (!$paymentId) {
                throw new DomainException('ID do pagamento não encontrado no webhook.');
            }

            // Consultar status atualizado
            return $this->getPaymentStatus($paymentId);

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do Mercado Pago', [
                'payload' => $payload,
                'exception' => $e->getMessage(),
            ]);

            throw new DomainException("Erro ao processar webhook: {$e->getMessage()}");
        }
    }

    /**
     * Valida a assinatura do webhook
     */
    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        // TODO: Implementar validação de assinatura HMAC se necessário
        // Por enquanto, validar apenas se o payload tem estrutura esperada
        return isset($payload['type']) && isset($payload['data']['id']);
    }

    /**
     * Mapeia Payment do Mercado Pago para PaymentResult
     * Adaptado para a nova estrutura da API 3.8.0+
     */
    private function mapPaymentToResult(array $payment): PaymentResult
    {
        $payer = $payment['payer'] ?? [];
        $payerIdentification = $payer['identification'] ?? [];

        return new PaymentResult(
            externalId: (string) ($payment['id'] ?? ''),
            status: $this->mapStatus($payment['status'] ?? 'pending'),
            amount: \App\Domain\Shared\ValueObjects\Money::fromReais($payment['transaction_amount'] ?? 0),
            paymentMethod: $payment['payment_method_id'] ?? 'unknown',
            description: $payment['description'] ?? null,
            payerEmail: $payer['email'] ?? null,
            payerCpf: $payerIdentification['number'] ?? null,
            transactionId: $payment['id'] ?? null,
            errorMessage: $payment['status_detail'] ?? null,
            metadata: $payment['metadata'] ?? null,
            createdAt: isset($payment['date_created']) ? new \DateTime($payment['date_created']) : null,
            approvedAt: isset($payment['date_approved']) ? new \DateTime($payment['date_approved']) : null,
        );
    }

    /**
     * Mapeia status do Mercado Pago para status interno
     */
    private function mapStatus(string $mpStatus): string
    {
        return match($mpStatus) {
            'approved' => 'approved',
            'pending', 'in_process' => 'pending',
            'rejected', 'cancelled' => 'rejected',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }
}
