<?php

namespace App\Domain\ProcessoItem\Entities;

use Carbon\Carbon;
use App\Domain\Exceptions\DomainException;

/**
 * Entidade ProcessoItem - Representa um item de processo licitatório
 */
class ProcessoItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $processoId,
        public readonly int $empresaId,
        public readonly ?int $fornecedorId = null,
        public readonly ?int $transportadoraId = null,
        public readonly ?string $numeroItem = null,
        public readonly ?string $nome = null,
        public readonly ?string $codigoInterno = null,
        public readonly float $quantidade = 0.0,
        public readonly ?string $unidade = null,
        public readonly ?string $especificacaoTecnica = null,
        public readonly ?string $marcaModeloReferencia = null,
        public readonly ?string $observacoesEdital = null,
        public readonly bool $exigeAtestado = false,
        public readonly ?float $quantidadeMinimaAtestado = null,
        public readonly ?float $quantidadeAtestadoCapTecnica = null,
        public readonly float $valorEstimado = 0.0,
        public readonly float $valorEstimadoTotal = 0.0,
        public readonly ?string $fonteValor = null,
        public readonly float $valorMinimoVenda = 0.0,
        public readonly float $valorFinalSessao = 0.0,
        public readonly float $valorArrematado = 0.0,
        public readonly ?Carbon $dataDisputa = null,
        public readonly float $valorNegociado = 0.0,
        public readonly ?string $classificacao = null,
        public readonly ?string $statusItem = null,
        public readonly ?string $situacaoFinal = null,
        public readonly ?string $chanceArremate = null,
        public readonly ?float $chancePercentual = null,
        public readonly bool $temChance = false,
        public readonly ?string $lembretes = null,
        public readonly ?string $observacoes = null,
        public readonly float $valorVencido = 0.0,
        public readonly float $valorEmpenhado = 0.0,
        public readonly float $valorFaturado = 0.0,
        public readonly float $valorPago = 0.0,
        public readonly float $saldoAberto = 0.0,
        public readonly float $lucroBruto = 0.0,
        public readonly float $lucroLiquido = 0.0,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->processoId <= 0) {
            throw new DomainException('O ID do processo é obrigatório.');
        }

        if ($this->empresaId <= 0) {
            throw new DomainException('O ID da empresa é obrigatório.');
        }

        if ($this->quantidade < 0) {
            throw new DomainException('A quantidade não pode ser negativa.');
        }

        if ($this->valorEstimado < 0 || $this->valorEstimadoTotal < 0) {
            throw new DomainException('Os valores estimados não podem ser negativos.');
        }
    }

    /**
     * Calcula o valor total estimado baseado na quantidade
     */
    public function calcularValorEstimadoTotal(): float
    {
        if ($this->quantidade > 0 && $this->valorEstimado > 0) {
            return round($this->quantidade * $this->valorEstimado, 2);
        }
        return $this->valorEstimadoTotal;
    }

    /**
     * Verifica se o item está arrematado
     */
    public function estaArrematado(): bool
    {
        return $this->valorArrematado > 0;
    }

    /**
     * Calcula o saldo aberto (valor arrematado - valor pago)
     */
    public function calcularSaldoAberto(): float
    {
        return round($this->valorArrematado - $this->valorPago, 2);
    }

    /**
     * Verifica se o item tem saldo em aberto
     */
    public function temSaldoAberto(): bool
    {
        return $this->calcularSaldoAberto() > 0;
    }
}


