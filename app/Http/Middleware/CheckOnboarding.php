<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Middleware para verificar se o onboarding foi concluído
 * 
 * Bloqueia acesso a rotas protegidas (ex: /planos) se onboarding não concluído
 */
class CheckOnboarding
{
    public function __construct(
        private readonly GerenciarOnboardingUseCase $gerenciarOnboardingUseCase,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuário não autenticado.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Verificar se onboarding está concluído
        $estaConcluido = $this->gerenciarOnboardingUseCase->estaConcluido(
            tenantId: $user->tenant_id ?? null,
            userId: $user->id,
            sessionId: null,
            email: $user->email ?? null,
        );

        if (!$estaConcluido) {
            Log::info('CheckOnboarding - Acesso bloqueado: onboarding não concluído', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'route' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Conclua o tutorial para continuar.',
                'code' => 'ONBOARDING_NOT_COMPLETED',
                'action' => 'complete_onboarding',
            ], 403);
        }

        return $next($request);
    }
}

