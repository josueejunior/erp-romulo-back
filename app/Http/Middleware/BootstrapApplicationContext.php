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
 * 
 * ✅ Faz:
 * - Chama ApplicationContext::bootstrap()
 * - Resolve empresa ativa
 * - Valida assinatura (se necessário)
 * 
 * ❌ NUNCA faz:
 * - Autenticação (já foi feita)
 * - Tenancy (já foi inicializado por ResolveTenantContext)
 * - Validação de regras de negócio
 * 
 * 📌 Nota: Este middleware só deve rodar APÓS ResolveTenantContext
 */
class BootstrapApplicationContext
{
    public function __construct(
        private ApplicationContextContract $context
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Log::info('BootstrapApplicationContext::handle - ✅ INÍCIO', [
            'path' => $request->path(),
        ]);

        // Verificar se usuário está autenticado
        // 🔥 IMPORTANTE: Usar guard 'sanctum' explicitamente (mesmo guard usado por AuthenticateJWT)
        $user = auth('sanctum')->user();
        
        if (!$user) {
            Log::warning('BootstrapApplicationContext::handle - Usuário não autenticado');
            return response()->json([
                'message' => 'Não autenticado. Faça login para continuar.',
            ], 401);
        }

        // Se for admin, não precisa de bootstrap (não tem empresa/tenant)
        if ($user instanceof \App\Modules\Auth\Models\AdminUser) {
            Log::debug('BootstrapApplicationContext::handle - Admin detectado, pulando bootstrap');
            return $next($request);
        }

        // Bootstrap do ApplicationContext (resolve empresa ativa, valida assinatura, etc.)
        try {
            Log::debug('BootstrapApplicationContext::handle - Iniciando bootstrap');
            $startTime = microtime(true);
            
            $this->context->bootstrap($request);
            
            $elapsed = microtime(true) - $startTime;
            Log::info('BootstrapApplicationContext::handle - ✅ Bootstrap concluído', [
                'elapsed_time' => round($elapsed, 3) . 's',
                'tenant_id' => $this->context->getTenantIdOrNull(),
                'empresa_id' => $this->context->getEmpresaIdOrNull(),
            ]);
        } catch (\Exception $e) {
            Log::error('BootstrapApplicationContext::handle - Erro no bootstrap', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $next($request);
    }
}

