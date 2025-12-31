<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class ResumoStatusOrcamento
{
    private string $status;
    private int $total;
    private float $valor;

    public function __construct(string $status, int $total, float $valor)
    {
        $statusValidos = ['pendente', 'aprovado', 'rejeitado', 'em_analise'];
        if (!in_array($status, $statusValidos)) {
            throw new \InvalidArgumentException('Status inválido: ' . $status);
        }

        $this->status = $status;
        $this->total = $total;
        $this->valor = $valor;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getValor(): float
    {
        return round($this->valor, 2);
    }

    public function getPercentual(int $totalGeral): float
    {
        if ($totalGeral === 0) {
            return 0;
        }
        return round(($this->total / $totalGeral * 100), 2);
    }

    public function toArray(int $totalGeral = 0): array
    {
        return [
            'status' => $this->status,
            'total' => $this->total,
            'valor' => $this->getValor(),
            'percentual' => $this->getPercentual($totalGeral)
        ];
    }
}
