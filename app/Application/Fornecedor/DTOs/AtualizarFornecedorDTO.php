<?php

namespace App\Application\Fornecedor\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para atualização de fornecedor
 */
class AtualizarFornecedorDTO
{
    public function __construct(
        public readonly int $fornecedorId,
        public readonly ?string $razaoSocial = null,
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
        public readonly ?bool $isTransportadora = null,
    ) {}

    /**
     * Criar DTO a partir de Request
     */
    public static function fromRequest(Request $request, int $fornecedorId): self
    {
        return new self(
            fornecedorId: $fornecedorId,
            razaoSocial: $request->input('razao_social'),
            cnpj: $request->input('cnpj'),
            nomeFantasia: $request->input('nome_fantasia'),
            cep: $request->input('cep'),
            logradouro: $request->input('logradouro'),
            numero: $request->input('numero'),
            bairro: $request->input('bairro'),
            complemento: $request->input('complemento'),
            cidade: $request->input('cidade'),
            estado: $request->input('estado'),
            email: $request->input('email'),
            telefone: $request->input('telefone'),
            emails: $request->input('emails'),
            telefones: $request->input('telefones'),
            contato: $request->input('contato'),
            observacoes: $request->input('observacoes'),
            isTransportadora: $request->input('is_transportadora'),
        );
    }
}

