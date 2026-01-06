<?php

namespace App\Http\Middleware;

use App\Contracts\ApplicationContextContract;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 CAMADA 6 - Bootstrap de Contexto (Empresa)
 * 
 * Responsabilidade ÚNICA: Inicializar ApplicationContext (empresa ativa)
 */
class BootstrapApplicationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('➡ BootstrapApplicationContext entrou', ['path' => $request->path()]);

        try {
            // Verificar se usuário está autenticado
            $user = auth('sanctum')->user();
            
            if (!$user) {
                Log::warning('BootstrapApplicationContext: Usuário não autenticado');
                return response()->json([
                    'message' => 'Não autenticado. Faça login para continuar.',
                ], 401);
            }

            // Se for admin, não precisa de bootstrap
            if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
                Log::debug('⬅ BootstrapApplicationContext: admin, pulando');
                return $next($request);
            }

            // Resolver ApplicationContext do container
            if (!app()->bound(ApplicationContextContract::class)) {
                Log::warning('BootstrapApplicationContext: ApplicationContextContract não registrado');
                return $next($request);
            }

            $context = app(ApplicationContextContract::class);
            $context->bootstrap($request);
            
            Log::debug('⬅ BootstrapApplicationContext: bootstrap OK', [
                'tenant_id' => method_exists($context, 'getTenantIdOrNull') ? $context->getTenantIdOrNull() : null,
                'empresa_id' => method_exists($context, 'getEmpresaIdOrNull') ? $context->getEmpresaIdOrNull() : null,
            ]);

        } catch (\Throwable $e) {
            Log::error('BootstrapApplicationContext: ERRO', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $next($request);
    }
}
