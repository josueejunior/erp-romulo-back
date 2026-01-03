<?php

namespace App\Domain\Empenho\Entities;

use DomainException;
use Carbon\Carbon;

/**
 * Entidade Empenho - Representa um empenho no domínio
 */
class Empenho
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly ?int $processoId,
        public readonly ?int $contratoId,
        public readonly ?int $autorizacaoFornecimentoId,
        public readonly ?string $numero = null,
        public readonly ?Carbon $data = null,
        public readonly ?Carbon $dataRecebimento = null,
        public readonly ?Carbon $prazoEntregaCalculado = null,
        public readonly float $valor = 0.0,
        public readonly bool $concluido = false,
        public readonly ?string $situacao = null,
        public readonly ?Carbon $dataEntrega = null,
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

    public function podeConcluir(): bool
    {
        return !$this->concluido;
    }

    public function concluir(): self
    {
        if (!$this->podeConcluir()) {
            throw new DomainException('Empenho já está concluído.');
        }

        return new self(
            id: $this->id,
            empresaId: $this->empresaId,
            processoId: $this->processoId,
            contratoId: $this->contratoId,
            autorizacaoFornecimentoId: $this->autorizacaoFornecimentoId,
            numero: $this->numero,
            data: $this->data,
            dataRecebimento: $this->dataRecebimento,
            prazoEntregaCalculado: $this->prazoEntregaCalculado,
            valor: $this->valor,
            concluido: true,
            situacao: 'concluido',
            dataEntrega: Carbon::now(),
            observacoes: $this->observacoes,
            numeroCte: $this->numeroCte,
        );
    }
}



