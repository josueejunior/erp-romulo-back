<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para gerenciamento de onboarding
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly GerenciarOnboardingUseCase $gerenciarOnboardingUseCase,
    ) {}

    /**
     * Inicia ou retoma onboarding
     */
    public function iniciar(Request $request): JsonResponse
    {
        $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
            tenantId: $request->input('tenant_id'),
            userId: $request->input('user_id'),
            sessionId: $request->input('session_id') ?? $request->session()->getId(),
            email: $request->input('email'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_id' => $onboarding->id,
                'progresso_percentual' => $onboarding->progresso_percentual,
                'onboarding_concluido' => $onboarding->onboarding_concluido,
                'etapas_concluidas' => $onboarding->etapas_concluidas,
            ],
        ]);
    }

    /**
     * Marca uma etapa como concluída
     */
    public function marcarEtapa(Request $request): JsonResponse
    {
        $request->validate([
            'onboarding_id' => 'required|integer',
            'etapa' => 'required|string|max:100',
        ]);

        $onboarding = $this->gerenciarOnboardingUseCase->marcarEtapaConcluida(
            onboardingId: $request->input('onboarding_id'),
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
     * Marca item do checklist como concluído
     */
    public function marcarChecklistItem(Request $request): JsonResponse
    {
        $request->validate([
            'onboarding_id' => 'required|integer',
            'item' => 'required|string|max:100',
        ]);

        $onboarding = $this->gerenciarOnboardingUseCase->marcarChecklistItem(
            onboardingId: $request->input('onboarding_id'),
            item: $request->input('item'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_id' => $onboarding->id,
                'checklist' => $onboarding->checklist,
            ],
        ]);
    }

    /**
     * Conclui o onboarding
     */
    public function concluir(Request $request): JsonResponse
    {
        $request->validate([
            'onboarding_id' => 'required|integer',
        ]);

        $onboarding = $this->gerenciarOnboardingUseCase->concluir(
            onboardingId: $request->input('onboarding_id'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Onboarding concluído com sucesso!',
            'data' => [
                'onboarding_id' => $onboarding->id,
                'onboarding_concluido' => $onboarding->onboarding_concluido,
                'concluido_em' => $onboarding->concluido_em,
            ],
        ]);
    }

    /**
     * Verifica se onboarding está concluído
     */
    public function verificarStatus(Request $request): JsonResponse
    {
        $estaConcluido = $this->gerenciarOnboardingUseCase->estaConcluido(
            tenantId: $request->input('tenant_id'),
            userId: $request->input('user_id'),
            sessionId: $request->input('session_id') ?? $request->session()->getId(),
            email: $request->input('email'),
        );

        return response()->json([
            'success' => true,
            'onboarding_concluido' => $estaConcluido,
            'pode_ver_planos' => $estaConcluido, // Se onboarding concluído, pode ver planos
        ]);
    }

    /**
     * Busca progresso atual
     */
    public function buscarProgresso(Request $request): JsonResponse
    {
        $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
            tenantId: $request->input('tenant_id'),
            userId: $request->input('user_id'),
            sessionId: $request->input('session_id') ?? $request->session()->getId(),
            email: $request->input('email'),
        );

        if (!$onboarding) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum progresso de onboarding encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_id' => $onboarding->id,
                'progresso_percentual' => $onboarding->progresso_percentual,
                'onboarding_concluido' => $onboarding->onboarding_concluido,
                'etapas_concluidas' => $onboarding->etapas_concluidas,
                'checklist' => $onboarding->checklist,
            ],
        ]);
    }
}

