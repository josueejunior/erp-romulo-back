<?php

namespace App\Domain\Contrato\Entities;

use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade Contrato - Representa um contrato no domínio
 */
class Contrato
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?int $processoId,
        public readonly ?string $numero = null,
        public readonly ?Carbon $dataInicio = null,
        public readonly ?Carbon $dataFim = null,
        public readonly ?Carbon $dataAssinatura = null,
        public readonly float $valorTotal = 0.0,
        public readonly float $saldo = 0.0,
        public readonly float $valorEmpenhado = 0.0,
        public readonly ?string $condicoesComerciais = null,
        public readonly ?string $condicoesTecnicas = null,
        public readonly ?string $locaisEntrega = null,
        public readonly ?string $prazosContrato = null,
        public readonly ?string $regrasContrato = null,
        public readonly ?string $situacao = null,
        public readonly bool $vigente = true,
        public readonly ?string $observacoes = null,
        public readonly ?string $arquivoContrato = null,
        public readonly ?string $numeroCte = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if ($this->dataInicio && $this->dataFim && $this->dataInicio->isAfter($this->dataFim)) {
            throw new DomainException('A data de início deve ser anterior à data de fim.');
        }

        if ($this->valorTotal < 0) {
            throw new DomainException('O valor total não pode ser negativo.');
        }
    }

    public function estaVigente(): bool
    {
        if (!$this->vigente) {
            return false;
        }

        if ($this->dataFim) {
            return Carbon::now()->isBefore($this->dataFim);
        }

        return true;
    }
}



