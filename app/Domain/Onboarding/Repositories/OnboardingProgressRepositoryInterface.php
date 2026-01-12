<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Repositories;

use App\Domain\Onboarding\Entities\OnboardingProgress;

/**
 * Interface do Repository de OnboardingProgress
 * O domínio não sabe se é MySQL, MongoDB, API, etc.
 */
interface OnboardingProgressRepositoryInterface
{
    /**
     * Criar um novo onboarding progress
     */
    public function criar(OnboardingProgress $onboarding): OnboardingProgress;

    /**
     * Buscar onboarding por ID
     */
    public function buscarPorId(int $id): ?OnboardingProgress;

    /**
     * Buscar onboarding por critérios
     * Retorna o mais recente que corresponder aos critérios
     */
    public function buscarPorCritérios(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): ?OnboardingProgress;

    /**
     * Buscar onboarding não concluído por critérios
     */
    public function buscarNaoConcluidoPorCritérios(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): ?OnboardingProgress;

    /**
     * Verificar se existe onboarding concluído por critérios
     */
    public function existeConcluidoPorCritérios(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): bool;

    /**
     * Atualizar onboarding
     */
    public function atualizar(OnboardingProgress $onboarding): OnboardingProgress;

    /**
     * Buscar modelo Eloquent por ID (para compatibilidade com controllers que precisam do model)
     * @param int $id
     * @return \App\Models\OnboardingProgress|null
     */
    public function buscarModeloPorId(int $id): ?\App\Models\OnboardingProgress;
}


