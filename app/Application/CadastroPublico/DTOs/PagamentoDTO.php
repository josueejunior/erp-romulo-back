<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\DTOs;

/**
 * DTO para dados de pagamento no cadastro público
 */
final class PagamentoDTO
{
    public function __construct(
        public readonly string $metodo, // 'credit_card' ou 'pix'
        public readonly string $payerEmail,
        public readonly ?string $payerCpf = null,
        public readonly ?string $cardToken = null, // Token do cartão (gerado via Mercado Pago)
        public readonly int $installments = 1, // Parcelas (apenas para cartão)
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            metodo: $data['payment_method'],
            payerEmail: $data['payer_email'],
            payerCpf: isset($data['payer_cpf']) ? preg_replace('/\D/', '', $data['payer_cpf']) : null,
            cardToken: $data['card_token'] ?? null,
            installments: isset($data['installments']) ? (int) $data['installments'] : 1,
        );
    }

    public function isCreditCard(): bool
    {
        return $this->metodo === 'credit_card';
    }

    public function isPix(): bool
    {
        return $this->metodo === 'pix';
    }
}



