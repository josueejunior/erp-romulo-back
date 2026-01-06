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
        // Log no construtor para verificar se está sendo instanciado
        error_log('EnsureEmpresaAtivaContext::__construct - CONSTRUTOR EXECUTADO');
        \Log::emergency('EnsureEmpresaAtivaContext::__construct - CONSTRUTOR EXECUTADO', [
            'context_class' => get_class($context),
        ]);
    }
    
    /**
     * Handle an incoming request.
     * 
     * 🔥 THIN MIDDLEWARE: Apenas chama o ApplicationContext
     * Toda a lógica está centralizada no ApplicationContext.
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
            \Log::debug('EnsureEmpresaAtivaContext::handle - Chamando context->bootstrap()');
            $startTime = microtime(true);
            $this->context->bootstrap($request);
            $elapsedTime = microtime(true) - $startTime;
            \Log::info('EnsureEmpresaAtivaContext::handle - context->bootstrap() concluído', [
                'elapsed_time' => round($elapsedTime, 3) . 's',
            ]);
            
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

