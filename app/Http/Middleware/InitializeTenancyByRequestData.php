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
        private ?ApplicationContextContract $context = null
    ) {
        // Log no construtor para verificar se está sendo instanciado
        error_log('InitializeTenancyByRequestData::__construct - CONSTRUTOR EXECUTADO');
        try {
            \Log::emergency('InitializeTenancyByRequestData::__construct - CONSTRUTOR EXECUTADO', [
                'context_class' => $context ? get_class($context) : 'NULL',
                'context_resolved' => $context !== null,
            ]);
        } catch (\Exception $e) {
            error_log('InitializeTenancyByRequestData::__construct - ERRO NO LOG: ' . $e->getMessage());
        }
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
            'context_exists' => $this->context !== null,
        ]);
        
        // Se não há context, tentar resolver
        if (!$this->context) {
            \Log::warning('InitializeTenancyByRequestData::handle - Context não injetado, tentando resolver');
            try {
                $this->context = app(ApplicationContextContract::class);
                \Log::info('InitializeTenancyByRequestData::handle - Context resolvido via container');
            } catch (\Exception $e) {
                \Log::error('InitializeTenancyByRequestData::handle - Erro ao resolver context', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
        
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







