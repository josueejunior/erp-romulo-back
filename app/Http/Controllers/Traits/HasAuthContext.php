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
     * 
     * 🔥 REFATORADO: Prioriza ApplicationContext, mantém compatibilidade
     */
    protected function getTenantId(): ?string
    {
        // Prioridade 1: ApplicationContext (nova arquitetura)
        if (app()->bound(\App\Contracts\ApplicationContextContract::class)) {
            try {
                $context = app(\App\Contracts\ApplicationContextContract::class);
                if ($context->isInitialized()) {
                    $tenantId = $context->getTenantIdOrNull();
                    if ($tenantId) {
                        return (string) $tenantId;
                    }
                }
            } catch (\Exception $e) {
                // Continuar para fallbacks
            }
        }
        
        // Prioridade 2: IAuthIdentity (compatibilidade legado)
        $identity = $this->getAuthIdentity();
        if ($identity && $identity->getTenantId()) {
            return $identity->getTenantId();
        }
        
        // Prioridade 3: tenancy() direto
        return tenancy()->tenant?->id;
    }

    /**
     * Obtém ID da empresa ativa
     * 
     * 🔥 REFATORADO: Prioriza ApplicationContext, mantém compatibilidade
     */
    protected function getEmpresaId(): ?int
    {
        // Prioridade 1: ApplicationContext (nova arquitetura)
        if (app()->bound(\App\Contracts\ApplicationContextContract::class)) {
            try {
                $context = app(\App\Contracts\ApplicationContextContract::class);
                if ($context->isInitialized()) {
                    return $context->getEmpresaIdOrNull();
                }
            } catch (\Exception $e) {
                // Continuar para fallbacks
            }
        }
        
        // Prioridade 2: IAuthIdentity (compatibilidade legado)
        $identity = $this->getAuthIdentity();
        if ($identity && $identity->getEmpresaId()) {
            return $identity->getEmpresaId();
        }
        
        // Prioridade 3: Fallback direto
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
     * 
     * 🔥 REFATORADO: Prioriza ApplicationContext, mantém compatibilidade
     */
    protected function getTenant(): ?Tenant
    {
        // Prioridade 1: ApplicationContext (nova arquitetura)
        if (app()->bound(\App\Contracts\ApplicationContextContract::class)) {
            try {
                $context = app(\App\Contracts\ApplicationContextContract::class);
                if ($context->isInitialized()) {
                    try {
                        return $context->tenant();
                    } catch (\RuntimeException $e) {
                        // Contexto inicializado mas sem tenant (admin, etc)
                    }
                }
            } catch (\Exception $e) {
                // Continuar para fallbacks
            }
        }
        
        // Prioridade 2: IAuthIdentity (compatibilidade legado)
        $identity = $this->getAuthIdentity();
        if ($identity && $identity->getTenant()) {
            return $identity->getTenant();
        }
        
        // Prioridade 3: tenancy() direto
        return tenancy()->tenant;
    }

    /**
     * Obtém objeto da empresa ativa
     * 
     * 🔥 REFATORADO: Prioriza ApplicationContext, mantém compatibilidade com IAuthIdentity
     */
    protected function getEmpresa(): ?Empresa
    {
        // Prioridade 1: ApplicationContext (nova arquitetura)
        if (app()->bound(\App\Contracts\ApplicationContextContract::class)) {
            try {
                $context = app(\App\Contracts\ApplicationContextContract::class);
                if ($context->isInitialized()) {
                    try {
                        return $context->empresa();
                    } catch (\RuntimeException $e) {
                        // Contexto inicializado mas sem empresa (admin, etc)
                    }
                }
            } catch (\Exception $e) {
                // Continuar para fallbacks
            }
        }
        
        // Prioridade 2: IAuthIdentity (compatibilidade legado)
        $identity = $this->getAuthIdentity();
        if ($identity && $identity->getEmpresa()) {
            return $identity->getEmpresa();
        }
        
        // Prioridade 3: Fallback direto (último recurso)
        $user = auth()->user();
        if ($user && $user->empresa_ativa_id) {
            return Empresa::find($user->empresa_ativa_id);
        }
        
        return $user?->empresas()->first();
    }

    /**
     * Obtém empresa ativa ou lança exceção
     * 
     * 🔥 REFATORADO: Usa ApplicationContext quando disponível
     */
    protected function getEmpresaOrFail(): Empresa
    {
        // Prioridade 1: ApplicationContext (nova arquitetura)
        if (app()->bound(\App\Contracts\ApplicationContextContract::class)) {
            try {
                $context = app(\App\Contracts\ApplicationContextContract::class);
                if ($context->isInitialized()) {
                    try {
                        return $context->empresa();
                    } catch (\RuntimeException $e) {
                        // Contexto inicializado mas sem empresa
                        abort(403, 'Você não tem acesso a nenhuma empresa.');
                    }
                }
            } catch (\Exception $e) {
                // Continuar para fallbacks
            }
        }
        
        // Fallback: método antigo
        $empresa = $this->getEmpresa();
        
        if (!$empresa) {
            abort(403, 'Você não tem acesso a nenhuma empresa.');
        }
        
        return $empresa;
    }

    /**
     * Alias para getEmpresaOrFail() (usado em vários controllers)
     * 
     * 🔥 REFATORADO: Usa ApplicationContext quando disponível
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        return $this->getEmpresaOrFail();
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



