<?php

namespace App\Domain\Setor\Entities;

use DomainException;

/**
 * Entidade Setor - Representa um setor de um órgão no domínio
 */
class Setor
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?int $orgaoId,
        public readonly string $nome,
        public readonly ?string $email = null,
        public readonly ?string $telefone = null,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if (empty(trim($this->nome))) {
            throw new DomainException('O nome do setor é obrigatório.');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }
    }
}


