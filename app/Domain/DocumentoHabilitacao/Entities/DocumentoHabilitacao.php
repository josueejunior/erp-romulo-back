<?php

namespace App\Domain\DocumentoHabilitacao\Entities;

use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade DocumentoHabilitacao - Representa um documento de habilitação no domínio
 */
class DocumentoHabilitacao
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?string $tipo = null,
        public readonly ?string $numero = null,
        public readonly ?string $identificacao = null,
        public readonly ?Carbon $dataEmissao = null,
        public readonly ?Carbon $dataValidade = null,
        public readonly ?string $arquivo = null,
        public readonly bool $ativo = true,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }
    }

    public function estaVencido(): bool
    {
        if (!$this->dataValidade) {
            return false;
        }

        return Carbon::now()->isAfter($this->dataValidade);
    }

    public function diasParaVencer(): ?int
    {
        if (!$this->dataValidade) {
            return null;
        }

        return Carbon::now()->diffInDays($this->dataValidade, false);
    }
}




