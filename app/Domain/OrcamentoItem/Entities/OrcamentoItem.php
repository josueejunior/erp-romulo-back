<?php

namespace App\Domain\OrcamentoItem\Entities;

use DomainException;

/**
 * Entidade OrcamentoItem - Representa um item de orçamento
 */
class OrcamentoItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $orcamentoId,
        public readonly int $processoItemId,
        public readonly float $custoProduto = 0.0,
        public readonly ?string $marcaModelo = null,
        public readonly ?string $ajustesEspecificacao = null,
        public readonly float $frete = 0.0,
        public readonly bool $freteIncluido = false,
        public readonly bool $fornecedorEscolhido = false,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->orcamentoId <= 0) {
            throw new DomainException('O ID do orçamento é obrigatório.');
        }

        if ($this->processoItemId <= 0) {
            throw new DomainException('O ID do item de processo é obrigatório.');
        }

        if ($this->custoProduto < 0 || $this->frete < 0) {
            throw new DomainException('Os custos não podem ser negativos.');
        }
    }

    /**
     * Calcula o custo total (produto + frete se não incluído)
     */
    public function calcularCustoTotal(): float
    {
        $custoTotal = $this->custoProduto;
        
        if (!$this->freteIncluido) {
            $custoTotal += $this->frete;
        }
        
        return round($custoTotal, 2);
    }

    /**
     * Verifica se o fornecedor foi escolhido
     */
    public function foiEscolhido(): bool
    {
        return $this->fornecedorEscolhido;
    }
}


