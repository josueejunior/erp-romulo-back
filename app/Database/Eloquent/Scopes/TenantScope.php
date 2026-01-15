<?php

namespace App\Database\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope para isolamento automático de tenant
 * 
 * 🔥 SEGURANÇA: Aplica filtro automático baseado no contexto do tenant
 * Reduz código repetitivo e previne vazamento de dados entre tenants
 * 
 * Uso:
 * ```php
 * protected static function booted()
 * {
 *     static::addGlobalScope(new TenantScope);
 * }
 * ```
 */
class TenantScope implements Scope
{
    /**
     * Aplica o scope ao builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Se o modelo não tem tenant_id, não aplicar scope
        if (!$this->hasTenantId($model)) {
            return;
        }

        // Obter tenant_id do contexto
        $tenantId = $this->getTenantIdFromContext();

        if ($tenantId) {
            $builder->where('tenant_id', $tenantId);
        }
    }

    /**
     * Verifica se o modelo tem coluna tenant_id
     */
    protected function hasTenantId(Model $model): bool
    {
        $fillable = $model->getFillable();
        $guarded = $model->getGuarded();
        
        // Verificar se tenant_id está em fillable ou não está em guarded
        return in_array('tenant_id', $fillable) || 
               (!in_array('tenant_id', $guarded) && empty($guarded));
    }

    /**
     * Obtém tenant_id do contexto atual
     * Prioridade:
     * 1. tenancy()->tenant->id (stancl/tenancy)
     * 2. Container do Laravel (injetado pelo middleware)
     * 3. Session (fallback)
     */
    protected function getTenantIdFromContext(): ?int
    {
        // 1. Stancl Tenancy (prioridade máxima)
        if (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant) {
            return tenancy()->tenant->id;
        }

        // 2. Container do Laravel (injetado pelo middleware)
        if (app()->bound('tenant_id')) {
            return app('tenant_id');
        }

        // 3. Session (fallback - não recomendado para produção)
        if (session()->has('tenant_id')) {
            return session()->get('tenant_id');
        }

        return null;
    }

    /**
     * Extender query builder para remover scope quando necessário
     * Exemplo: User::withoutGlobalScope(TenantScope::class)->get()
     */
    public function extend(Builder $builder): void
    {
        // Permite remover o scope quando necessário (ex: admin global)
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}

