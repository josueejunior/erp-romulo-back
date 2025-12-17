<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use App\Models\Assinatura;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obter tenant do request
        $tenantId = $request->header('X-Tenant-ID') 
            ?? $request->input('tenant_id')
            ?? tenancy()->tenant?->id;

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant ID não fornecido.',
                'code' => 'TENANT_ID_REQUIRED'
            ], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado.',
                'code' => 'TENANT_NOT_FOUND'
            ], 404);
        }

        // Verificar assinatura
        $assinatura = $tenant->assinaturaAtual;

        if (!$assinatura) {
            return response()->json([
                'message' => 'Nenhuma assinatura encontrada. Contrate um plano para continuar usando o sistema.',
                'code' => 'NO_SUBSCRIPTION',
                'action' => 'subscribe'
            ], 403);
        }

        // Verificar se está ativa
        if (!$assinatura->isAtiva()) {
            $diasExpirado = $assinatura->diasRestantes() * -1;
            
            if ($assinatura->estaNoGracePeriod()) {
                // Ainda no período de tolerância - permitir acesso mas avisar
                return $next($request)->withHeaders([
                    'X-Subscription-Warning' => 'true',
                    'X-Subscription-Expired-Days' => $diasExpirado
                ]);
            }

            // Expirada - bloquear acesso
            return response()->json([
                'message' => 'Sua assinatura expirou em ' . $assinatura->data_fim->format('d/m/Y') . '. Renove sua assinatura para continuar usando o sistema.',
                'code' => 'SUBSCRIPTION_EXPIRED',
                'data_vencimento' => $assinatura->data_fim->format('Y-m-d'),
                'dias_expirado' => $diasExpirado,
                'action' => 'renew'
            ], 403);
        }

        // Verificar se está suspensa
        if ($assinatura->status === 'suspensa') {
            return response()->json([
                'message' => 'Sua assinatura está suspensa. Entre em contato com o suporte.',
                'code' => 'SUBSCRIPTION_SUSPENDED',
                'action' => 'contact_support'
            ], 403);
        }

        return $next($request);
    }
}

