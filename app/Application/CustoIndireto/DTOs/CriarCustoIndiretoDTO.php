<?php

namespace App\Application\CustoIndireto\DTOs;

use Carbon\Carbon;

/**
 * DTO para criação de custo indireto
 */
class CriarCustoIndiretoDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly string $descricao,
        public readonly ?Carbon $data = null,
        public readonly float $valor = 0.0,
        public readonly ?string $categoria = null,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            descricao: $data['descricao'] ?? '',
            data: isset($data['data']) ? Carbon::parse($data['data']) : null,
            valor: (float) ($data['valor'] ?? 0),
            categoria: $data['categoria'] ?? null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}



