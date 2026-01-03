<?php

namespace App\Domain\Empresa\Entities;

use DomainException;

/**
 * Entidade Empresa - Representa uma empresa dentro de um tenant
 */
class Empresa
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $razaoSocial,
        public readonly ?string $cnpj = null,
        public readonly ?string $email = null,
        public readonly string $status = 'ativa',
        public readonly ?string $endereco = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $cep = null,
        public readonly ?array $telefones = null,
        public readonly ?array $emails = null,
        public readonly ?string $bancoNome = null,
        public readonly ?string $bancoAgencia = null,
        public readonly ?string $bancoConta = null,
        public readonly ?string $bancoTipo = null,
        public readonly ?string $bancoPix = null,
        public readonly ?string $representanteLegal = null,
        public readonly ?string $logo = null,
    ) {
        $this->validate();
    }

    /**
     * Validações de negócio da entidade Empresa
     */
    private function validate(): void
    {
        if (empty(trim($this->razaoSocial))) {
            throw new DomainException('A razão social é obrigatória.');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }

        if (!in_array($this->status, ['ativa', 'inativa'])) {
            throw new DomainException('O status deve ser "ativa" ou "inativa".');
        }
    }

    /**
     * Verifica se a empresa está ativa
     */
    public function estaAtiva(): bool
    {
        return $this->status === 'ativa';
    }
}



