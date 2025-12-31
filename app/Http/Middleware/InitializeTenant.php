<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para inicializar contexto do tenant
 * Remove responsabilidade do Controller
 */
class InitializeTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if ($tenant) {
            // Se for modelo Eloquent, inicializar
            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                tenancy()->initialize($tenant);
            } elseif (is_numeric($tenant)) {
                // Se for ID, buscar e inicializar
                $tenantModel = \App\Models\Tenant::find($tenant);
                if ($tenantModel) {
                    tenancy()->initialize($tenantModel);
                }
            }
        }

        try {
            return $next($request);
        } finally {
            // Sempre finalizar contexto do tenant após a requisição
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}


