<?php

namespace App\Domain\Auth\Entities;

use App\Domain\Exceptions\DomainException;

/**
 * Entidade User - Representa um usuário no domínio
 */
class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $nome,
        public readonly string $email,
        public readonly string $senhaHash,
        public readonly ?int $empresaAtivaId = null,
    ) {
        $this->validate();
    }

    /**
     * Validações de negócio da entidade User
     */
    private function validate(): void
    {
        if (empty(trim($this->nome))) {
            throw new DomainException('O nome do usuário é obrigatório.');
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }

        if (empty($this->senhaHash)) {
            throw new DomainException('A senha é obrigatória.');
        }
    }
}




