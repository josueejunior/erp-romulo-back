<?php

namespace App\Domain\Payment\ValueObjects;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Value Object para requisição de pagamento
 * 
 * Encapsula todos os dados necessários para processar um pagamento
 */
readonly class PaymentRequest
{
    public function __construct(
        public Money $amount,
        public string $description,
        public string $payerEmail,
        public ?string $payerCpf = null,
        public ?string $cardToken = null, // Token do cartão (MercadoPago.js)
        public int $installments = 1,
        public ?string $paymentMethodId = null, // 'credit_card', 'debit_card', 'pix', etc
        public ?string $externalReference = null, // Referência externa (ex: tenant_id)
        public ?array $metadata = null, // Metadados adicionais
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->amount->cents <= 0) {
            throw new \App\Domain\Exceptions\DomainException('O valor do pagamento deve ser maior que zero.');
        }

        if (empty(trim($this->description))) {
            throw new \App\Domain\Exceptions\DomainException('A descrição do pagamento é obrigatória.');
        }

        if (!filter_var($this->payerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \App\Domain\Exceptions\DomainException('O e-mail do pagador é inválido.');
        }

        // Validações específicas por método de pagamento
        $isPix = $this->paymentMethodId === 'pix';
        $isCartao = !$isPix && $this->cardToken !== null;

        // Para cartão de crédito, token é obrigatório
        if ($isCartao && empty($this->cardToken)) {
            throw new \App\Domain\Exceptions\DomainException('Token do cartão é obrigatório para pagamento com cartão.');
        }

        // Para PIX, não pode ter token
        if ($isPix && !empty($this->cardToken)) {
            throw new \App\Domain\Exceptions\DomainException('Token do cartão não deve ser enviado para pagamento PIX.');
        }

        // Parcelas só fazem sentido para cartão
        if ($isPix && $this->installments > 1) {
            throw new \App\Domain\Exceptions\DomainException('Parcelas não são permitidas para pagamento PIX.');
        }

        if ($this->installments < 1 || $this->installments > 12) {
            throw new \App\Domain\Exceptions\DomainException('O número de parcelas deve estar entre 1 e 12.');
        }
    }

    /**
     * Cria PaymentRequest a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: Money::fromReais($data['amount'] ?? 0),
            description: $data['description'] ?? '',
            payerEmail: $data['payer_email'] ?? '',
            payerCpf: $data['payer_cpf'] ?? null,
            cardToken: $data['card_token'] ?? null,
            installments: $data['installments'] ?? 1,
            paymentMethodId: $data['payment_method_id'] ?? null,
            externalReference: $data['external_reference'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}

