<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Onboarding\Entities\OnboardingProgress;
use App\Domain\Onboarding\Repositories\OnboardingProgressRepositoryInterface;
use App\Models\OnboardingProgress as OnboardingProgressModel;
use Carbon\Carbon;

/**
 * Implementação do Repository de OnboardingProgress usando Eloquent
 */
class OnboardingProgressRepository implements OnboardingProgressRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(OnboardingProgressModel $model): OnboardingProgress
    {
        return new OnboardingProgress(
            id: $model->id,
            tenantId: $model->tenant_id,
            userId: $model->user_id,
            sessionId: $model->session_id,
            email: $model->email,
            onboardingConcluido: $model->onboarding_concluido ?? false,
            etapasConcluidas: $model->etapas_concluidas ?? [],
            checklist: $model->checklist ?? [],
            progressoPercentual: $model->progresso_percentual ?? 0,
            iniciadoEm: $model->iniciado_em ? Carbon::parse($model->iniciado_em) : null,
            concluidoEm: $model->concluido_em ? Carbon::parse($model->concluido_em) : null,
        );
    }

    /**
     * Converter entidade do domínio para array do Eloquent
     */
    private function toArray(OnboardingProgress $onboarding): array
    {
        $data = [
            'tenant_id' => $onboarding->tenantId,
            'user_id' => $onboarding->userId,
            'session_id' => $onboarding->sessionId,
            'email' => $onboarding->email,
            'onboarding_concluido' => $onboarding->onboardingConcluido,
            'etapas_concluidas' => $onboarding->etapasConcluidas,
            'checklist' => $onboarding->checklist,
            'progresso_percentual' => $onboarding->progressoPercentual,
        ];

        // Incluir ID apenas se já existir (para updates)
        if ($onboarding->id !== null) {
            $data['id'] = $onboarding->id;
        }

        // Incluir timestamps específicos
        if ($onboarding->iniciadoEm) {
            $data['iniciado_em'] = $onboarding->iniciadoEm->toDateTimeString();
        }

        if ($onboarding->concluidoEm) {
            $data['concluido_em'] = $onboarding->concluidoEm->toDateTimeString();
        }

        return $data;
    }

    public function criar(OnboardingProgress $onboarding): OnboardingProgress
    {
        $data = $this->toArray($onboarding);
        
        // Se não tem iniciadoEm, definir como agora
        if (!isset($data['iniciado_em'])) {
            $data['iniciado_em'] = now();
        }

        $model = OnboardingProgressModel::create($data);
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?OnboardingProgress
    {
        $model = OnboardingProgressModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorCritérios(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): ?OnboardingProgress {
        $query = OnboardingProgressModel::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($email) {
            $query->where('email', $email);
        } else {
            return null;
        }

        $model = $query->orderBy('created_at', 'desc')->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarNaoConcluidoPorCritérios(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): ?OnboardingProgress {
        $query = OnboardingProgressModel::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($email) {
            $query->where('email', $email);
        } else {
            return null;
        }

        $model = $query->where('onboarding_concluido', false)->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function existeConcluidoPorCritérios(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): bool {
        $query = OnboardingProgressModel::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($email) {
            $query->where('email', $email);
        } else {
            return false;
        }

        return $query->where('onboarding_concluido', true)->exists();
    }

    public function atualizar(OnboardingProgress $onboarding): OnboardingProgress
    {
        if ($onboarding->id === null) {
            throw new \DomainException('Não é possível atualizar um onboarding sem ID.');
        }

        $model = OnboardingProgressModel::findOrFail($onboarding->id);
        $model->update($this->toArray($onboarding));
        return $this->toDomain($model->fresh());
    }

    public function buscarModeloPorId(int $id): ?OnboardingProgressModel
    {
        return OnboardingProgressModel::find($id);
    }
}

