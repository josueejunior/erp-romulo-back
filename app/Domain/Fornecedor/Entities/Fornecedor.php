<?php

namespace App\Domain\Fornecedor\Entities;

use DomainException;

/**
 * Entidade Fornecedor - Representa um fornecedor no domínio
 */
class Fornecedor
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly string $razaoSocial,
        public readonly ?string $cnpj = null,
        public readonly ?string $nomeFantasia = null,
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
        public readonly ?string $contato = null,
        public readonly ?string $observacoes = null,
        public readonly bool $isTransportadora = false,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty(trim($this->razaoSocial))) {
            throw new DomainException('A razão social é obrigatória.');
        }

        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }

        if ($this->estado !== null && strlen($this->estado) !== 2) {
            throw new DomainException('O estado deve ter exatamente 2 caracteres.');
        }
    }
}


