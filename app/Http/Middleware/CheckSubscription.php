<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Contracts\ApplicationContextContract;
use App\Domain\Assinatura\Services\SubscriptionAccessService;
use Illuminate\Support\Facades\Log;

/**
 * Middleware para validar assinatura ativa
 * 
 * 🔥 REGRA DE OURO: A identidade (quem é o usuário) deve ser estabelecida ANTES
 * de qualquer lógica de negócio (qual empresa/plano ele acessa).
 * 
 * Este middleware DEVE rodar APÓS:
 * 1. auth:sanctum (identidade estabelecida)
 * 2. InitializeTenancyByRequestData (tenant inicializado)
 * 3. EnsureEmpresaAtivaContext (empresa definida)
 * 
 * Valida a "Trindade": Usuário + Empresa + Plano
 * - O usuário pertence a esta empresa?
 * - Esta empresa pertence a este Tenant?
 * - Este Tenant possui uma assinatura active ou trialing?
 */
/**
 * Middleware thin para validar assinatura ativa
 * 
 * 🔥 REFATORADO: Este middleware agora é apenas um proxy.
 * Toda a lógica está centralizada no ApplicationContext.
 */
class CheckSubscription
{
    public function __construct(
        private ApplicationContextContract $context,
        private SubscriptionAccessService $subscriptionAccessService,
    ) {}

    /**
     * Handle an incoming request.
     * 
     * 🔥 THIN MIDDLEWARE: Apenas chama o ApplicationContext
     * Toda a lógica está centralizada no ApplicationContext.
     * 
     * 🔥 EXCEÇÃO: Dashboard deve estar acessível para planos gratuitos (onboarding)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Garantir que o contexto foi inicializado
        if (!$this->context->isInitialized()) {
            $this->context->bootstrap($request);
        }
        
        // ✅ DDD: Usar Domain Service para verificar se rota está isenta de validação
        $routeName = $request->route()?->getName() ?? '';
        $path = $request->path();
        
        if ($this->subscriptionAccessService->isRouteExemptFromSubscriptionCheck($routeName, $path)) {
            Log::debug('CheckSubscription - Rota isenta de validação de assinatura (ex: dashboard para onboarding)', [
                'user_id' => $this->context->getUser()?->id,
                'route' => $routeName,
                'path' => $path,
            ]);
            return $next($request);
        }
        
        // Verificar assinatura
        $resultado = $this->context->validateAssinatura();
        
        if (!$resultado['pode_acessar']) {
            Log::warning('CheckSubscription - Acesso negado', [
                'user_id' => $this->context->getUser()?->id,
                'code' => $resultado['code'] ?? null,
                'message' => $resultado['message'] ?? null,
            ]);
            
            return response()->json([
                'message' => $resultado['message'] ?? 'Sua empresa não possui um plano ativo.',
                'code' => $resultado['code'] ?? 'SUBSCRIPTION_REQUIRED',
                'action' => $resultado['action'] ?? 'subscribe',
                'data_vencimento' => $resultado['data_vencimento'] ?? null,
                'dias_expirado' => $resultado['dias_expirado'] ?? null,
            ], 403);
        }

        // Se pode acessar mas tem warning (grace period), adicionar headers
        if (isset($resultado['warning']) && $resultado['warning']) {
            return $next($request)->withHeaders([
                'X-Subscription-Warning' => 'true',
                'X-Subscription-Expired-Days' => $resultado['warning']['dias_expirado'] ?? 0,
            ]);
        }

        // Tudo OK, permitir acesso
        return $next($request);
    }
}
