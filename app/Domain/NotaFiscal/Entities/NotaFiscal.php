<?php

namespace App\Domain\NotaFiscal\Entities;

use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade NotaFiscal - Representa uma nota fiscal no domínio
 */
class NotaFiscal
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?int $processoId,
        public readonly ?int $processoItemId = null,
        public readonly ?int $empenhoId = null,
        public readonly ?int $contratoId = null,
        public readonly ?int $autorizacaoFornecimentoId = null,
        public readonly ?string $tipo = null,
        public readonly ?string $numero = null,
        public readonly ?string $serie = null,
        public readonly ?Carbon $dataEmissao = null,
        public readonly ?int $fornecedorId = null,
        public readonly ?string $transportadora = null,
        public readonly ?string $numeroCte = null,
        public readonly ?Carbon $dataEntregaPrevista = null,
        public readonly ?Carbon $dataEntregaRealizada = null,
        public readonly ?string $situacaoLogistica = null,
        public readonly float $valor = 0.0,
        public readonly float $custoProduto = 0.0,
        public readonly float $custoFrete = 0.0,
        public readonly float $custoTotal = 0.0,
        public readonly ?string $comprovantePagamento = null,
        public readonly ?string $arquivo = null,
        public readonly ?string $situacao = null,
        public readonly ?Carbon $dataPagamento = null,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if ($this->valor < 0) {
            throw new DomainException('O valor não pode ser negativo.');
        }

        if ($this->custoProduto < 0 || $this->custoFrete < 0) {
            throw new DomainException('Os custos não podem ser negativos.');
        }
    }

    public function calcularCustoTotal(): float
    {
        return round($this->custoProduto + $this->custoFrete, 2);
    }
}



