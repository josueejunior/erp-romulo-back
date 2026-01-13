<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain Event: PagamentoProcessado
 * Disparado quando um pagamento é processado (aprovado ou rejeitado)
 */
readonly class PagamentoProcessado implements DomainEvent
{
    public function __construct(
        public int $paymentLogId,
        public int $tenantId,
        public ?int $assinaturaId,
        public int $planoId,
        public string $status, // 'approved', 'rejected', 'pending', 'failed'
        public float $valor,
        public string $metodoPagamento,
        public ?string $externalId,
        public string $idempotencyKey,
        public ?int $userId = null,
        public DateTimeImmutable $ocorreuEm = new DateTimeImmutable()
    ) {}

    public function ocorreuEm(): DateTimeImmutable
    {
        return $this->ocorreuEm;
    }

    public function agregadoId(): string
    {
        return (string) $this->paymentLogId;
    }

    public function foiAprovado(): bool
    {
        return $this->status === 'approved';
    }

    public function estaPendente(): bool
    {
        return $this->status === 'pending';
    }
}





