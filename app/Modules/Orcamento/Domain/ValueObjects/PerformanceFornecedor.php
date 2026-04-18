<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class PerformanceFornecedor
{
    private int $fornecedorId;
    private string $fornecedorNome;
    private int $totalOrcamentos;
    private float $valorTotal;
    private float $valorMedio;
    private float $taxaAprovacao;
    private float $taxaRejeicao;

    public function __construct(
        int $fornecedorId,
        string $fornecedorNome,
        int $totalOrcamentos,
        float $valorTotal,
        float $valorMedio,
        float $taxaAprovacao,
        float $taxaRejeicao
    ) {
        if ($totalOrcamentos < 0 || $valorTotal < 0 || $valorMedio < 0) {
            throw new \InvalidArgumentException('Valores não podem ser negativos');
        }
        if ($taxaAprovacao < 0 || $taxaAprovacao > 100 || $taxaRejeicao < 0 || $taxaRejeicao > 100) {
            throw new \InvalidArgumentException('Taxas devem estar entre 0 e 100');
        }

        $this->fornecedorId = $fornecedorId;
        $this->fornecedorNome = $fornecedorNome;
        $this->totalOrcamentos = $totalOrcamentos;
        $this->valorTotal = $valorTotal;
        $this->valorMedio = $valorMedio;
        $this->taxaAprovacao = $taxaAprovacao;
        $this->taxaRejeicao = $taxaRejeicao;
    }

    public function getFornecedorId(): int
    {
        return $this->fornecedorId;
    }

    public function getFornecedorNome(): string
    {
        return $this->fornecedorNome;
    }

    public function getTotalOrcamentos(): int
    {
        return $this->totalOrcamentos;
    }

    public function getValorTotal(): float
    {
        return round($this->valorTotal, 2);
    }

    public function getValorMedio(): float
    {
        return round($this->valorMedio, 2);
    }

    public function getTaxaAprovacao(): float
    {
        return round($this->taxaAprovacao, 2);
    }

    public function getTaxaRejeicao(): float
    {
        return round($this->taxaRejeicao, 2);
    }

    public function getConfiabilidade(): string
    {
        if ($this->taxaAprovacao >= 90) {
            return 'EXCELENTE';
        } elseif ($this->taxaAprovacao >= 75) {
            return 'BOA';
        } elseif ($this->taxaAprovacao >= 50) {
            return 'MEDIA';
        }
        return 'BAIXA';
    }

    public function toArray(): array
    {
        return [
            'fornecedor_id' => $this->fornecedorId,
            'fornecedor_nome' => $this->fornecedorNome,
            'total_orcamentos' => $this->totalOrcamentos,
            'valor_total' => $this->getValorTotal(),
            'valor_medio' => $this->getValorMedio(),
            'taxa_aprovacao' => $this->getTaxaAprovacao(),
            'taxa_rejeicao' => $this->getTaxaRejeicao(),
            'confiabilidade' => $this->getConfiabilidade()
        ];
    }
}
