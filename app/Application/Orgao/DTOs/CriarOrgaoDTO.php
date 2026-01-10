<?php

namespace App\Application\Orgao\DTOs;

/**
 * DTO para criação de órgão
 */
class CriarOrgaoDTO
{
    public function __construct(
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
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            uasg: $data['uasg'] ?? null,
            razaoSocial: $data['razao_social'] ?? $data['razaoSocial'] ?? null,
            cnpj: $data['cnpj'] ?? null,
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
            observacoes: $data['observacoes'] ?? null,
        );
    }
}




