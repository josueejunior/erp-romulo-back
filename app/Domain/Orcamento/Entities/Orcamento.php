<?php

namespace App\Domain\Orcamento\Entities;

use DomainException;

/**
 * Entidade Orcamento - Representa um orçamento no domínio
 */
class Orcamento
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?int $processoId,
        public readonly ?int $processoItemId,
        public readonly ?int $fornecedorId,
        public readonly ?int $transportadoraId,
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
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if ($this->custoProduto < 0 || $this->frete < 0) {
            throw new DomainException('Os valores não podem ser negativos.');
        }
    }

    public function calcularCustoTotal(): float
    {
        if ($this->freteIncluido) {
            return $this->custoProduto;
        }
        return round($this->custoProduto + $this->frete, 2);
    }
}

