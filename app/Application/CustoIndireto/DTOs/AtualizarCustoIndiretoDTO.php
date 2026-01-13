<?php

namespace App\Application\CustoIndireto\DTOs;

use Carbon\Carbon;

/**
 * DTO para atualização de custo indireto
 */
class AtualizarCustoIndiretoDTO
{
    public function __construct(
        public readonly int $custoIndiretoId,
        public readonly int $empresaId,
        public readonly ?string $descricao = null,
        public readonly ?Carbon $data = null,
        public readonly ?float $valor = null,
        public readonly ?string $categoria = null,
        public readonly ?string $observacoes = null,
    ) {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('empresaId é obrigatório e deve ser maior que zero.');
        }
        
        if ($valor !== null && $valor < 0) {
            throw new \InvalidArgumentException('valor não pode ser negativo.');
        }
    }

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data, int $custoIndiretoId, int $empresaId): self
    {
        return new self(
            custoIndiretoId: $custoIndiretoId,
            empresaId: $empresaId,
            descricao: $data['descricao'] ?? null,
            data: isset($data['data']) && $data['data'] ? Carbon::parse($data['data']) : null,
            valor: isset($data['valor']) ? (float) $data['valor'] : null,
            categoria: $data['categoria'] ?? null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}








