<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Contracts\ApplicationContextContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware thin para inicializar tenancy
 * 
 * 🔥 REFATORADO: Este middleware agora é apenas um proxy.
 * Toda a lógica está centralizada no ApplicationContext.
 */
class InitializeTenancyByRequestData
{
    public function __construct(
        private ApplicationContextContract $context
    ) {
        // Log no construtor para verificar se está sendo instanciado
        error_log('InitializeTenancyByRequestData::__construct - CONSTRUTOR EXECUTADO');
        \Log::emergency('InitializeTenancyByRequestData::__construct - CONSTRUTOR EXECUTADO', [
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
        // Log IMEDIATO - antes de qualquer coisa
        error_log('InitializeTenancyByRequestData::handle - PRIMEIRO LOG (error_log)');
        \Log::emergency('InitializeTenancyByRequestData::handle - ✅ INÍCIO (EMERGENCY)', [
            'path' => $request->path(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'memory' => memory_get_usage(true),
        ]);
        
        try {
            \Log::debug('InitializeTenancyByRequestData::handle - Chamando context->bootstrap()');
            $startTime = microtime(true);
            $this->context->bootstrap($request);
            $elapsedTime = microtime(true) - $startTime;
            \Log::info('InitializeTenancyByRequestData::handle - context->bootstrap() concluído', [
                'elapsed_time' => round($elapsedTime, 3) . 's',
            ]);
            
            \Log::debug('InitializeTenancyByRequestData::handle - Chamando $next($request)');
            $startTime = microtime(true);
            $response = $next($request);
            $elapsedTime = microtime(true) - $startTime;
            
            \Log::info('InitializeTenancyByRequestData::handle - ✅ FIM', [
                'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'elapsed_time' => round($elapsedTime, 3) . 's',
            ]);
            
            return $response;
        } catch (\Exception $e) {
            \Log::error('InitializeTenancyByRequestData::handle - ❌ ERRO', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}







