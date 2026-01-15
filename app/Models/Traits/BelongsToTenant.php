<?php

namespace App\Models\Traits;

use App\Database\Eloquent\Scopes\TenantScope;

/**
 * Trait para adicionar isolamento automático de tenant
 * 
 * 🔥 SEGURANÇA: Aplica Global Scope automaticamente
 * Garante que queries sempre filtrem por tenant_id
 * 
 * Uso:
 * ```php
 * class MeuModel extends BaseModel
 * {
 *     use BelongsToTenant;
 *     
 *     protected $fillable = ['name', 'tenant_id', ...];
 * }
 * ```
 */
trait BelongsToTenant
{
    /**
     * Boot do trait - adiciona global scope
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // Preenche tenant_id automaticamente ao criar (se não fornecido)
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = static::getTenantIdFromContext();
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    /**
     * Obtém tenant_id do contexto (método auxiliar)
     */
    protected static function getTenantIdFromContext(): ?int
    {
        // Stancl Tenancy
        if (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant) {
            return tenancy()->tenant->id;
        }

        // Container
        if (app()->bound('tenant_id')) {
            return app('tenant_id');
        }

        // Session (fallback)
        if (session()->has('tenant_id')) {
            return session()->get('tenant_id');
        }

        return null;
    }

    /**
     * Query sem filtro de tenant (para admin global, etc)
     * 
     * Uso: Model::withoutTenantScope()->get()
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}

