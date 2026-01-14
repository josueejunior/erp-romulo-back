<?php

namespace App\Application\Orcamento\DTOs;

/**
 * DTO para atualização de orçamento
 */
class AtualizarOrcamentoDTO
{
    public function __construct(
        public readonly int $orcamentoId,
        public readonly ?int $fornecedorId = null,
        public readonly ?int $transportadoraId = null,
        public readonly ?float $custoProduto = null,
        public readonly ?string $marcaModelo = null,
        public readonly ?string $ajustesEspecificacao = null,
        public readonly ?float $frete = null,
        public readonly ?bool $freteIncluido = null,
        public readonly ?bool $fornecedorEscolhido = null,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data, int $orcamentoId): self
    {
        return new self(
            orcamentoId: $orcamentoId,
            fornecedorId: $data['fornecedor_id'] ?? $data['fornecedorId'] ?? null,
            transportadoraId: $data['transportadora_id'] ?? $data['transportadoraId'] ?? null,
            custoProduto: isset($data['custo_produto']) || isset($data['custoProduto']) 
                ? (float) ($data['custo_produto'] ?? $data['custoProduto'] ?? 0) 
                : null,
            marcaModelo: $data['marca_modelo'] ?? $data['marcaModelo'] ?? null,
            ajustesEspecificacao: $data['ajustes_especificacao'] ?? $data['ajustesEspecificacao'] ?? null,
            frete: isset($data['frete']) ? (float) $data['frete'] : null,
            freteIncluido: isset($data['frete_incluido']) || isset($data['freteIncluido'])
                ? (bool) ($data['frete_incluido'] ?? $data['freteIncluido'] ?? false)
                : null,
            fornecedorEscolhido: isset($data['fornecedor_escolhido']) || isset($data['fornecedorEscolhido'])
                ? (bool) ($data['fornecedor_escolhido'] ?? $data['fornecedorEscolhido'] ?? false)
                : null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}









