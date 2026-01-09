<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

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
     * Se onboarding_id não fornecido, busca automaticamente pelo usuário autenticado
     */
    public function marcarEtapa(Request $request): JsonResponse
    {
        $request->validate([
            'etapa' => 'required|string|max:100',
            'onboarding_id' => 'nullable|integer', // Opcional - buscará automaticamente se não fornecido
        ]);

        // Buscar onboarding_id se não fornecido
        $onboardingId = $request->input('onboarding_id');
        
        if (!$onboardingId) {
            // Buscar automaticamente pelo usuário autenticado ou pelos parâmetros fornecidos
            $user = $request->user();
            $tenantId = $request->input('tenant_id') ?? ($user ? (Tenancy::tenant()?->id ?? null) : null);
            $userId = $request->input('user_id') ?? ($user?->id ?? null);
            $sessionId = $request->input('session_id');
            $email = $request->input('email') ?? ($user?->email ?? null);

            if (!$userId && !$sessionId && !$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não identificado. Forneça user_id, session_id ou email, ou faça login.',
                ], 400);
            }

            // Buscar progresso atual
            $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: $sessionId,
                email: $email,
            );

            if (!$onboarding) {
                // Criar novo onboarding se não existir
                $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
                    tenantId: $tenantId,
                    userId: $userId,
                    sessionId: $sessionId,
                    email: $email,
                );
            }

            $onboardingId = $onboarding->id;
        }

        $onboarding = $this->gerenciarOnboardingUseCase->marcarEtapaConcluida(
            onboardingId: $onboardingId,
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
     * Se onboarding_id não fornecido, busca automaticamente pelo usuário autenticado
     */
    public function marcarChecklistItem(Request $request): JsonResponse
    {
        $request->validate([
            'item' => 'required|string|max:100',
            'onboarding_id' => 'nullable|integer', // Opcional - buscará automaticamente se não fornecido
        ]);

        // Buscar onboarding_id se não fornecido
        $onboardingId = $request->input('onboarding_id');
        
        if (!$onboardingId) {
            // Buscar automaticamente pelo usuário autenticado ou pelos parâmetros fornecidos
            $user = $request->user();
            $tenantId = $request->input('tenant_id') ?? ($user ? (Tenancy::tenant()?->id ?? null) : null);
            $userId = $request->input('user_id') ?? ($user?->id ?? null);
            $sessionId = $request->input('session_id');
            $email = $request->input('email') ?? ($user?->email ?? null);

            if (!$userId && !$sessionId && !$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não identificado. Forneça user_id, session_id ou email, ou faça login.',
                ], 400);
            }

            // Buscar progresso atual
            $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: $sessionId,
                email: $email,
            );

            if (!$onboarding) {
                // Criar novo onboarding se não existir
                $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
                    tenantId: $tenantId,
                    userId: $userId,
                    sessionId: $sessionId,
                    email: $email,
                );
            }

            $onboardingId = $onboarding->id;
        }

        $onboarding = $this->gerenciarOnboardingUseCase->marcarChecklistItem(
            onboardingId: $onboardingId,
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
     * Se onboarding_id não fornecido, busca automaticamente pelo usuário autenticado
     */
    public function concluir(Request $request): JsonResponse
    {
        $request->validate([
            'onboarding_id' => 'nullable|integer', // Opcional - buscará automaticamente se não fornecido
        ]);

        // Buscar onboarding_id se não fornecido
        $onboardingId = $request->input('onboarding_id');
        
        if (!$onboardingId) {
            // Buscar automaticamente pelo usuário autenticado ou pelos parâmetros fornecidos
            $user = $request->user();
            $tenantId = $request->input('tenant_id') ?? ($user ? (Tenancy::tenant()?->id ?? null) : null);
            $userId = $request->input('user_id') ?? ($user?->id ?? null);
            $sessionId = $request->input('session_id');
            $email = $request->input('email') ?? ($user?->email ?? null);

            if (!$userId && !$sessionId && !$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não identificado. Forneça user_id, session_id ou email, ou faça login.',
                ], 400);
            }

            // Buscar progresso atual
            $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: $sessionId,
                email: $email,
            );

            if (!$onboarding) {
                // Criar novo onboarding se não existir
                $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
                    tenantId: $tenantId,
                    userId: $userId,
                    sessionId: $sessionId,
                    email: $email,
                );
            }

            $onboardingId = $onboarding->id;
        }

        $onboarding = $this->gerenciarOnboardingUseCase->concluir(
            onboardingId: $onboardingId,
        );

        Log::info('OnboardingController::concluir - Onboarding concluído', [
            'onboarding_id' => $onboarding->id,
            'user_id' => $request->user()?->id,
            'tenant_id' => Tenancy::tenant()?->id,
        ]);

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
     * Retorna status completo do onboarding (incluindo dados completos)
     */
    public function verificarStatus(Request $request): JsonResponse
    {
        // Buscar dados do usuário autenticado se disponível
        $user = $request->user();
        $tenantId = $request->input('tenant_id') ?? ($user ? (Tenancy::tenant()?->id ?? null) : null);
        $userId = $request->input('user_id') ?? ($user?->id ?? null);
        $sessionId = $request->input('session_id') ?? $request->session()->getId();
        $email = $request->input('email') ?? ($user?->email ?? null);

        // Buscar progresso completo
        $onboarding = $this->gerenciarOnboardingUseCase->buscarProgresso(
            tenantId: $tenantId,
            userId: $userId,
            sessionId: $sessionId,
            email: $email,
        );

        if (!$onboarding) {
            // Se não existe, criar novo onboarding não concluído
            $onboarding = $this->gerenciarOnboardingUseCase->iniciar(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: $sessionId,
                email: $email,
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'onboarding_id' => $onboarding->id,
                'onboarding_concluido' => $onboarding->onboarding_concluido ?? false,
                'progresso_percentual' => $onboarding->progresso_percentual ?? 0,
                'etapas_concluidas' => $onboarding->etapas_concluidas ?? [],
                'checklist' => $onboarding->checklist ?? [],
                'pode_ver_planos' => $onboarding->onboarding_concluido ?? false, // Se onboarding concluído, pode ver planos
            ],
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


