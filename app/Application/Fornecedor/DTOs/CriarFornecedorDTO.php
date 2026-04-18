<?php

namespace App\Application\Fornecedor\DTOs;

/**
 * DTO para criação de fornecedor
 * Transporta dados entre camadas sem expor entidades
 */
class CriarFornecedorDTO
{
    public function __construct(
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
    ) {}

    /**
     * Criar DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            razaoSocial: $data['razao_social'] ?? $data['razaoSocial'] ?? '',
            cnpj: $data['cnpj'] ?? null,
            nomeFantasia: $data['nome_fantasia'] ?? $data['nomeFantasia'] ?? null,
            cep: $data['cep'] ?? null,
            logradouro: $data['logradouro'] ?? null,
            numero: $data['numero'] ?? null,
            bairro: $data['bairro'] ?? null,
            complemento: $data['complemento'] ?? null,
            cidade: $data['cidade'] ?? null,
            estado: $data['estado'] ?? null,
            email: $data['email'] ?? null,
            telefone: $data['telefone'] ?? null,
            emails: $data['emails'] ?? null,
            telefones: $data['telefones'] ?? null,
            contato: $data['contato'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            isTransportadora: $data['is_transportadora'] ?? $data['isTransportadora'] ?? false,
        );
    }
}

