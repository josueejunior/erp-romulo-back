<?php

namespace App\Application\Orgao\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para atualização de órgão
 */
class AtualizarOrgaoDTO
{
    public function __construct(
        public readonly int $orgaoId,
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

    public static function fromRequest(Request $request, int $id): self
    {
        $validated = $request->validated();
        
        return new self(
            orgaoId: $id,
            uasg: $validated['uasg'] ?? null,
            razaoSocial: $validated['razao_social'] ?? $validated['razaoSocial'] ?? null,
            cnpj: $validated['cnpj'] ?? null,
            cep: $validated['cep'] ?? null,
            logradouro: $validated['logradouro'] ?? null,
            numero: $validated['numero'] ?? null,
            bairro: $validated['bairro'] ?? null,
            complemento: $validated['complemento'] ?? null,
            cidade: $validated['cidade'] ?? null,
            estado: $validated['estado'] ?? null,
            email: $validated['email'] ?? null,
            telefone: $validated['telefone'] ?? null,
            emails: $validated['emails'] ?? null,
            telefones: $validated['telefones'] ?? null,
            observacoes: $validated['observacoes'] ?? null,
        );
    }
}



