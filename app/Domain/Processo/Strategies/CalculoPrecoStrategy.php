<?php

namespace App\Domain\Processo\Strategies;

use App\Domain\ProcessoItem\Entities\ProcessoItem;

/**
 * Interface Strategy para cálculo de preços
 */
interface CalculoPrecoStrategy
{
    /**
     * Calcular preço para um item de processo
     * 
     * @param ProcessoItem $item Item do processo
     * @return float Preço calculado
     */
    public function calcular(ProcessoItem $item): float;
}



