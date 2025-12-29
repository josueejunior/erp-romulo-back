<?php

namespace App\Domain\FormacaoPreco\Entities;

use DomainException;

/**
 * Entidade FormacaoPreco - Representa uma formação de preço no domínio
 */
class FormacaoPreco
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $processoItemId,
        public readonly ?int $orcamentoId,
        public readonly ?int $orcamentoItemId,
        public readonly float $custoProduto = 0.0,
        public readonly float $frete = 0.0,
        public readonly float $percentualImpostos = 0.0,
        public readonly float $valorImpostos = 0.0,
        public readonly float $percentualMargem = 0.0,
        public readonly float $valorMargem = 0.0,
        public readonly float $precoMinimo = 0.0,
        public readonly float $precoRecomendado = 0.0,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->custoProduto < 0 || $this->frete < 0) {
            throw new DomainException('Os custos não podem ser negativos.');
        }

        if ($this->percentualImpostos < 0 || $this->percentualMargem < 0) {
            throw new DomainException('Os percentuais não podem ser negativos.');
        }
    }

    public function calcularPrecoRecomendado(): float
    {
        $base = $this->custoProduto + $this->frete;
        $comImpostos = $base + $this->valorImpostos;
        $comMargem = $comImpostos + $this->valorMargem;
        
        return round($comMargem, 2);
    }
}

