<?php

namespace App\Http\Controllers\Traits;

use App\Contracts\IAuthIdentity;
use App\Models\User;
use App\Models\AdminUser;
use App\Models\Tenant;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;

/**
 * Trait para controllers e services que precisam acessar o contexto de autenticação
 * Similar ao HasAuthContext do exemplo fornecido
 */
trait HasAuthContext
{
    /**
     * Obter identidade de autenticação
     */
    protected function getAuthIdentity(): ?IAuthIdentity
    {
        return app(IAuthIdentity::class);
    }

    /**
     * Obter ID do usuário
     */
    protected function getUserId(): ?int
    {
        return $this->getAuthIdentity()?->getUserId();
    }

    /**
     * Obter ID do tenant
     */
    protected function getTenantId(): ?string
    {
        return $this->getAuthIdentity()?->getTenantId();
    }

    /**
     * Obter ID da empresa ativa
     */
    protected function getEmpresaId(): ?int
    {
        return $this->getAuthIdentity()?->getEmpresaId();
    }

    /**
     * Obter objeto do usuário
     */
    protected function getUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->getAuthIdentity()?->getUser();
    }

    /**
     * Obter objeto do tenant
     */
    protected function getTenant(): ?Tenant
    {
        return $this->getAuthIdentity()?->getTenant();
    }

    /**
     * Obter objeto da empresa ativa
     */
    protected function getEmpresa(): ?Empresa
    {
        return $this->getAuthIdentity()?->getEmpresa();
    }

    /**
     * Verificar se é admin central
     */
    protected function isAdminCentral(): bool
    {
        return $this->getAuthIdentity()?->isAdminCentral() ?? false;
    }

    /**
     * Verificar se é usuário do tenant
     */
    protected function isTenantUser(): bool
    {
        return $this->getAuthIdentity()?->isTenantUser() ?? false;
    }

    /**
     * Obter escopo de autenticação
     */
    protected function getScope(): string
    {
        return $this->getAuthIdentity()?->getScope() ?? 'api-v1';
    }

    /**
     * Obter usuário ou lançar exceção
     */
    protected function getUserOrFail(): \Illuminate\Contracts\Auth\Authenticatable
    {
        $user = $this->getUser();
        
        if (!$user) {
            abort(401, 'Usuário não autenticado');
        }

        return $user;
    }

    /**
     * Obter empresa ou lançar exceção
     */
    protected function getEmpresaOrFail(): Empresa
    {
        $empresa = $this->getEmpresa();
        
        if (!$empresa) {
            abort(403, 'Usuário não possui empresa associada');
        }

        return $empresa;
    }

    /**
     * Obter tenant ou lançar exceção
     */
    protected function getTenantOrFail(): Tenant
    {
        $tenant = $this->getTenant();
        
        if (!$tenant) {
            abort(403, 'Tenant não encontrado');
        }

        return $tenant;
    }
}

