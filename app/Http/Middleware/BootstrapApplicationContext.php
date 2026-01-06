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
        $path = $request->path();
        Log::debug('➡ BootstrapApplicationContext entrou', ['path' => $path]);
        error_log("[BOOTSTRAP] ENTROU - path: {$path}");

        try {
            error_log("[BOOTSTRAP] Iniciando try");
            
            // Verificar se usuário está autenticado
            $user = auth('sanctum')->user();
            error_log("[BOOTSTRAP] User obtido: " . ($user ? $user->getKey() : 'null'));
            
            if (!$user) {
                error_log("[BOOTSTRAP] Usuario nao autenticado");
                Log::warning('BootstrapApplicationContext: Usuário não autenticado');
                return response()->json([
                    'message' => 'Não autenticado. Faça login para continuar.',
                ], 401);
            }

            // Se for admin, não precisa de bootstrap
            if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
                error_log("[BOOTSTRAP] Admin detectado, pulando");
                Log::debug('⬅ BootstrapApplicationContext: admin, pulando');
                return $next($request);
            }

            // Resolver ApplicationContext do container
            error_log("[BOOTSTRAP] Verificando se ApplicationContextContract esta bound");
            if (!app()->bound(ApplicationContextContract::class)) {
                error_log("[BOOTSTRAP] ApplicationContextContract NAO registrado");
                Log::warning('BootstrapApplicationContext: ApplicationContextContract não registrado');
                // Continuar sem bootstrap - não bloquear a requisição
                return $next($request);
            }

            error_log("[BOOTSTRAP] Resolvendo ApplicationContext");
            $context = app(ApplicationContextContract::class);
            
            // Bootstrap do ApplicationContext
            error_log("[BOOTSTRAP] Chamando context->bootstrap()");
            $context->bootstrap($request);
            
            error_log("[BOOTSTRAP] Bootstrap OK");
            Log::debug('⬅ BootstrapApplicationContext: bootstrap OK', [
                'tenant_id' => method_exists($context, 'getTenantIdOrNull') ? $context->getTenantIdOrNull() : null,
                'empresa_id' => method_exists($context, 'getEmpresaIdOrNull') ? $context->getEmpresaIdOrNull() : null,
            ]);

        } catch (\Throwable $e) {
            // Capturar QUALQUER erro (Exception, Error, TypeError, etc.)
            error_log("[BOOTSTRAP] ERRO CAPTURADO: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
            Log::error('BootstrapApplicationContext: ERRO', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
            ]);
            
            // Não bloquear a requisição - deixar o controller decidir
        }

        error_log("[BOOTSTRAP] Chamando next()");
        return $next($request);
    }
}
