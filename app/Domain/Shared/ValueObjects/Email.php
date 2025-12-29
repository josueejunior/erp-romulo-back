<?php

namespace App\Domain\Shared\ValueObjects;

use DomainException;

/**
 * Value Object: Email
 * Garante que emails sejam sempre válidos e consistentes
 */
readonly class Email
{
    public function __construct(
        public string $value
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty(trim($this->value))) {
            throw new DomainException('O e-mail não pode estar vazio.');
        }

        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }
    }

    /**
     * Factory method para criar Email já normalizado
     */
    public static function criar(string $email): self
    {
        return new self(strtolower(trim($email)));
    }

    public function equals(Email $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
