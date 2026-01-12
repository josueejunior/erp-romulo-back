<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureTenantHasActiveSubscription
 * 
 * Valida que o tenant possui assinatura ativa antes de permitir acesso
 * 
 * ✅ DDD: Regra de domínio movida para middleware
 * Controller não precisa mais saber sobre assinaturas
 */
class EnsureTenantHasActiveSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;
        
        if (!$tenant || !$tenant->temAssinaturaAtiva()) {
            return response()->json([
                'success' => false,
                'code' => 'NO_SUBSCRIPTION',
                'message' => 'Para acessar este recurso, é necessário contratar um dos nossos planos. Consulte nossos planos e preços para continuar.',
                'action' => 'subscribe',
            ], 403);
        }

        return $next($request);
    }
}





