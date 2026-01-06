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
    ) {}
    
    /**
     * Handle an incoming request.
     * 
     * 🔥 THIN MIDDLEWARE: Apenas chama o ApplicationContext
     * Toda a lógica está centralizada no ApplicationContext.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->bootstrap($request);
        return $next($request);
    }
}







