<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Entities;

use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade OnboardingProgress - Representa o progresso de onboarding no domínio
 * Contém apenas regras de negócio, sem dependências de infraestrutura
 */
class OnboardingProgress
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $tenantId,
        public readonly ?int $userId,
        public readonly ?string $sessionId,
        public readonly ?string $email,
        public readonly bool $onboardingConcluido = false,
        public readonly array $etapasConcluidas = [],
        public readonly array $checklist = [],
        public readonly int $progressoPercentual = 0,
        public readonly ?Carbon $iniciadoEm = null,
        public readonly ?Carbon $concluidoEm = null,
    ) {
        $this->validate();
    }

    /**
     * Validações de negócio da entidade OnboardingProgress
     */
    private function validate(): void
    {
        // Deve ter pelo menos uma forma de identificar o usuário
        if (!$this->tenantId && !$this->userId && !$this->sessionId && !$this->email) {
            throw new DomainException('É necessário fornecer pelo menos uma forma de identificação (tenant_id, user_id, session_id ou email).');
        }

        // Validar progresso percentual
        if ($this->progressoPercentual < 0 || $this->progressoPercentual > 100) {
            throw new DomainException('O progresso percentual deve estar entre 0 e 100.');
        }

        // Validar etapas concluídas
        if (!is_array($this->etapasConcluidas)) {
            throw new DomainException('As etapas concluídas devem ser um array.');
        }

        // Validar checklist
        if (!is_array($this->checklist)) {
            throw new DomainException('O checklist deve ser um array.');
        }

        // Se concluído, progresso deve ser 100%
        if ($this->onboardingConcluido && $this->progressoPercentual !== 100) {
            throw new DomainException('Onboarding concluído deve ter progresso de 100%.');
        }

        // Se concluído, deve ter data de conclusão
        if ($this->onboardingConcluido && !$this->concluidoEm) {
            throw new DomainException('Onboarding concluído deve ter data de conclusão.');
        }
    }

    /**
     * Regra de negócio: Verificar se pode concluir onboarding
     */
    public function podeConcluir(): bool
    {
        return !$this->onboardingConcluido;
    }

    /**
     * Regra de negócio: Verificar se etapa já foi concluída
     */
    public function etapaJaConcluida(string $etapa): bool
    {
        return in_array($etapa, $this->etapasConcluidas, true);
    }

    /**
     * Regra de negócio: Adicionar etapa concluída (retorna nova instância)
     */
    public function adicionarEtapaConcluida(string $etapa): self
    {
        if ($this->etapaJaConcluida($etapa)) {
            return $this; // Já está concluída, retornar mesma instância
        }

        $novasEtapas = array_merge($this->etapasConcluidas, [$etapa]);
        
        // Calcular novo progresso (assumindo 5 etapas totais)
        $totalEtapas = 5;
        $novoProgresso = min(100, (int) round((count($novasEtapas) / $totalEtapas) * 100));

        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            userId: $this->userId,
            sessionId: $this->sessionId,
            email: $this->email,
            onboardingConcluido: $this->onboardingConcluido,
            etapasConcluidas: $novasEtapas,
            checklist: $this->checklist,
            progressoPercentual: $novoProgresso,
            iniciadoEm: $this->iniciadoEm,
            concluidoEm: $this->concluidoEm,
        );
    }

    /**
     * Regra de negócio: Marcar item do checklist (retorna nova instância)
     */
    public function marcarItemChecklist(string $item): self
    {
        $novoChecklist = $this->checklist;
        $novoChecklist[$item] = true;

        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            userId: $this->userId,
            sessionId: $this->sessionId,
            email: $this->email,
            onboardingConcluido: $this->onboardingConcluido,
            etapasConcluidas: $this->etapasConcluidas,
            checklist: $novoChecklist,
            progressoPercentual: $this->progressoPercentual,
            iniciadoEm: $this->iniciadoEm,
            concluidoEm: $this->concluidoEm,
        );
    }

    /**
     * Regra de negócio: Concluir onboarding (retorna nova instância)
     */
    public function concluir(): self
    {
        if (!$this->podeConcluir()) {
            return $this; // Já está concluído
        }

        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            userId: $this->userId,
            sessionId: $this->sessionId,
            email: $this->email,
            onboardingConcluido: true,
            etapasConcluidas: $this->etapasConcluidas,
            checklist: $this->checklist,
            progressoPercentual: 100,
            iniciadoEm: $this->iniciadoEm,
            concluidoEm: Carbon::now(),
        );
    }

    /**
     * Regra de negócio: Verificar se está concluído
     */
    public function estaConcluido(): bool
    {
        return $this->onboardingConcluido;
    }

    /**
     * Regra de negócio: Obter identificador principal
     */
    public function getIdentificadorPrincipal(): ?string
    {
        if ($this->userId) {
            return "user_id:{$this->userId}";
        }
        if ($this->sessionId) {
            return "session_id:{$this->sessionId}";
        }
        if ($this->email) {
            return "email:{$this->email}";
        }
        if ($this->tenantId) {
            return "tenant_id:{$this->tenantId}";
        }
        return null;
    }
}


