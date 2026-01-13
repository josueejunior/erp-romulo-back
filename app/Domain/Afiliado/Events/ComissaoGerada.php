<?php

declare(strict_types=1);

namespace App\Domain\Afiliado\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Domain Event: ComissaoGerada
 * Disparado quando uma comissão de afiliado é gerada
 */
readonly class ComissaoGerada implements DomainEvent
{
    public function __construct(
        public int $comissaoId,
        public int $afiliadoId,
        public int $tenantId,
        public ?int $assinaturaId,
        public float $valor,
        public string $tipo, // 'inicial', 'recorrente'
        public string $status, // 'pendente', 'paga', 'cancelada'
        public ?string $periodoCompetencia = null,
        public DateTimeImmutable $ocorreuEm = new DateTimeImmutable()
    ) {}

    public function ocorreuEm(): DateTimeImmutable
    {
        return $this->ocorreuEm;
    }

    public function agregadoId(): string
    {
        return (string) $this->comissaoId;
    }

    public function isPendente(): bool
    {
        return $this->status === 'pendente';
    }
}





