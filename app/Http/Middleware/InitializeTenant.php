<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

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
        $tenantParam = $request->route('tenant');
        
        Log::info('InitializeTenant: Iniciando', [
            'route' => $request->route()->getName(),
            'tenant_param' => $tenantParam,
            'tenant_param_type' => gettype($tenantParam),
            'path' => $request->path(),
        ]);

        if ($tenantParam) {
            $tenantModel = null;
            
            // Se for modelo Eloquent, usar diretamente
            if (is_object($tenantParam) && method_exists($tenantParam, 'getKey')) {
                $tenantModel = $tenantParam;
                Log::info('InitializeTenant: Tenant recebido como modelo', [
                    'tenant_id' => $tenantModel->id,
                ]);
            } elseif (is_numeric($tenantParam)) {
                // Se for ID, buscar no banco central
                Log::info('InitializeTenant: Buscando tenant no banco central', [
                    'tenant_id' => $tenantParam,
                ]);
                $tenantModel = \App\Models\Tenant::find($tenantParam);
                
                if (!$tenantModel) {
                    Log::error('InitializeTenant: Tenant não encontrado', [
                        'tenant_id' => $tenantParam,
                    ]);
                    abort(404, 'Tenant não encontrado');
                }
                
                Log::info('InitializeTenant: Tenant encontrado', [
                    'tenant_id' => $tenantModel->id,
                    'tenant_database' => $tenantModel->database ?? 'N/A',
                    'tenant_razao_social' => $tenantModel->razao_social ?? 'N/A',
                ]);
            }
            
            // Verificar se já está inicializado com outro tenant
            if (tenancy()->initialized) {
                $currentTenant = tenancy()->tenant;
                if ($currentTenant && $currentTenant->id === $tenantModel->id) {
                    Log::info('InitializeTenant: Tenant já inicializado corretamente', [
                        'tenant_id' => $tenantModel->id,
                    ]);
                } else {
                    Log::warning('InitializeTenant: Tenant diferente já inicializado, reinicializando', [
                        'tenant_id_atual' => $currentTenant?->id,
                        'tenant_id_correto' => $tenantModel->id,
                    ]);
                    tenancy()->end();
                }
            }
            
            // Inicializar tenant
            if ($tenantModel) {
                try {
                    tenancy()->initialize($tenantModel);
                    Log::info('InitializeTenant: Tenant inicializado com sucesso', [
                        'tenant_id' => $tenantModel->id,
                        'tenant_database' => tenancy()->tenant?->database ?? 'N/A',
                        'tenancy_initialized' => tenancy()->initialized,
                        'database_connection' => \DB::connection()->getName(),
                        'database_name' => \DB::connection()->getDatabaseName(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('InitializeTenant: Erro ao inicializar tenant', [
                        'tenant_id' => $tenantModel->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }
        } else {
            Log::warning('InitializeTenant: Parâmetro tenant não encontrado na rota', [
                'route_params' => $request->route()->parameters(),
            ]);
        }

        try {
            $response = $next($request);
            
            Log::debug('InitializeTenant: Requisição processada', [
                'tenant_id' => tenancy()->tenant?->id,
                'tenancy_initialized' => tenancy()->initialized,
            ]);
            
            return $response;
        } finally {
            // Sempre finalizar contexto do tenant após a requisição
            if (tenancy()->initialized) {
                Log::debug('InitializeTenant: Finalizando contexto do tenant', [
                    'tenant_id' => tenancy()->tenant?->id,
                ]);
                tenancy()->end();
            }
        }
    }
}




