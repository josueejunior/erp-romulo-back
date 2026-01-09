<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para gerenciamento de onboarding (usuários autenticados)
 * 
 * Usa dados do usuário autenticado automaticamente
 */
class OnboardingController extends BaseApiController
{
    public function __construct(
        private readonly GerenciarOnboardingUseCase $gerenciarOnboardingUseCase,
    ) {}

    /**
     * Obtém status do onboarding do usuário autenticado
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
            tenantId: $user->tenant_id ?? null,
            userId: $user->id,
            sessionId: null,
            email: $user->email ?? null,
        );

        if (!$onboarding) {
            // Se não existe, criar um novo
            $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
                tenantId: $user->tenant_id ?? null,
                userId: $user->id,
                sessionId: null,
                email: $user->email ?? null,
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_id' => $onboarding->id,
                'progresso_percentual' => $onboarding->progresso_percentual,
                'onboarding_concluido' => $onboarding->onboarding_concluido,
                'etapas_concluidas' => $onboarding->etapas_concluidas ?? [],
                'checklist' => $onboarding->checklist ?? [],
            ],
        ]);
    }

    /**
     * Marca uma etapa como concluída
     */
    public function marcarEtapa(Request $request): JsonResponse
    {
        $request->validate([
            'etapa' => 'required|string|max:100',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        // Buscar onboarding atual
        $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
            tenantId: $user->tenant_id ?? null,
            userId: $user->id,
            sessionId: null,
            email: $user->email ?? null,
        );

        if (!$onboarding) {
            // Criar se não existir
            $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
                tenantId: $user->tenant_id ?? null,
                userId: $user->id,
                sessionId: null,
                email: $user->email ?? null,
            );
        }

        $onboarding = $this->gerenciarOnboardingUseCase->marcarEtapaConcluida(
            onboardingId: $onboarding->id,
            etapa: $request->input('etapa'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_id' => $onboarding->id,
                'progresso_percentual' => $onboarding->progresso_percentual,
                'etapas_concluidas' => $onboarding->etapas_concluidas,
            ],
        ]);
    }

    /**
     * Conclui o onboarding
     */
    public function concluir(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        // Buscar onboarding atual
        $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
            tenantId: $user->tenant_id ?? null,
            userId: $user->id,
            sessionId: null,
            email: $user->email ?? null,
        );

        if (!$onboarding) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum progresso de onboarding encontrado.',
            ], 404);
        }

        $onboarding = $this->gerenciarOnboardingUseCase->concluir($onboarding->id);

        Log::info('OnboardingController - Onboarding concluído', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'onboarding_id' => $onboarding->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tutorial concluído com sucesso!',
            'data' => [
                'onboarding_id' => $onboarding->id,
                'onboarding_concluido' => $onboarding->onboarding_concluido,
                'concluido_em' => $onboarding->concluido_em,
            ],
        ]);
    }
}

