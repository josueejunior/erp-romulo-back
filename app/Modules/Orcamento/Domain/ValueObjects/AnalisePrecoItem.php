<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class AnalisePrecoItem
{
    private int $processoItemId;
    private string $descricao;
    private float $precoMinimo;
    private float $precoMaximo;
    private float $precoMedio;
    private int $totalCotacoes;

    public function __construct(
        int $processoItemId,
        string $descricao,
        float $precoMinimo,
        float $precoMaximo,
        float $precoMedio,
        int $totalCotacoes
    ) {
        if ($precoMinimo < 0 || $precoMaximo < 0 || $precoMedio < 0) {
            throw new \InvalidArgumentException('Preços não podem ser negativos');
        }
        if ($totalCotacoes < 0) {
            throw new \InvalidArgumentException('Total de cotações não pode ser negativo');
        }

        $this->processoItemId = $processoItemId;
        $this->descricao = $descricao;
        $this->precoMinimo = $precoMinimo;
        $this->precoMaximo = $precoMaximo;
        $this->precoMedio = $precoMedio;
        $this->totalCotacoes = $totalCotacoes;
    }

    public function getProcessoItemId(): int
    {
        return $this->processoItemId;
    }

    public function getDescricao(): string
    {
        return $this->descricao;
    }

    public function getPrecoMinimo(): float
    {
        return round($this->precoMinimo, 2);
    }

    public function getPrecoMaximo(): float
    {
        return round($this->precoMaximo, 2);
    }

    public function getPrecoMedio(): float
    {
        return round($this->precoMedio, 2);
    }

    public function getTotalCotacoes(): int
    {
        return $this->totalCotacoes;
    }

    public function getVariacaoPercentual(): float
    {
        if ($this->precoMinimo === 0) {
            return 0;
        }
        return round((($this->precoMaximo - $this->precoMinimo) / $this->precoMinimo * 100), 2);
    }

    public function toArray(): array
    {
        return [
            'processo_item_id' => $this->processoItemId,
            'descricao' => $this->descricao,
            'preco_minimo' => $this->getPrecoMinimo(),
            'preco_maximo' => $this->getPrecoMaximo(),
            'preco_medio' => $this->getPrecoMedio(),
            'total_cotacoes' => $this->totalCotacoes,
            'variacao_percentual' => $this->getVariacaoPercentual()
        ];
    }
}
