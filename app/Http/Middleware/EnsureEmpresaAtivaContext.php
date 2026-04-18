<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Contracts\ApplicationContextContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware thin para garantir que a empresa ativa está definida
 * 
 * 🔥 REFATORADO: Este middleware agora é apenas um proxy.
 * Toda a lógica está centralizada no ApplicationContext.
 */
class EnsureEmpresaAtivaContext
{
    public function __construct(
        private ApplicationContextContract $context
    ) {
        // Construtor sem logs (evita ruído nos logs)
    }
    
    /**
     * Handle an incoming request.
     * 
     * 🔥 THIN MIDDLEWARE: Apenas garante que o ApplicationContext está inicializado
     * Toda a lógica está centralizada no ApplicationContext.
     * 
     * ✅ OTIMIZAÇÃO: Verifica se já está inicializado antes de chamar bootstrap()
     * para evitar chamadas redundantes (o bootstrap() já é idempotente, mas evita logs de warning)
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('EnsureEmpresaAtivaContext::handle - ✅ INÍCIO', [
            'path' => $request->path(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'auth_check' => auth('sanctum')->check(),
            'user_id' => auth('sanctum')->id(),
            'route' => $request->route() ? $request->route()->getName() : 'NO_ROUTE',
        ]);
        
        try {
            // ✅ OTIMIZAÇÃO: Verificar se já está inicializado antes de chamar bootstrap()
            // Isso evita chamadas redundantes e warnings nos logs
            if (!$this->context->isInitialized()) {
                \Log::debug('EnsureEmpresaAtivaContext::handle - Contexto não inicializado, chamando bootstrap()');
                $startTime = microtime(true);
                $this->context->bootstrap($request);
                $elapsedTime = microtime(true) - $startTime;
                \Log::info('EnsureEmpresaAtivaContext::handle - context->bootstrap() concluído', [
                    'elapsed_time' => round($elapsedTime, 3) . 's',
                ]);
            } else {
                \Log::debug('EnsureEmpresaAtivaContext::handle - Contexto já inicializado, pulando bootstrap()');
            }
            
            \Log::debug('EnsureEmpresaAtivaContext::handle - Chamando $next($request)');
            $startTime = microtime(true);
            $response = $next($request);
            $elapsedTime = microtime(true) - $startTime;
            
            \Log::info('EnsureEmpresaAtivaContext::handle - ✅ FIM', [
                'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'elapsed_time' => round($elapsedTime, 3) . 's',
            ]);
            
            return $response;
        } catch (\Exception $e) {
            \Log::error('EnsureEmpresaAtivaContext::handle - ❌ ERRO', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

