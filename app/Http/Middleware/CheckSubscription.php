<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Application\Assinatura\UseCases\VerificarAssinaturaAtivaUseCase;
use App\Services\ApplicationContext;
use Illuminate\Support\Facades\Auth;
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
class CheckSubscription
{
    public function __construct(
        private VerificarAssinaturaAtivaUseCase $verificarAssinaturaAtivaUseCase,
        private ApplicationContext $context,
    ) {}

    /**
     * Handle an incoming request.
     * 
     * Fluxo de validação:
     * 1. Garante que o usuário está autenticado (fail-fast)
     * 2. Obtém tenant_id do contexto (já inicializado pelo middleware anterior)
     * 3. Busca assinatura ativa do tenant
     * 4. Valida status da assinatura (active, trialing, ou grace period)
     * 5. Se válida, permite acesso; se não, retorna 403
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Garante que o usuário está autenticado (fail-fast)
        if (!Auth::check()) {
            Log::warning('CheckSubscription - Usuário não autenticado', [
                'url' => $request->url(),
            ]);
            
            return response()->json([
                'message' => 'Não autenticado',
                'code' => 'UNAUTHENTICATED'
            ], 401);
        }

        $user = Auth::user();
        
        // 🔥 NOVO: Validar assinatura do USUÁRIO, não do tenant
        // A assinatura pertence ao usuário, que pode ter acesso a múltiplas empresas/tenants
        
        // 2. Validar se o tenant/empresa pertence ao usuário (opcional, mas recomendado)
        $tenantId = $this->context->getTenantIdOrNull();
        $empresaId = $this->context->getEmpresaIdOrNull();
        
        // Se empresaId foi fornecido, validar que o usuário tem acesso a ela
        if ($empresaId && $user->empresa_ativa_id !== $empresaId) {
            // Verificar se o usuário tem acesso a esta empresa
            $temAcesso = $user->empresas()->where('empresas.id', $empresaId)->exists();
            if (!$temAcesso) {
                Log::warning('CheckSubscription - Usuário não tem acesso à empresa', [
                    'user_id' => $user->id,
                    'empresa_id' => $empresaId,
                    'empresa_ativa_id' => $user->empresa_ativa_id,
                ]);
                
                return response()->json([
                    'message' => 'Você não tem acesso a esta empresa.',
                    'code' => 'COMPANY_ACCESS_DENIED'
                ], 403);
            }
        }

        // 3. Busca assinatura ativa do USUÁRIO
        Log::info('CheckSubscription - Validando assinatura do usuário', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'empresa_id' => $empresaId,
        ]);

        // Verificar assinatura usando Use Case DDD (por userId)
        $resultado = $this->verificarAssinaturaAtivaUseCase->executar($user->id);
        
        Log::info('CheckSubscription - Resultado da verificação', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'pode_acessar' => $resultado['pode_acessar'] ?? false,
            'code' => $resultado['code'] ?? null,
        ]);

        // 4. Valida status da assinatura
        if (!$resultado['pode_acessar']) {
            Log::warning('CheckSubscription - Acesso negado', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
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

        // 5. Se pode acessar mas tem warning (grace period), adicionar headers
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
