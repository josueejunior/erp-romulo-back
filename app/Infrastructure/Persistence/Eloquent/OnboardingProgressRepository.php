<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Onboarding\Entities\OnboardingProgress;
use App\Domain\Onboarding\Repositories\OnboardingProgressRepositoryInterface;
use App\Models\OnboardingProgress as OnboardingProgressModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        // 🔥 CORREÇÃO: Priorizar email (global) sobre userId (tenant-specific)
        // Se temos email, buscar por email (mais estável e global)
        if ($email) {
            $query->where('email', $email);
        }
        // Se não temos email mas temos userId, buscar por userId
        elseif ($userId) {
            $query->where('user_id', $userId);
        }
        // Se temos tenant_id, buscar por tenant_id
        elseif ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        // Por último, tentar sessionId
        elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return null;
        }

        // 🔥 MELHORIA: Priorizar onboarding concluído (mais importante)
        // Ordenar por: concluído primeiro, depois por data de criação (mais recente)
        // 🔥 CORREÇÃO: Usar orderByRaw para garantir que true vem antes de false
        $model = $query->orderByRaw('onboarding_concluido DESC NULLS LAST')
                      ->orderBy('created_at', 'desc')
                      ->first();
        
        Log::info('OnboardingProgressRepository::buscarPorCritérios - Busca realizada', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email' => $email,
            'session_id' => $sessionId,
            'encontrado' => $model !== null,
            'onboarding_id' => $model?->id,
            'onboarding_concluido' => $model?->onboarding_concluido,
            'onboarding_concluido_type' => gettype($model?->onboarding_concluido),
            'tenant_id_encontrado' => $model?->tenant_id,
            'user_id_encontrado' => $model?->user_id,
            'concluido_em' => $model?->concluido_em?->toIso8601String(),
        ]);
        
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarNaoConcluidoPorCritérios(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): ?OnboardingProgress {
        $query = OnboardingProgressModel::query();

        // 🔥 CORREÇÃO: Priorizar email (global) sobre userId
        if ($email) {
            $query->where('email', $email);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } elseif ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
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

        // 🔥 CORREÇÃO: Priorizar email (global) sobre userId
        if ($email) {
            $query->where('email', $email);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } elseif ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return false;
        }

        $existe = $query->where('onboarding_concluido', true)->exists();
        
        Log::info('OnboardingProgressRepository::existeConcluidoPorCritérios - Verificação realizada', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email' => $email,
            'session_id' => $sessionId,
            'existe' => $existe,
        ]);
        
        return $existe;
    }

    public function atualizar(OnboardingProgress $onboarding): OnboardingProgress
    {
        if ($onboarding->id === null) {
            throw new \DomainException('Não é possível atualizar um onboarding sem ID.');
        }

        $model = OnboardingProgressModel::findOrFail($onboarding->id);
        
        // 🔥 CORREÇÃO: Log antes de atualizar para debug
        $dataToUpdate = $this->toArray($onboarding);
        Log::info('OnboardingProgressRepository::atualizar - Dados antes de atualizar', [
            'onboarding_id' => $onboarding->id,
            'onboarding_concluido_entidade' => $onboarding->onboardingConcluido,
            'onboarding_concluido_array' => $dataToUpdate['onboarding_concluido'],
            'onboarding_concluido_banco_antes' => $model->onboarding_concluido,
            'concluido_em_entidade' => $onboarding->concluidoEm?->toIso8601String(),
            'concluido_em_array' => $dataToUpdate['concluido_em'] ?? null,
            'data_completa' => $dataToUpdate,
        ]);
        
        // 🔥 CORREÇÃO: Garantir que o campo booleano está sendo salvo corretamente
        // Usar update direto com cast explícito
        $model->onboarding_concluido = (bool) $dataToUpdate['onboarding_concluido'];
        if (isset($dataToUpdate['concluido_em'])) {
            $model->concluido_em = $dataToUpdate['concluido_em'];
        }
        $model->etapas_concluidas = $dataToUpdate['etapas_concluidas'];
        $model->checklist = $dataToUpdate['checklist'];
        $model->progresso_percentual = $dataToUpdate['progresso_percentual'];
        $model->save();
        
        // 🔥 CORREÇÃO: Verificar se foi salvo corretamente
        $model->refresh();
        Log::info('OnboardingProgressRepository::atualizar - Dados após atualizar', [
            'onboarding_id' => $model->id,
            'onboarding_concluido_banco_depois' => $model->onboarding_concluido,
            'concluido_em_banco_depois' => $model->concluido_em?->toIso8601String(),
        ]);
        
        return $this->toDomain($model);
    }

    public function buscarModeloPorId(int $id): ?OnboardingProgressModel
    {
        return OnboardingProgressModel::find($id);
    }
}





