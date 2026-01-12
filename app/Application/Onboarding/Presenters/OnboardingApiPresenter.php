<?php

declare(strict_types=1);

namespace App\Application\Onboarding\Presenters;

use App\Models\OnboardingProgress as OnboardingProgressModel;

/**
 * Presenter para serialização de OnboardingProgress na API
 * 
 * ✅ Responsabilidade única: transformar modelos Eloquent em arrays para resposta JSON
 * ✅ Remove lógica de serialização do Controller
 * ✅ Facilita testes e mudanças de formato
 */
class OnboardingApiPresenter
{
    /**
     * Transforma um modelo OnboardingProgress em array para resposta da API
     */
    public function present(OnboardingProgressModel $onboarding): array
    {
        return [
            'onboarding_id' => $onboarding->id,
            'onboarding_concluido' => $onboarding->onboarding_concluido ?? false,
            'progresso_percentual' => $onboarding->progresso_percentual ?? 0,
            'etapas_concluidas' => $onboarding->etapas_concluidas ?? [],
            'checklist' => $onboarding->checklist ?? [],
            'pode_ver_planos' => $onboarding->onboarding_concluido ?? false,
            'iniciado_em' => $onboarding->iniciado_em?->toIso8601String(),
            'concluido_em' => $onboarding->concluido_em?->toIso8601String(),
            'created_at' => $onboarding->created_at?->toIso8601String(),
            'updated_at' => $onboarding->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Transforma uma entidade de domínio OnboardingProgress em array para resposta da API
     */
    public function presentDomain(\App\Domain\Onboarding\Entities\OnboardingProgress $onboarding): array
    {
        return [
            'onboarding_id' => $onboarding->id,
            'onboarding_concluido' => $onboarding->onboardingConcluido,
            'progresso_percentual' => $onboarding->progressoPercentual,
            'etapas_concluidas' => $onboarding->etapasConcluidas,
            'checklist' => $onboarding->checklist,
            'pode_ver_planos' => $onboarding->onboardingConcluido,
            'iniciado_em' => $onboarding->iniciadoEm?->toIso8601String(),
            'concluido_em' => $onboarding->concluidoEm?->toIso8601String(),
        ];
    }

    /**
     * Transforma uma coleção de modelos OnboardingProgress em array
     */
    public function presentCollection(iterable $onboardings): array
    {
        return array_map(fn($onboarding) => $this->present($onboarding), $onboardings);
    }
}



