<?php

namespace App\Application\FormacaoPreco\DTOs;

/**
 * DTO para criação de formação de preço
 */
class CriarFormacaoPrecoDTO
{
    public function __construct(
        public readonly ?int $processoItemId = null,
        public readonly ?int $orcamentoId = null,
        public readonly ?int $orcamentoItemId = null,
        public readonly float $custoProduto = 0.0,
        public readonly float $frete = 0.0,
        public readonly float $percentualImpostos = 0.0,
        public readonly float $valorImpostos = 0.0,
        public readonly float $percentualMargem = 0.0,
        public readonly float $valorMargem = 0.0,
        public readonly float $precoMinimo = 0.0,
        public readonly float $precoRecomendado = 0.0,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            processoItemId: $data['processo_item_id'] ?? $data['processoItemId'] ?? null,
            orcamentoId: $data['orcamento_id'] ?? $data['orcamentoId'] ?? null,
            orcamentoItemId: $data['orcamento_item_id'] ?? $data['orcamentoItemId'] ?? null,
            custoProduto: (float) ($data['custo_produto'] ?? $data['custoProduto'] ?? 0),
            frete: (float) ($data['frete'] ?? $data['frete'] ?? 0),
            percentualImpostos: (float) ($data['percentual_impostos'] ?? $data['percentualImpostos'] ?? 0),
            valorImpostos: (float) ($data['valor_impostos'] ?? $data['valorImpostos'] ?? 0),
            percentualMargem: (float) ($data['percentual_margem'] ?? $data['percentualMargem'] ?? 0),
            valorMargem: (float) ($data['valor_margem'] ?? $data['valorMargem'] ?? 0),
            precoMinimo: (float) ($data['preco_minimo'] ?? $data['precoMinimo'] ?? 0),
            precoRecomendado: (float) ($data['preco_recomendado'] ?? $data['precoRecomendado'] ?? 0),
            observacoes: $data['observacoes'] ?? null,
        );
    }
}



