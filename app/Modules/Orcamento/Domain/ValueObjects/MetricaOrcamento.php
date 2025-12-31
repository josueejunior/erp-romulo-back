<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class MetricaOrcamento
{
    private int $total;
    private float $valorTotal;
    private string $moeda;

    public function __construct(int $total, float $valorTotal, string $moeda = 'BRL')
    {
        if ($total < 0) {
            throw new \InvalidArgumentException('Total de orçamentos não pode ser negativo');
        }
        if ($valorTotal < 0) {
            throw new \InvalidArgumentException('Valor total não pode ser negativo');
        }

        $this->total = $total;
        $this->valorTotal = $valorTotal;
        $this->moeda = $moeda;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getValorTotal(): float
    {
        return round($this->valorTotal, 2);
    }

    public function getMoeda(): string
    {
        return $this->moeda;
    }

    public function getValorMedio(): float
    {
        if ($this->total === 0) {
            return 0;
        }
        return round($this->valorTotal / $this->total, 2);
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'valor_total' => $this->getValorTotal(),
            'valor_medio' => $this->getValorMedio(),
            'moeda' => $this->moeda
        ];
    }
}
