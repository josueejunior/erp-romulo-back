<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByRequestData extends IdentificationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se há tenant_id no header ou no request
        $tenantId = $request->header('X-Tenant-ID') 
            ?? $request->input('tenant_id')
            ?? $request->bearerToken() ? $this->getTenantIdFromToken($request) : null;

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant ID não fornecido. Use o header X-Tenant-ID ou inclua tenant_id no request.'
            ], 400);
        }

        $tenant = \App\Models\Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado.'
            ], 404);
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }

    /**
     * Extrair tenant_id do token (se armazenado no token)
     */
    protected function getTenantIdFromToken(Request $request): ?string
    {
        // Tentar extrair do token Sanctum
        $user = $request->user();
        if ($user && $request->user()->currentAccessToken()) {
            // Verificar se há tenant_id nos abilities do token
            $abilities = $request->user()->currentAccessToken()->abilities;
            if (isset($abilities['tenant_id'])) {
                return $abilities['tenant_id'];
            }
        }
        return null;
    }
}

