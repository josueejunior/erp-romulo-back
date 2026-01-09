<?php

declare(strict_types=1);

namespace App\Application\Onboarding\UseCases;

use App\Models\OnboardingProgress;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Gerenciar Onboarding
 * 
 * Gerencia o progresso do tutorial/onboarding do usuário
 */
final class GerenciarOnboardingUseCase
{
    /**
     * Inicia ou retoma onboarding
     * 
     * @param int|null $tenantId
     * @param int|null $userId
     * @param string|null $sessionId
     * @param string|null $email
     * @return OnboardingProgress
     */
    public function iniciar(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): OnboardingProgress {
        // Verificar se já existe
        $query = OnboardingProgress::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($email) {
            $query->where('email', $email);
        }

        $existente = $query->where('onboarding_concluido', false)->first();

        if ($existente) {
            return $existente;
        }

        // Criar novo
        return OnboardingProgress::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'email' => $email,
            'onboarding_concluido' => false,
            'etapas_concluidas' => [],
            'checklist' => [],
            'progresso_percentual' => 0,
            'iniciado_em' => now(),
        ]);
    }

    /**
     * Marca uma etapa como concluída
     * 
     * @param int $onboardingId
     * @param string $etapa Nome da etapa (ex: "processos", "fornecedores", "licitacoes")
     * @return OnboardingProgress
     */
    public function marcarEtapaConcluida(int $onboardingId, string $etapa): OnboardingProgress
    {
        $onboarding = OnboardingProgress::findOrFail($onboardingId);
        
        $etapas = $onboarding->etapas_concluidas ?? [];
        
        if (!in_array($etapa, $etapas)) {
            $etapas[] = $etapa;
        }

        // Calcular progresso (exemplo: 5 etapas = 20% cada)
        $totalEtapas = 5; // Ajustar conforme necessário
        $progresso = min(100, (count($etapas) / $totalEtapas) * 100);

        $onboarding->update([
            'etapas_concluidas' => $etapas,
            'progresso_percentual' => (int) $progresso,
        ]);

        Log::info('GerenciarOnboardingUseCase - Etapa concluída', [
            'onboarding_id' => $onboardingId,
            'etapa' => $etapa,
            'progresso' => $progresso,
        ]);

        return $onboarding->fresh();
    }

    /**
     * Marca item do checklist como concluído
     * 
     * @param int $onboardingId
     * @param string $item Nome do item (ex: "criar_processo", "adicionar_fornecedor")
     * @return OnboardingProgress
     */
    public function marcarChecklistItem(int $onboardingId, string $item): OnboardingProgress
    {
        $onboarding = OnboardingProgress::findOrFail($onboardingId);
        
        $checklist = $onboarding->checklist ?? [];
        
        if (!isset($checklist[$item])) {
            $checklist[$item] = true;
        }

        $onboarding->update([
            'checklist' => $checklist,
        ]);

        return $onboarding->fresh();
    }

    /**
     * Conclui o onboarding
     * 
     * @param int $onboardingId
     * @return OnboardingProgress
     */
    public function concluir(int $onboardingId): OnboardingProgress
    {
        $onboarding = OnboardingProgress::findOrFail($onboardingId);
        
        $onboarding->update([
            'onboarding_concluido' => true,
            'progresso_percentual' => 100,
            'concluido_em' => now(),
        ]);

        Log::info('GerenciarOnboardingUseCase - Onboarding concluído', [
            'onboarding_id' => $onboardingId,
            'tenant_id' => $onboarding->tenant_id,
            'user_id' => $onboarding->user_id,
        ]);

        return $onboarding->fresh();
    }

    /**
     * Verifica se onboarding está concluído
     * 
     * @param int|null $tenantId
     * @param int|null $userId
     * @param string|null $sessionId
     * @param string|null $email
     * @return bool
     */
    public function estaConcluido(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): bool {
        $query = OnboardingProgress::query();
        
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

        $onboarding = $query->where('onboarding_concluido', true)->first();
        
        return $onboarding !== null;
    }

    /**
     * Busca progresso atual
     * 
     * @param int|null $tenantId
     * @param int|null $userId
     * @param string|null $sessionId
     * @param string|null $email
     * @return OnboardingProgress|null
     */
    public function buscarProgresso(
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $email = null
    ): ?OnboardingProgress {
        $query = OnboardingProgress::query();
        
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

        return $query->orderBy('created_at', 'desc')->first();
    }
}


