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
    ) {}
    
    /**
     * Handle an incoming request.
     * 
     * 🔥 THIN MIDDLEWARE: Apenas chama o ApplicationContext
     * Toda a lógica está centralizada no ApplicationContext.
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::debug('EnsureEmpresaAtivaContext::handle - INÍCIO', [
            'path' => $request->path(),
            'method' => $request->method(),
            'auth_check' => auth('sanctum')->check(),
        ]);
        $this->context->bootstrap($request);
        $response = $next($request);
        \Log::debug('EnsureEmpresaAtivaContext::handle - FIM', [
            'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
        ]);
        return $response;
    }
}

