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
     * 
     * 🔥 MELHORIA: Inclui last_step_recorded e next_recommended_step para hydration
     */
    public function present(OnboardingProgressModel $onboarding): array
    {
        // Converter para entidade de domínio para usar métodos de cálculo
        $onboardingDomain = $this->modelToDomain($onboarding);
        
        // 🔥 MELHORIA: Calcular próxima etapa recomendada
        $todasEtapas = ['welcome', 'dashboard', 'processos', 'orcamentos', 'fornecedores', 'documentos', 'orgaos', 'setores', 'complete'];
        $proximaEtapa = $onboardingDomain->getProximaEtapaRecomendada($todasEtapas);
        $ultimaEtapa = $onboardingDomain->getUltimaEtapaRegistrada();

        return [
            'onboarding_id' => $onboarding->id,
            'onboarding_concluido' => $onboarding->onboarding_concluido ?? false,
            'progresso_percentual' => $onboarding->progresso_percentual ?? 0,
            'etapas_concluidas' => $onboarding->etapas_concluidas ?? [],
            'checklist' => $onboarding->checklist ?? [],
            'pode_ver_planos' => $onboarding->onboarding_concluido ?? false,
            'last_step_recorded' => $ultimaEtapa, // 🔥 NOVO: Última etapa registrada (para hydration)
            'next_recommended_step' => $proximaEtapa, // 🔥 NOVO: Próxima etapa recomendada
            'iniciado_em' => $onboarding->iniciado_em?->toIso8601String(),
            'concluido_em' => $onboarding->concluido_em?->toIso8601String(),
            'created_at' => $onboarding->created_at?->toIso8601String(),
            'updated_at' => $onboarding->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Helper: Converte modelo Eloquent para entidade de domínio
     */
    private function modelToDomain(OnboardingProgressModel $model): \App\Domain\Onboarding\Entities\OnboardingProgress
    {
        return new \App\Domain\Onboarding\Entities\OnboardingProgress(
            id: $model->id,
            tenantId: $model->tenant_id,
            userId: $model->user_id,
            sessionId: $model->session_id,
            email: $model->email,
            onboardingConcluido: $model->onboarding_concluido ?? false,
            etapasConcluidas: $model->etapas_concluidas ?? [],
            checklist: $model->checklist ?? [],
            progressoPercentual: $model->progresso_percentual ?? 0,
            iniciadoEm: $model->iniciado_em,
            concluidoEm: $model->concluido_em,
        );
    }

    /**
     * Transforma uma entidade de domínio OnboardingProgress em array para resposta da API
     * 
     * 🔥 MELHORIA: Inclui last_step_recorded e next_recommended_step para hydration
     */
    public function presentDomain(\App\Domain\Onboarding\Entities\OnboardingProgress $onboarding): array
    {
        // 🔥 MELHORIA: Calcular próxima etapa recomendada
        $todasEtapas = ['welcome', 'dashboard', 'processos', 'orcamentos', 'fornecedores', 'documentos', 'orgaos', 'setores', 'complete'];
        $proximaEtapa = $onboarding->getProximaEtapaRecomendada($todasEtapas);
        $ultimaEtapa = $onboarding->getUltimaEtapaRegistrada();

        return [
            'onboarding_id' => $onboarding->id,
            'onboarding_concluido' => $onboarding->onboardingConcluido,
            'progresso_percentual' => $onboarding->progressoPercentual,
            'etapas_concluidas' => $onboarding->etapasConcluidas,
            'checklist' => $onboarding->checklist,
            'pode_ver_planos' => $onboarding->onboardingConcluido,
            'last_step_recorded' => $ultimaEtapa, // 🔥 NOVO: Última etapa registrada (para hydration)
            'next_recommended_step' => $proximaEtapa, // 🔥 NOVO: Próxima etapa recomendada
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




