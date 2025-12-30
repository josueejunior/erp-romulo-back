<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use App\Application\Assinatura\UseCases\VerificarAssinaturaAtivaUseCase;

class CheckSubscription
{
    public function __construct(
        private VerificarAssinaturaAtivaUseCase $verificarAssinaturaAtivaUseCase,
    ) {}

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

        // Verificar assinatura usando Use Case DDD
        $resultado = $this->verificarAssinaturaAtivaUseCase->executar($tenant->id);

        // Se não pode acessar, retornar erro
        if (!$resultado['pode_acessar']) {
            return response()->json([
                'message' => $resultado['message'],
                'code' => $resultado['code'],
                'action' => $resultado['action'] ?? null,
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

