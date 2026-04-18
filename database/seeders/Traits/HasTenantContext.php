<?php

namespace Database\Seeders\Traits;

use App\Models\Tenant;

/**
 * Trait para seeders que precisam trabalhar no contexto de tenants
 * Fornece métodos auxiliares para inicializar e finalizar contexto de tenant
 */
trait HasTenantContext
{
    /**
     * Executa um callback no contexto de um tenant
     */
    protected function withTenant(Tenant $tenant, callable $callback)
    {
        $wasInitialized = tenancy()->initialized;
        
        if (!$wasInitialized) {
            tenancy()->initialize($tenant);
        }

        try {
            return $callback();
        } finally {
            if (!$wasInitialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * Executa um callback para cada tenant
     */
    protected function forEachTenant(callable $callback)
    {
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            $this->withTenant($tenant, function() use ($tenant, $callback) {
                return $callback($tenant);
            });
        }
    }
}







