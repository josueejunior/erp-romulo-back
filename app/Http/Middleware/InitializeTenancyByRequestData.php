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
        // Verificar se já há tenancy inicializado
        if (tenancy()->initialized) {
            return $next($request);
        }

        // Verificar se há tenant_id no header ou no request
        $tenantId = $request->header('X-Tenant-ID') 
            ?? $request->input('tenant_id')
            ?? $this->getTenantIdFromToken($request)
            ?? $this->getTenantIdFromUser($request);

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
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            // Verificar se há tenant_id nos abilities do token
            $abilities = $user->currentAccessToken()->abilities;
            if (isset($abilities['tenant_id'])) {
                return $abilities['tenant_id'];
            }
        }
        return null;
    }

    /**
     * Tentar obter tenant_id do usuário autenticado através de sessão ou cookies
     */
    protected function getTenantIdFromUser(Request $request): ?string
    {
        // Se o usuário está autenticado, buscar o tenant pela sessão
        // Isso é um fallback caso o header não esteja presente
        if ($request->user()) {
            // Tentar buscar o tenant_id da sessão ou cookie se disponível
            return $request->session()->get('tenant_id') 
                ?? $request->cookie('tenant_id');
        }
        
        return null;
    }
}




