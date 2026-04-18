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
        public readonly Carbon $data,
        public readonly float $valor,
        public readonly ?string $categoria = null,
        public readonly ?string $observacoes = null,
    ) {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('empresaId é obrigatório e deve ser maior que zero.');
        }
        
        if (empty(trim($descricao))) {
            throw new \InvalidArgumentException('descricao é obrigatória.');
        }
        
        if ($valor < 0) {
            throw new \InvalidArgumentException('valor não pode ser negativo.');
        }
    }

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data, int $empresaId): self
    {
        return new self(
            empresaId: $empresaId,
            descricao: $data['descricao'] ?? '',
            data: isset($data['data']) ? Carbon::parse($data['data']) : Carbon::now(),
            valor: (float) ($data['valor'] ?? 0),
            categoria: $data['categoria'] ?? null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

