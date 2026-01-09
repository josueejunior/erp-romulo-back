<?php

namespace App\Domain\Assinatura\Entities;

use App\Domain\Assinatura\Enums\StatusAssinatura;
use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade Assinatura - Representa uma assinatura de plano no domínio
 * 
 * Regras de negócio:
 * - Assinatura PERTENCE à empresa (empresaId obrigatório para novas assinaturas)
 * - userId é opcional (para compatibilidade com sistema legado)
 * - TenantId é opcional (para compatibilidade com sistema legado)
 * - Status controlado por Enum
 * - Validações fortes no construtor
 * - Datas devem ser consistentes
 * - Valores não podem ser negativos
 * 
 * @see StatusAssinatura Para status válidos
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
     * 🔒 ROBUSTEZ: Validações abrangentes para garantir integridade dos dados
     * 
     * @throws DomainException Se regra for violada
     */
    private function validate(): void
    {
        // ========== VALIDAÇÕES DE IDENTIFICAÇÃO ==========
        
        // empresaId é OBRIGATÓRIO para novas assinaturas (sem id)
        // Assinaturas existentes podem ter empresaId null (compatibilidade com sistema legado)
        if (!$this->id && (!$this->empresaId || $this->empresaId <= 0)) {
            throw new DomainException('A empresa é obrigatória para criar uma assinatura.');
        }
        
        // empresaId, se fornecido, deve ser válido
        if ($this->empresaId !== null && $this->empresaId <= 0) {
            throw new DomainException('O empresa_id deve ser válido se fornecido.');
        }
        
        // userId é OBRIGATÓRIO apenas para novas assinaturas (sem id)
        // Assinaturas existentes podem ter userId null (compatibilidade com sistema legado)
        // 🔥 NOVO: Assinatura pertence à empresa, não ao usuário, então userId pode ser null
        if (!$this->id && (!$this->userId || $this->userId <= 0)) {
            throw new DomainException('O usuário é obrigatório para criar uma assinatura.');
        }
        
        // userId, se fornecido, deve ser válido
        if ($this->userId !== null && $this->userId <= 0) {
            throw new DomainException('O user_id deve ser válido se fornecido.');
        }
        
        // tenantId pode ser null, mas se fornecido deve ser válido
        if ($this->tenantId !== null && $this->tenantId <= 0) {
            throw new DomainException('O tenant_id deve ser válido se fornecido.');
        }

        // planoId é obrigatório e deve ser válido
        if ($this->planoId <= 0) {
            throw new DomainException('O plano é obrigatório e deve ser válido.');
        }

        // ========== VALIDAÇÕES DE STATUS ==========
        
        // Status deve ser válido (já validado pelo Enum, mas garante)
        if (!StatusAssinatura::tryFrom($this->status)) {
            throw new DomainException(
                "Status de assinatura inválido: '{$this->status}'. " .
                "Valores válidos: " . implode(', ', array_column(StatusAssinatura::cases(), 'value'))
            );
        }

        // ========== VALIDAÇÕES DE DATAS ==========
        
        // Data início não pode ser no futuro (para novas assinaturas)
        if ($this->dataInicio && !$this->id && $this->dataInicio->isFuture()) {
            throw new DomainException('A data de início não pode ser no futuro.');
        }
        
        // Data fim não pode ser anterior à data início
        if ($this->dataFim && $this->dataInicio && $this->dataFim->isBefore($this->dataInicio)) {
            throw new DomainException('A data de fim não pode ser anterior à data de início.');
        }
        
        // Data de cancelamento deve ser posterior à data de início
        if ($this->dataCancelamento && $this->dataInicio && $this->dataCancelamento->isBefore($this->dataInicio)) {
            throw new DomainException('A data de cancelamento não pode ser anterior à data de início.');
        }
        
        // Data de cancelamento deve ser anterior ou igual à data fim (se houver)
        if ($this->dataCancelamento && $this->dataFim && $this->dataCancelamento->isAfter($this->dataFim)) {
            throw new DomainException('A data de cancelamento não pode ser posterior à data de fim.');
        }

        // Validação de data de cancelamento: só pode ser definida para assinaturas encerradas
        if ($this->dataCancelamento && !$this->statusEnum->isEncerrada()) {
            throw new DomainException('Data de cancelamento só pode ser definida para assinaturas canceladas ou expiradas.');
        }

        // ========== VALIDAÇÕES DE VALORES ==========
        
        // Valor não pode ser negativo
        if ($this->valorPago !== null && $this->valorPago < 0) {
            throw new DomainException('O valor pago não pode ser negativo.');
        }
        
        // Valor muito alto pode indicar erro (limite de segurança: R$ 1.000.000)
        if ($this->valorPago !== null && $this->valorPago > 1000000) {
            throw new DomainException('O valor pago excede o limite máximo permitido (R$ 1.000.000,00).');
        }

        // ========== VALIDAÇÕES DE CONFIGURAÇÃO ==========
        
        // Grace period não pode ser negativo
        if ($this->diasGracePeriod < 0) {
            throw new DomainException('O período de graça não pode ser negativo.');
        }
        
        // Grace period muito longo pode indicar erro (limite: 90 dias)
        if ($this->diasGracePeriod > 90) {
            throw new DomainException('O período de graça não pode exceder 90 dias.');
        }
        
        // ========== VALIDAÇÕES DE MÉTODO DE PAGAMENTO ==========
        
        // Método de pagamento válido (se fornecido)
        if ($this->metodoPagamento !== null) {
            $metodosValidos = ['gratuito', 'credit_card', 'pix', 'boleto', 'pendente'];
            if (!in_array($this->metodoPagamento, $metodosValidos, true)) {
                throw new DomainException(
                    "Método de pagamento inválido: '{$this->metodoPagamento}'. " .
                    "Valores válidos: " . implode(', ', $metodosValidos)
                );
            }
        }
        
        // Se tem transação_id, deve ter método de pagamento
        if ($this->transacaoId && !$this->metodoPagamento) {
            throw new DomainException('Assinaturas com transação de pagamento devem ter método de pagamento definido.');
        }
        
        // ========== VALIDAÇÕES DE OBSERVAÇÕES ==========
        
        // Observações não podem ser muito longas (limite: 5000 caracteres)
        if ($this->observacoes !== null && strlen($this->observacoes) > 5000) {
            throw new DomainException('As observações não podem exceder 5000 caracteres.');
        }
    }

    // ==================== FACTORY METHODS ====================
    
    /**
     * Factory method: Criar assinatura ativa
     * 
     * 🔒 ROBUSTEZ: Factory method garante criação consistente
     * 
     * @param int $empresaId ID da empresa
     * @param int $planoId ID do plano
     * @param ?int $userId ID do usuário (opcional)
     * @param ?int $tenantId ID do tenant (opcional)
     * @param ?Carbon $dataInicio Data de início (padrão: agora)
     * @param ?Carbon $dataFim Data de fim
     * @param ?float $valorPago Valor pago
     * @param ?string $metodoPagamento Método de pagamento
     * @param ?string $transacaoId ID da transação
     * @param int $diasGracePeriod Dias de grace period
     * @return self
     */
    public static function criarAtiva(
        int $empresaId,
        int $planoId,
        ?int $userId = null,
        ?int $tenantId = null,
        ?Carbon $dataInicio = null,
        ?Carbon $dataFim = null,
        ?float $valorPago = null,
        ?string $metodoPagamento = null,
        ?string $transacaoId = null,
        int $diasGracePeriod = 7,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            tenantId: $tenantId,
            empresaId: $empresaId,
            planoId: $planoId,
            status: StatusAssinatura::ATIVA->value,
            dataInicio: $dataInicio ?? Carbon::now(),
            dataFim: $dataFim,
            dataCancelamento: null,
            valorPago: $valorPago,
            metodoPagamento: $metodoPagamento ?? 'gratuito',
            transacaoId: $transacaoId,
            diasGracePeriod: $diasGracePeriod,
            observacoes: null,
        );
    }
    
    /**
     * Factory method: Criar assinatura pendente
     * 
     * @param int $empresaId ID da empresa
     * @param int $planoId ID do plano
     * @param ?int $userId ID do usuário (opcional)
     * @param ?int $tenantId ID do tenant (opcional)
     * @param ?Carbon $dataInicio Data de início (padrão: agora)
     * @param ?Carbon $dataFim Data de fim
     * @param ?string $transacaoId ID da transação
     * @param ?string $observacoes Observações
     * @return self
     */
    public static function criarPendente(
        int $empresaId,
        int $planoId,
        ?int $userId = null,
        ?int $tenantId = null,
        ?Carbon $dataInicio = null,
        ?Carbon $dataFim = null,
        ?string $transacaoId = null,
        ?string $observacoes = null,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            tenantId: $tenantId,
            empresaId: $empresaId,
            planoId: $planoId,
            status: StatusAssinatura::PENDENTE->value,
            dataInicio: $dataInicio ?? Carbon::now(),
            dataFim: $dataFim,
            dataCancelamento: null,
            valorPago: null,
            metodoPagamento: 'pendente',
            transacaoId: $transacaoId,
            diasGracePeriod: 7,
            observacoes: $observacoes,
        );
    }
    
    /**
     * Factory method: Criar assinatura trial (período de teste)
     * 
     * @param int $empresaId ID da empresa
     * @param int $planoId ID do plano
     * @param ?int $userId ID do usuário (opcional)
     * @param ?int $tenantId ID do tenant (opcional)
     * @param int $diasTrial Dias de trial (padrão: 3)
     * @return self
     */
    public static function criarTrial(
        int $empresaId,
        int $planoId,
        ?int $userId = null,
        ?int $tenantId = null,
        int $diasTrial = 3,
    ): self {
        $dataInicio = Carbon::now();
        $dataFim = $dataInicio->copy()->addDays($diasTrial);
        
        return new self(
            id: null,
            userId: $userId,
            tenantId: $tenantId,
            empresaId: $empresaId,
            planoId: $planoId,
            status: StatusAssinatura::TRIAL->value,
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            dataCancelamento: null,
            valorPago: 0,
            metodoPagamento: 'gratuito',
            transacaoId: null,
            diasGracePeriod: 0,
            observacoes: "Período de teste de {$diasTrial} dias",
        );
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
