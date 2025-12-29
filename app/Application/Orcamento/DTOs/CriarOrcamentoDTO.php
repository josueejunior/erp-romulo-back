<?php

namespace App\Application\Orcamento\DTOs;

/**
 * DTO para criação de orçamento
 */
class CriarOrcamentoDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?int $processoId = null,
        public readonly ?int $processoItemId = null,
        public readonly ?int $fornecedorId = null,
        public readonly ?int $transportadoraId = null,
        public readonly float $custoProduto = 0.0,
        public readonly ?string $marcaModelo = null,
        public readonly ?string $ajustesEspecificacao = null,
        public readonly float $frete = 0.0,
        public readonly bool $freteIncluido = false,
        public readonly bool $fornecedorEscolhido = false,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            processoId: $data['processo_id'] ?? $data['processoId'] ?? null,
            processoItemId: $data['processo_item_id'] ?? $data['processoItemId'] ?? null,
            fornecedorId: $data['fornecedor_id'] ?? $data['fornecedorId'] ?? null,
            transportadoraId: $data['transportadora_id'] ?? $data['transportadoraId'] ?? null,
            custoProduto: (float) ($data['custo_produto'] ?? $data['custoProduto'] ?? 0),
            marcaModelo: $data['marca_modelo'] ?? $data['marcaModelo'] ?? null,
            ajustesEspecificacao: $data['ajustes_especificacao'] ?? $data['ajustesEspecificacao'] ?? null,
            frete: (float) ($data['frete'] ?? $data['frete'] ?? 0),
            freteIncluido: $data['frete_incluido'] ?? $data['freteIncluido'] ?? false,
            fornecedorEscolhido: $data['fornecedor_escolhido'] ?? $data['fornecedorEscolhido'] ?? false,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

