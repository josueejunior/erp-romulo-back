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

        // Bootstrap do ApplicationContext
        try {
            $context = app(ApplicationContextContract::class);
            $context->bootstrap($request);
            
            Log::debug('⬅ BootstrapApplicationContext: bootstrap OK', [
                'tenant_id' => $context->getTenantIdOrNull(),
                'empresa_id' => $context->getEmpresaIdOrNull(),
            ]);
        } catch (\Exception $e) {
            Log::error('BootstrapApplicationContext: erro', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $next($request);
    }
}

