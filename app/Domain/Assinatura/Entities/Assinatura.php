<?php

namespace App\Domain\Assinatura\Entities;

use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade Assinatura - Representa uma assinatura de plano no domínio
 */
class Assinatura
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $planoId,
        public readonly string $status,
        public readonly ?Carbon $dataInicio = null,
        public readonly ?Carbon $dataFim = null,
        public readonly ?Carbon $dataCancelamento = null,
        public readonly ?float $valorPago = null,
        public readonly ?string $metodoPagamento = null,
        public readonly ?string $transacaoId = null,
        public readonly int $diasGracePeriod = 7,
        public readonly ?string $observacoes = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->tenantId <= 0) {
            throw new DomainException('O tenant é obrigatório.');
        }

        if ($this->planoId <= 0) {
            throw new DomainException('O plano é obrigatório.');
        }

        $statusValidos = ['ativa', 'suspensa', 'cancelada', 'expirada', 'pendente'];
        if (!in_array($this->status, $statusValidos)) {
            throw new DomainException('Status de assinatura inválido.');
        }

        if ($this->dataFim && $this->dataInicio && $this->dataFim->isBefore($this->dataInicio)) {
            throw new DomainException('A data de fim não pode ser anterior à data de início.');
        }

        if ($this->valorPago !== null && $this->valorPago < 0) {
            throw new DomainException('O valor pago não pode ser negativo.');
        }

        if ($this->diasGracePeriod < 0) {
            throw new DomainException('O período de graça não pode ser negativo.');
        }
    }

    /**
     * Verifica se a assinatura está ativa
     */
    public function isAtiva(): bool
    {
        return $this->status === 'ativa' && !$this->isExpirada();
    }

    /**
     * Verifica se a assinatura está expirada
     */
    public function isExpirada(): bool
    {
        if (!$this->dataFim) {
            return false;
        }

        $hoje = Carbon::now();
        $dataFimComGrace = $this->dataFim->copy()->addDays($this->diasGracePeriod);
        
        return $hoje->isAfter($dataFimComGrace);
    }

    /**
     * Verifica se está no período de grace (tolerância)
     */
    public function estaNoGracePeriod(): bool
    {
        if (!$this->dataFim) {
            return false;
        }

        $hoje = Carbon::now();
        $dataFim = $this->dataFim;
        $dataFimComGrace = $dataFim->copy()->addDays($this->diasGracePeriod);
        
        return $hoje->isAfter($dataFim) && $hoje->isBeforeOrEqualTo($dataFimComGrace);
    }

    /**
     * Retorna dias restantes até o vencimento
     */
    public function diasRestantes(): int
    {
        if (!$this->dataFim) {
            return 0;
        }

        $hoje = Carbon::now();
        $dataFim = $this->dataFim;
        
        if ($hoje->isAfter($dataFim)) {
            return 0;
        }
        
        return $hoje->diffInDays($dataFim);
    }
}

