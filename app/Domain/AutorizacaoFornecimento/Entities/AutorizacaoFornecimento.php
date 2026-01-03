<?php

namespace App\Domain\AutorizacaoFornecimento\Entities;

use DomainException;
use Carbon\Carbon;

/**
 * Entidade AutorizacaoFornecimento - Representa uma autorização de fornecimento no domínio
 */
class AutorizacaoFornecimento
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?int $processoId,
        public readonly ?int $contratoId,
        public readonly ?string $numero = null,
        public readonly ?Carbon $data = null,
        public readonly ?Carbon $dataAdjudicacao = null,
        public readonly ?Carbon $dataHomologacao = null,
        public readonly ?Carbon $dataFimVigencia = null,
        public readonly ?string $condicoesAf = null,
        public readonly ?string $itensArrematados = null,
        public readonly float $valor = 0.0,
        public readonly float $saldo = 0.0,
        public readonly float $valorEmpenhado = 0.0,
        public readonly ?string $situacao = null,
        public readonly ?string $situacaoDetalhada = null,
        public readonly bool $vigente = true,
        public readonly ?string $observacoes = null,
        public readonly ?string $numeroCte = null,
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
    }

    public function estaVigente(): bool
    {
        if (!$this->vigente) {
            return false;
        }

        if ($this->dataFimVigencia) {
            return Carbon::now()->isBefore($this->dataFimVigencia);
        }

        return true;
    }
}



