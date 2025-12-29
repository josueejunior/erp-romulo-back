<?php

namespace App\Domain\Shared\ValueObjects;

use DomainException;

/**
 * Value Object: Status
 * Garante que status sejam sempre válidos
 */
readonly class Status
{
    public const ATIVA = 'ativa';
    public const INATIVA = 'inativa';
    public const PENDENTE = 'pendente';
    public const CANCELADA = 'cancelada';

    private const VALID_STATUSES = [
        self::ATIVA,
        self::INATIVA,
        self::PENDENTE,
        self::CANCELADA,
    ];

    public function __construct(
        public string $value
    ) {
        $this->validate();
        $this->value = strtolower(trim($this->value));
    }

    private function validate(): void
    {
        if (!in_array(strtolower(trim($this->value)), self::VALID_STATUSES, true)) {
            throw new DomainException(
                'Status inválido. Valores permitidos: ' . implode(', ', self::VALID_STATUSES)
            );
        }
    }

    public function isAtiva(): bool
    {
        return $this->value === self::ATIVA;
    }

    public function isInativa(): bool
    {
        return $this->value === self::INATIVA;
    }

    public function equals(Status $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

