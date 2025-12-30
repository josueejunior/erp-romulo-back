<?php

namespace App\Http\Controllers\Traits;

use App\Contracts\IAuthIdentity;
use App\Models\Empresa;
use App\Models\Tenant;

/**
 * Trait para controllers acessarem contexto de autenticação
 * 
 * Fornece métodos para acessar dados do usuário, tenant e empresa
 * através do IAuthIdentity configurado pelo middleware SetAuthContext
 */
trait HasAuthContext
{
    /**
     * Obtém a identidade de autenticação do container
     */
    protected function getAuthIdentity(): ?IAuthIdentity
    {
        try {
            return app(IAuthIdentity::class);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtém ID do usuário autenticado
     */
    protected function getUserId(): ?int
    {
        $identity = $this->getAuthIdentity();
        return $identity?->getUserId() ?? auth()->id();
    }

    /**
     * Obtém ID do tenant
     */
    protected function getTenantId(): ?string
    {
        $identity = $this->getAuthIdentity();
        return $identity?->getTenantId() ?? tenancy()->tenant?->id;
    }

    /**
     * Obtém ID da empresa ativa
     */
    protected function getEmpresaId(): ?int
    {
        $identity = $this->getAuthIdentity();
        if ($identity && $identity->getEmpresaId()) {
            return $identity->getEmpresaId();
        }
        
        // Fallback: obter do usuário autenticado
        $user = auth()->user();
        return $user?->empresa_ativa_id ?? null;
    }

    /**
     * Obtém objeto do usuário autenticado
     */
    protected function getUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        $identity = $this->getAuthIdentity();
        return $identity?->getUser() ?? auth()->user();
    }

    /**
     * Obtém objeto do tenant
     */
    protected function getTenant(): ?Tenant
    {
        $identity = $this->getAuthIdentity();
        return $identity?->getTenant() ?? tenancy()->tenant;
    }

    /**
     * Obtém objeto da empresa ativa
     */
    protected function getEmpresa(): ?Empresa
    {
        $identity = $this->getAuthIdentity();
        if ($identity && $identity->getEmpresa()) {
            return $identity->getEmpresa();
        }
        
        // Fallback: buscar do usuário
        $user = auth()->user();
        if ($user && $user->empresa_ativa_id) {
            return Empresa::find($user->empresa_ativa_id);
        }
        
        return $user?->empresas()->first();
    }

    /**
     * Obtém empresa ativa ou lança exceção
     */
    protected function getEmpresaOrFail(): Empresa
    {
        $empresa = $this->getEmpresa();
        
        if (!$empresa) {
            abort(403, 'Você não tem acesso a nenhuma empresa.');
        }
        
        return $empresa;
    }

    /**
     * Obtém usuário autenticado ou lança exceção
     */
    protected function getUserOrFail(): \Illuminate\Contracts\Auth\Authenticatable
    {
        $user = $this->getUser();
        
        if (!$user) {
            abort(401, 'Usuário não autenticado.');
        }
        
        return $user;
    }

    /**
     * Obtém tenant ou lança exceção
     */
    protected function getTenantOrFail(): Tenant
    {
        $tenant = $this->getTenant();
        
        if (!$tenant) {
            abort(403, 'Tenant não identificado.');
        }
        
        return $tenant;
    }

    /**
     * Verifica se é admin central
     */
    protected function isAdminCentral(): bool
    {
        $identity = $this->getAuthIdentity();
        return $identity?->isAdminCentral() ?? false;
    }

    /**
     * Verifica se é usuário de tenant
     */
    protected function isTenantUser(): bool
    {
        $identity = $this->getAuthIdentity();
        return $identity?->isTenantUser() ?? true;
    }

    /**
     * Obtém escopo de autenticação
     */
    protected function getScope(): string
    {
        $identity = $this->getAuthIdentity();
        return $identity?->getScope() ?? 'api-v1';
    }
}


