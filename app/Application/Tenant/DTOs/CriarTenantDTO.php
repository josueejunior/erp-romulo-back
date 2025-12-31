<?php

namespace App\Application\Tenant\DTOs;

/**
 * DTO (Data Transfer Object) para criação de tenant
 * Transporta dados entre camadas sem expor entidades do domínio
 */
class CriarTenantDTO
{
    public function __construct(
        public readonly string $razaoSocial,
        public readonly ?string $cnpj = null,
        public readonly ?string $email = null,
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
        // Dados do administrador (opcional)
        public readonly ?string $adminName = null,
        public readonly ?string $adminEmail = null,
        public readonly ?string $adminPassword = null,
    ) {}

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            razaoSocial: $data['razao_social'],
            cnpj: $data['cnpj'] ?? null,
            email: $data['email'] ?? null,
            status: $data['status'] ?? 'ativa',
            endereco: $data['endereco'] ?? null,
            cidade: $data['cidade'] ?? null,
            estado: $data['estado'] ?? null,
            cep: $data['cep'] ?? null,
            telefones: $data['telefones'] ?? null,
            emailsAdicionais: $data['emails_adicionais'] ?? null,
            banco: $data['banco'] ?? null,
            agencia: $data['agencia'] ?? null,
            conta: $data['conta'] ?? null,
            tipoConta: $data['tipo_conta'] ?? null,
            pix: $data['pix'] ?? null,
            representanteLegalNome: $data['representante_legal_nome'] ?? null,
            representanteLegalCpf: $data['representante_legal_cpf'] ?? null,
            representanteLegalCargo: $data['representante_legal_cargo'] ?? null,
            logo: $data['logo'] ?? null,
            adminName: $data['admin_name'] ?? null,
            adminEmail: $data['admin_email'] ?? null,
            adminPassword: $data['admin_password'] ?? null,
        );
    }

    /**
     * Verifica se dados do administrador foram fornecidos
     */
    public function temDadosAdmin(): bool
    {
        return !empty($this->adminName) 
            && !empty($this->adminEmail) 
            && !empty($this->adminPassword);
    }
}


