<?php

namespace App\Application\DocumentoHabilitacao\DTOs;

use Carbon\Carbon;

/**
 * DTO para criação de documento de habilitação
 */
class CriarDocumentoHabilitacaoDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?string $tipo = null,
        public readonly ?string $numero = null,
        public readonly ?string $identificacao = null,
        public readonly ?Carbon $dataEmissao = null,
        public readonly ?Carbon $dataValidade = null,
        public readonly ?string $arquivo = null,
        public readonly bool $ativo = true,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            tipo: $data['tipo'] ?? null,
            numero: $data['numero'] ?? null,
            identificacao: $data['identificacao'] ?? null,
            dataEmissao: isset($data['data_emissao']) ? Carbon::parse($data['data_emissao']) : null,
            dataValidade: isset($data['data_validade']) ? Carbon::parse($data['data_validade']) : null,
            arquivo: $data['arquivo'] ?? null,
            ativo: $data['ativo'] ?? true,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

