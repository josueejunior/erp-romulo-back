<?php

namespace App\Domain\CustoIndireto\Entities;

use DomainException;
use Carbon\Carbon;

/**
 * Entidade CustoIndireto - Representa um custo indireto no domínio
 */
class CustoIndireto
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly string $descricao,
        public readonly ?Carbon $data = null,
        public readonly float $valor = 0.0,
        public readonly ?string $categoria = null,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->empresaId <= 0) {
            throw new DomainException('A empresa é obrigatória.');
        }

        if (empty(trim($this->descricao))) {
            throw new DomainException('A descrição é obrigatória.');
        }

        if ($this->valor < 0) {
            throw new DomainException('O valor não pode ser negativo.');
        }
    }
}



