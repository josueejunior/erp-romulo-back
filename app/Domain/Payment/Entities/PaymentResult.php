<?php

namespace App\Domain\Payment\Entities;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Entidade que representa o resultado de um pagamento
 * 
 * Imutável (readonly) para garantir integridade
 */
readonly class PaymentResult
{
    public function __construct(
        public string $externalId, // ID no gateway (ex: Mercado Pago)
        public string $status, // 'pending', 'approved', 'rejected', 'cancelled', 'refunded'
        public Money $amount,
        public string $paymentMethod, // 'credit_card', 'debit_card', 'pix', 'boleto'
        public ?string $description = null,
        public ?string $payerEmail = null,
        public ?string $payerCpf = null,
        public ?string $transactionId = null, // ID interno da transação
        public ?string $errorMessage = null,
        public ?array $metadata = null,
        public ?\DateTimeInterface $createdAt = null,
        public ?\DateTimeInterface $approvedAt = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $validStatuses = ['pending', 'approved', 'rejected', 'cancelled', 'refunded', 'in_process', 'in_mediation'];
        if (!in_array($this->status, $validStatuses)) {
            throw new \App\Domain\Exceptions\DomainException("Status de pagamento inválido: {$this->status}");
        }
    }

    /**
     * Verifica se o pagamento foi aprovado
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Verifica se o pagamento está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'in_process';
    }

    /**
     * Verifica se o pagamento foi rejeitado
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Cria PaymentResult a partir de array (útil para webhooks)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['external_id'] ?? $data['id'] ?? '',
            status: $data['status'] ?? 'pending',
            amount: Money::fromReais($data['amount'] ?? $data['transaction_amount'] ?? 0),
            paymentMethod: $data['payment_method'] ?? $data['payment_method_id'] ?? 'unknown',
            description: $data['description'] ?? null,
            payerEmail: $data['payer']['email'] ?? $data['payer_email'] ?? null,
            payerCpf: $data['payer']['identification']['number'] ?? $data['payer_cpf'] ?? null,
            transactionId: $data['transaction_id'] ?? null,
            errorMessage: $data['error_message'] ?? $data['status_detail'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['date_created']) ? new \DateTime($data['date_created']) : null,
            approvedAt: isset($data['date_approved']) ? new \DateTime($data['date_approved']) : null,
        );
    }
}

