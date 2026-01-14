<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use App\Application\Onboarding\DTOs\BuscarProgressoDTO;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Middleware para verificar se o onboarding foi concluído
 * 
 * Bloqueia acesso a rotas protegidas (ex: /planos) se onboarding não concluído
 * 
 * 🔥 IMPORTANTE: Planos PAGOS não precisam de onboarding - permitir acesso direto
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

        // 🔥 IMPORTANTE: Verificar se é plano PAGO - planos pagos não precisam de onboarding
        try {
            $tenant = tenancy()->tenant;
            if ($tenant) {
                // Verificar se tenant tem plano atual (via relacionamento)
                $plano = $tenant->planoAtual;
                if ($plano) {
                    $isPlanoGratuito = !$plano->preco_mensal || $plano->preco_mensal == 0;
                    
                    // Se é plano pago, permitir acesso direto (sem onboarding)
                    if (!$isPlanoGratuito) {
                        Log::debug('CheckOnboarding - Plano pago detectado, permitindo acesso sem onboarding', [
                            'user_id' => $user->id,
                            'tenant_id' => $tenant->id,
                            'plano_id' => $plano->id,
                            'preco_mensal' => $plano->preco_mensal,
                        ]);
                        return $next($request);
                    }
                }
            }
        } catch (\Exception $e) {
            // Se der erro ao verificar plano, continuar com verificação de onboarding (mais seguro)
            Log::warning('CheckOnboarding - Erro ao verificar plano, continuando com verificação de onboarding', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
        }

        // Para planos gratuitos, verificar se onboarding está concluído
        $dto = new BuscarProgressoDTO(
            tenantId: tenancy()->tenant?->id ?? null,
            userId: $user->id,
            sessionId: null,
            email: $user->email ?? null,
        );

        $estaConcluido = $this->gerenciarOnboardingUseCase->estaConcluido($dto);

        if (!$estaConcluido) {
            Log::info('CheckOnboarding - Acesso bloqueado: onboarding não concluído (plano gratuito)', [
                'user_id' => $user->id,
                'tenant_id' => tenancy()->tenant?->id,
                'route' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Conclua o tutorial para continuar. Este é um passo rápido para você conhecer todas as funcionalidades do sistema.',
                'code' => 'ONBOARDING_NOT_COMPLETED',
                'action' => 'complete_onboarding',
            ], 403);
        }

        return $next($request);
    }
}







