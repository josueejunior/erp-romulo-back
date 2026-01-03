<?php

namespace App\Domain\Orgao\Entities;

use DomainException;

/**
 * Entidade Orgao - Representa um órgão público no domínio
 */
class Orgao
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?string $uasg = null,
        public readonly ?string $razaoSocial = null,
        public readonly ?string $cnpj = null,
        public readonly ?string $cep = null,
        public readonly ?string $logradouro = null,
        public readonly ?string $numero = null,
        public readonly ?string $bairro = null,
        public readonly ?string $complemento = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $email = null,
        public readonly ?string $telefone = null,
        public readonly ?array $emails = null,
        public readonly ?array $telefones = null,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if (empty($this->uasg) && empty($this->razaoSocial)) {
            throw new DomainException('O órgão deve ter UASG ou razão social.');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }

        if ($this->estado !== null && strlen($this->estado) !== 2) {
            throw new DomainException('O estado deve ter exatamente 2 caracteres.');
        }
    }
}



