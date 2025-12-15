<?php

namespace App\Services;

use App\Models\FormacaoPreco;

class FormacaoPrecoService
{
    /**
     * Calcula o preço mínimo e recomendado baseado nos custos, impostos e margem
     * 
     * Fórmula:
     * Base = Custo Produto + Frete
     * Impostos = Base * (percentual_impostos / 100)
     * Subtotal = Base + Impostos
     * Margem = Subtotal * (percentual_margem / 100)
     * Preço Mínimo = Subtotal + Margem
     * Preço Recomendado = Preço Mínimo * 1.10 (10% a mais)
     */
    public function calcularPrecos(
        float $custoProduto,
        float $frete = 0,
        float $percentualImpostos = 0,
        float $percentualMargem = 0
    ): array {
        // Base de cálculo
        $base = $custoProduto + $frete;
        
        // Cálculo de impostos
        $valorImpostos = $base * ($percentualImpostos / 100);
        
        // Subtotal (base + impostos)
        $subtotal = $base + $valorImpostos;
        
        // Cálculo da margem
        $valorMargem = $subtotal * ($percentualMargem / 100);
        
        // Preço mínimo (subtotal + margem)
        $precoMinimo = $subtotal + $valorMargem;
        
        // Preço recomendado (10% acima do mínimo)
        $precoRecomendado = $precoMinimo * 1.10;
        
        return [
            'custo_produto' => round($custoProduto, 2),
            'frete' => round($frete, 2),
            'base' => round($base, 2),
            'percentual_impostos' => $percentualImpostos,
            'valor_impostos' => round($valorImpostos, 2),
            'subtotal' => round($subtotal, 2),
            'percentual_margem' => $percentualMargem,
            'valor_margem' => round($valorMargem, 2),
            'preco_minimo' => round($precoMinimo, 2),
            'preco_recomendado' => round($precoRecomendado, 2),
        ];
    }

    /**
     * Cria ou atualiza a formação de preço para um orçamento
     */
    public function criarOuAtualizar(
        int $processoItemId,
        int $orcamentoId,
        array $dados
    ): FormacaoPreco {
        $precos = $this->calcularPrecos(
            $dados['custo_produto'],
            $dados['frete'] ?? 0,
            $dados['percentual_impostos'] ?? 0,
            $dados['percentual_margem'] ?? 0
        );

        return FormacaoPreco::updateOrCreate(
            [
                'processo_item_id' => $processoItemId,
                'orcamento_id' => $orcamentoId,
            ],
            array_merge($dados, $precos)
        );
    }
}

