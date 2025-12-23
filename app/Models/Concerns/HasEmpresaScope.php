<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Trait para adicionar scope global que filtra automaticamente por empresa_id
 * Garante isolamento de dados entre empresas
 */
trait HasEmpresaScope
{
    /**
     * Boot do trait - adiciona global scope
     */
    protected static function bootHasEmpresaScope()
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            // Apenas aplicar se o modelo tem empresa_id
            if (!in_array('empresa_id', $builder->getModel()->getFillable())) {
                return;
            }

            // Tentar obter empresa_id do contexto atual
            $empresaId = static::getEmpresaIdFromContext();

            if ($empresaId) {
                $builder->where('empresa_id', $empresaId)
                        ->whereNotNull('empresa_id');
            }
        });
    }

    /**
     * Obtém empresa_id do contexto atual
     * Usa IAuthIdentity para garantir consistência com BaseService
     */
    protected static function getEmpresaIdFromContext(): ?int
    {
        // Usar IAuthIdentity para garantir consistência com BaseService
        try {
            $authIdentity = app(\App\Contracts\IAuthIdentity::class);
            if ($authIdentity) {
                $empresaId = $authIdentity->getEmpresaId();
                if ($empresaId) {
                    return $empresaId;
                }
            }
        } catch (\Exception $e) {
            // Se IAuthIdentity não estiver disponível, tentar método alternativo
        }

        // Fallback: Tentar obter do usuário autenticado diretamente
        if (Auth::check()) {
            $user = Auth::user();
            
            // Se usuário tem empresa_ativa_id
            if ($user->empresa_ativa_id ?? null) {
                return $user->empresa_ativa_id;
            }
            
            // Tentar obter do relacionamento
            try {
                $empresa = $user->empresas()->first();
                if ($empresa) {
                    return $empresa->id;
                }
            } catch (\Exception $e) {
                // Ignorar erros se relacionamento não estiver disponível
            }
        }

        // Tentar obter do request (header)
        if (request() && request()->header('X-Empresa-ID')) {
            return (int) request()->header('X-Empresa-ID');
        }

        return null;
    }

    /**
     * Remove o scope global (útil para queries administrativas)
     */
    public function scopeWithoutEmpresaScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('empresa');
    }
}

