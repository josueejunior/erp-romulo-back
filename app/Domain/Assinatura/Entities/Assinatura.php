<?php

namespace App\Domain\Assinatura\Entities;

use App\Domain\Assinatura\Enums\StatusAssinatura;
use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade Assinatura - Representa uma assinatura de plano no domínio
 * 
 * Regras de negócio:
 * - Assinatura PERTENCE ao usuário (userId obrigatório)
 * - TenantId é opcional (para compatibilidade com sistema legado)
 * - Status controlado por Enum
 * - Validações fortes no construtor
 */
class Assinatura
{
    public readonly StatusAssinatura $statusEnum;

    public function __construct(
        public readonly ?int $id,
        public readonly ?int $userId,
        public readonly ?int $tenantId,
        public readonly ?int $empresaId, // 🔥 NOVO: Assinatura pertence à empresa
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
        $this->statusEnum = StatusAssinatura::fromString($this->status);
        $this->validate();
    }

    /**
     * Validações de regras de negócio
     * 
     * @throws DomainException Se regra for violada
     */
    private function validate(): void
    {
        // userId é OBRIGATÓRIO
        if (!$this->userId || $this->userId <= 0) {
            throw new DomainException('O usuário é obrigatório para criar uma assinatura.');
        }
        
        // tenantId pode ser null, mas se fornecido deve ser válido
        if ($this->tenantId !== null && $this->tenantId <= 0) {
            throw new DomainException('O tenant_id deve ser válido se fornecido.');
        }

        // planoId é obrigatório
        if ($this->planoId <= 0) {
            throw new DomainException('O plano é obrigatório.');
        }

        // Status deve ser válido (já validado pelo Enum, mas garante)
        if (!StatusAssinatura::tryFrom($this->status)) {
            throw new DomainException(
                "Status de assinatura inválido: '{$this->status}'. " .
                "Valores válidos: " . implode(', ', array_column(StatusAssinatura::cases(), 'value'))
            );
        }

        // Data fim não pode ser anterior à data início
        if ($this->dataFim && $this->dataInicio && $this->dataFim->isBefore($this->dataInicio)) {
            throw new DomainException('A data de fim não pode ser anterior à data de início.');
        }

        // Valor não pode ser negativo
        if ($this->valorPago !== null && $this->valorPago < 0) {
            throw new DomainException('O valor pago não pode ser negativo.');
        }

        // Grace period não pode ser negativo
        if ($this->diasGracePeriod < 0) {
            throw new DomainException('O período de graça não pode ser negativo.');
        }

        // Validação de data de cancelamento
        if ($this->dataCancelamento && !$this->statusEnum->isEncerrada()) {
            throw new DomainException('Data de cancelamento só pode ser definida para assinaturas canceladas/expiradas.');
        }
    }

    // ==================== MÉTODOS DE ESTADO ====================

    /**
     * Verifica se a assinatura está ativa e válida
     */
    public function isAtiva(): bool
    {
        return $this->statusEnum === StatusAssinatura::ATIVA && !$this->isExpirada();
    }

    /**
     * Verifica se a assinatura é válida (pode ser usada)
     */
    public function isValida(): bool
    {
        return $this->statusEnum->isValida() && !$this->isExpirada();
    }

    /**
     * Verifica se a assinatura está expirada (considerando grace period)
     */
    public function isExpirada(): bool
    {
        if (!$this->dataFim) {
            return false;
        }

        return Carbon::now()->isAfter($this->dataFimComGrace());
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
        return $hoje->isAfter($this->dataFim) && $hoje->isBeforeOrEqualTo($this->dataFimComGrace());
    }

    /**
     * Verifica se está em período de trial
     */
    public function isTrial(): bool
    {
        return $this->statusEnum === StatusAssinatura::TRIAL;
    }

    /**
     * Verifica se foi cancelada
     */
    public function isCancelada(): bool
    {
        return $this->statusEnum === StatusAssinatura::CANCELADA;
    }

    /**
     * Verifica se permite upgrade de plano
     */
    public function permiteUpgrade(): bool
    {
        return $this->statusEnum->permiteUpgrade() && !$this->isExpirada();
    }

    // ==================== MÉTODOS DE CÁLCULO ====================

    /**
     * Retorna a data fim considerando o grace period
     */
    public function dataFimComGrace(): ?Carbon
    {
        if (!$this->dataFim) {
            return null;
        }

        return $this->dataFim->copy()->addDays($this->diasGracePeriod);
    }

    /**
     * Retorna dias restantes até o vencimento (sem grace period)
     */
    public function diasRestantes(): int
    {
        if (!$this->dataFim) {
            return PHP_INT_MAX; // Sem data fim = ilimitado
        }

        $hoje = Carbon::now();
        
        if ($hoje->isAfter($this->dataFim)) {
            return 0;
        }
        
        return (int) $hoje->diffInDays($this->dataFim);
    }

    /**
     * Retorna dias restantes incluindo grace period
     */
    public function diasRestantesComGrace(): int
    {
        $dataFimGrace = $this->dataFimComGrace();
        
        if (!$dataFimGrace) {
            return PHP_INT_MAX;
        }

        $hoje = Carbon::now();
        
        if ($hoje->isAfter($dataFimGrace)) {
            return 0;
        }
        
        return (int) $hoje->diffInDays($dataFimGrace);
    }

    /**
     * Calcula a duração total da assinatura em dias
     */
    public function duracaoEmDias(): ?int
    {
        if (!$this->dataInicio || !$this->dataFim) {
            return null;
        }

        return (int) $this->dataInicio->diffInDays($this->dataFim);
    }

    // ==================== MÉTODOS DE APRESENTAÇÃO ====================

    /**
     * Retorna label do status para exibição
     */
    public function statusLabel(): string
    {
        return $this->statusEnum->label();
    }

    /**
     * Retorna cor do status para UI
     */
    public function statusColor(): string
    {
        return $this->statusEnum->color();
    }

    /**
     * Retorna resumo para logs/debug
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'empresa_id' => $this->empresaId,
            'plano_id' => $this->planoId,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'data_inicio' => $this->dataInicio?->toDateString(),
            'data_fim' => $this->dataFim?->toDateString(),
            'dias_restantes' => $this->diasRestantes(),
            'is_valida' => $this->isValida(),
            'is_expirada' => $this->isExpirada(),
            'esta_no_grace' => $this->estaNoGracePeriod(),
        ];
    }
}
