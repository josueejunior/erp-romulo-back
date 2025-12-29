<?php

namespace App\Domain\Processo\Strategies;

use App\Domain\ProcessoItem\Entities\ProcessoItem;

/**
 * Strategy para cálculo de preço direto (sem frete)
 */
class CalculoPrecoDireto implements CalculoPrecoStrategy
{
    public function calcular(ProcessoItem $item): float
    {
        // Preço = valor arrematado ou valor negociado
        if ($item->valorArrematado > 0) {
            return $item->valorArrematado;
        }
        
        if ($item->valorNegociado > 0) {
            return $item->valorNegociado;
        }
        
        return $item->valorEstimado;
    }
}

