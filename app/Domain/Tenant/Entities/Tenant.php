<?php

namespace App\Domain\Tenant\Entities;

use DomainException;

/**
 * Entidade Tenant - Representa uma empresa/tenant no domínio
 * Contém apenas regras de negócio, sem dependências de infraestrutura
 */
class Tenant
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $razaoSocial,
        public readonly ?string $cnpj,
        public readonly ?string $email,
        public readonly string $status = 'ativa',
        public readonly ?string $endereco = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $cep = null,
        public readonly ?array $telefones = null,
        public readonly ?array $emailsAdicionais = null,
        public readonly ?string $banco = null,
        public readonly ?string $agencia = null,
        public readonly ?string $conta = null,
        public readonly ?string $tipoConta = null,
        public readonly ?string $pix = null,
        public readonly ?string $representanteLegalNome = null,
        public readonly ?string $representanteLegalCpf = null,
        public readonly ?string $representanteLegalCargo = null,
        public readonly ?string $logo = null,
    ) {
        $this->validate();
    }

    /**
     * Validações de negócio da entidade Tenant
     */
    private function validate(): void
    {
        if (empty(trim($this->razaoSocial))) {
            throw new DomainException('A razão social é obrigatória.');
        }

        if (strlen($this->razaoSocial) > 255) {
            throw new DomainException('A razão social não pode ter mais de 255 caracteres.');
        }

        if ($this->cnpj !== null && strlen($this->cnpj) > 18) {
            throw new DomainException('O CNPJ não pode ter mais de 18 caracteres.');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }

        if (!in_array($this->status, ['ativa', 'inativa'])) {
            throw new DomainException('O status deve ser "ativa" ou "inativa".');
        }

        if ($this->estado !== null && strlen($this->estado) !== 2) {
            throw new DomainException('O estado deve ter exatamente 2 caracteres.');
        }
    }

    /**
     * Regra de negócio: CNPJ não pode ser alterado se já existe um definido
     */
    public function podeAlterarCnpj(?string $novoCnpj): bool
    {
        if ($this->cnpj && $novoCnpj && $novoCnpj !== $this->cnpj) {
            return false;
        }
        
        return true;
    }

    /**
     * Regra de negócio: Inativar tenant
     */
    public function inativar(): self
    {
        if ($this->status === 'inativa') {
            return $this;
        }

        return new self(
            id: $this->id,
            razaoSocial: $this->razaoSocial,
            cnpj: $this->cnpj,
            email: $this->email,
            status: 'inativa',
            endereco: $this->endereco,
            cidade: $this->cidade,
            estado: $this->estado,
            cep: $this->cep,
            telefones: $this->telefones,
            emailsAdicionais: $this->emailsAdicionais,
            banco: $this->banco,
            agencia: $this->agencia,
            conta: $this->conta,
            tipoConta: $this->tipoConta,
            pix: $this->pix,
            representanteLegalNome: $this->representanteLegalNome,
            representanteLegalCpf: $this->representanteLegalCpf,
            representanteLegalCargo: $this->representanteLegalCargo,
            logo: $this->logo,
        );
    }

    /**
     * Regra de negócio: Reativar tenant
     */
    public function reativar(): self
    {
        return new self(
            id: $this->id,
            razaoSocial: $this->razaoSocial,
            cnpj: $this->cnpj,
            email: $this->email,
            status: 'ativa',
            endereco: $this->endereco,
            cidade: $this->cidade,
            estado: $this->estado,
            cep: $this->cep,
            telefones: $this->telefones,
            emailsAdicionais: $this->emailsAdicionais,
            banco: $this->banco,
            agencia: $this->agencia,
            conta: $this->conta,
            tipoConta: $this->tipoConta,
            pix: $this->pix,
            representanteLegalNome: $this->representanteLegalNome,
            representanteLegalCpf: $this->representanteLegalCpf,
            representanteLegalCargo: $this->representanteLegalCargo,
            logo: $this->logo,
        );
    }

    /**
     * Verifica se o tenant está ativo
     */
    public function estaAtivo(): bool
    {
        return $this->status === 'ativa';
    }
}

