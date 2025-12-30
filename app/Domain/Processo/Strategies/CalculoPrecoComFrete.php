<?php

namespace App\Domain\Processo\Strategies;

use App\Domain\ProcessoItem\Entities\ProcessoItem;

/**
 * Strategy para cálculo de preço com frete
 */
class CalculoPrecoComFrete implements CalculoPrecoStrategy
{
    public function calcular(ProcessoItem $item): float
    {
        $precoBase = (new CalculoPrecoDireto())->calcular($item);
        
        // Adicionar frete se houver
        // Nota: O frete geralmente está no Orcamento, não no ProcessoItem
        // Esta é uma implementação simplificada
        
        return $precoBase;
    }
}


