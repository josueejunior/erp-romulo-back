<?php

namespace App\Domain\FormacaoPreco\ValueObjects;

/**
 * Value Object para cálculo de preço mínimo
 * 
 * ✅ DDD: Fórmula isolada e testável
 * Centraliza a lógica de cálculo em um único lugar
 */
class PrecoMinimoCalculator
{
    /**
     * Calcular preço mínimo baseado na fórmula:
     * preco_minimo = (custo_produto + frete) * (1 + impostos%) / (1 - margem%)
     * 
     * @param float $custoProduto
     * @param float $frete
     * @param float $percentualImpostos (0-100)
     * @param float $percentualMargem (0-99)
     * @return float
     * @throws \DomainException se margem >= 100%
     */
    public static function calcular(
        float $custoProduto,
        float $frete,
        float $percentualImpostos,
        float $percentualMargem
    ): float {
        if ($custoProduto < 0 || $frete < 0) {
            throw new \DomainException('Custos não podem ser negativos.');
        }

        if ($percentualImpostos < 0 || $percentualImpostos > 100) {
            throw new \DomainException('Percentual de impostos deve estar entre 0 e 100.');
        }

        if ($percentualMargem < 0 || $percentualMargem >= 100) {
            throw new \DomainException('Percentual de margem deve estar entre 0 e 99.');
        }

        $custo = $custoProduto + $frete;
        $impostoDecimal = $percentualImpostos / 100;
        $margemDecimal = $percentualMargem / 100;

        $comImposto = $custo * (1 + $impostoDecimal);

        // Margem já validada acima, mas garantia extra
        if ($margemDecimal >= 1) {
            throw new \DomainException('Margem inválida: não pode ser 100% ou maior.');
        }

        return round($comImposto / (1 - $margemDecimal), 2);
    }
}









