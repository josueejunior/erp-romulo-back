<?php

namespace App\Domain\Shared\ValueObjects;

use DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Value Object: Senha
 * Garante que senhas sejam sempre seguras e hasheadas
 */
readonly class Senha
{
    public function __construct(
        public string $hash
    ) {
        // Hash já deve estar feito, apenas validar que não está vazio
        if (empty(trim($this->hash))) {
            throw new DomainException('A senha não pode estar vazia.');
        }
    }

    /**
     * Criar Senha a partir de senha em texto plano
     * Valida força da senha antes de hash
     */
    public static function fromPlainText(string $plainText, bool $validateStrength = true): self
    {
        if (empty(trim($plainText))) {
            throw new DomainException('A senha não pode estar vazia.');
        }

        if ($validateStrength) {
            self::validateStrength($plainText);
        }

        return new self(Hash::make($plainText));
    }

    /**
     * Validar força da senha
     */
    private static function validateStrength(string $senha): void
    {
        if (strlen($senha) < 8) {
            throw new DomainException('A senha deve ter no mínimo 8 caracteres.');
        }

        if (!preg_match('/[a-z]/', $senha)) {
            throw new DomainException('A senha deve conter pelo menos uma letra minúscula.');
        }

        if (!preg_match('/[A-Z]/', $senha)) {
            throw new DomainException('A senha deve conter pelo menos uma letra maiúscula.');
        }

        if (!preg_match('/[0-9]/', $senha)) {
            throw new DomainException('A senha deve conter pelo menos um número.');
        }

        if (!preg_match('/[@$!%*?&]/', $senha)) {
            throw new DomainException('A senha deve conter pelo menos um caractere especial (@$!%*?&).');
        }
    }

    /**
     * Verificar se senha em texto plano corresponde ao hash
     */
    public function verificar(string $plainText): bool
    {
        return Hash::check($plainText, $this->hash);
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}

