<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Exceptions\DomainException;

/**
 * Value Object: Money
 * Representa valores monetários de forma segura
 * Evita problemas de precisão com float
 */
readonly class Money
{
    public function __construct(
        public int $cents // Armazenar em centavos (inteiro)
    ) {
        if ($this->cents < 0) {
            throw new DomainException('Valor monetário não pode ser negativo.');
        }
    }

    /**
     * Criar Money a partir de reais (float)
     */
    public static function fromReais(float $reais): self
    {
        return new self((int) round($reais * 100));
    }

    /**
     * Criar Money a partir de centavos (int)
     */
    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Obter valor em reais (float)
     */
    public function toReais(): float
    {
        return $this->cents / 100;
    }

    /**
     * Obter valor formatado (R$ X.XXX,XX)
     */
    public function formatado(): string
    {
        return 'R$ ' . number_format($this->toReais(), 2, ',', '.');
    }

    /**
     * Somar valores
     */
    public function adicionar(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /**
     * Subtrair valores
     */
    public function subtrair(Money $other): self
    {
        $result = $this->cents - $other->cents;
        if ($result < 0) {
            throw new DomainException('Resultado da subtração não pode ser negativo.');
        }
        return new self($result);
    }

    /**
     * Multiplicar por número
     */
    public function multiplicar(float $multiplier): self
    {
        return new self((int) round($this->cents * $multiplier));
    }

    /**
     * Comparar valores
     */
    public function maiorQue(Money $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function menorQue(Money $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function igual(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function __toString(): string
    {
        return $this->formatado();
    }
}



