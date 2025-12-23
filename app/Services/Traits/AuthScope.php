<?php

namespace App\Services\Traits;

use App\Contracts\IAuthIdentity;
use Illuminate\Support\Facades\Auth;

/**
 * Trait para services Legacy que precisam acessar o contexto de autenticação
 * Similar ao AuthScope do exemplo fornecido
 * Fornece métodos compatíveis com código legado
 */
trait AuthScope
{
    /**
     * Obter identidade de autenticação
     */
    protected function getAuthIdentity(): ?IAuthIdentity
    {
        return app(IAuthIdentity::class);
    }

    /**
     * Obter ID do tenant (compatível com código legado)
     */
    protected function getClienteId(): ?string
    {
        return $this->getAuthIdentity()?->getTenantId();
    }

    /**
     * Obter ID da empresa (compatível com código legado)
     */
    protected function getEmpresaId(): ?int
    {
        return $this->getAuthIdentity()?->getEmpresaId();
    }

    /**
     * Obter ID do usuário
     */
    protected function getUserId(): ?int
    {
        return $this->getAuthIdentity()?->getUserId();
    }

    /**
     * Obter guard de autenticação (compatível com código legado)
     */
    protected function auth(string $guard = 'sanctum')
    {
        return Auth::guard($guard);
    }

    /**
     * Obter sessão atual (compatível com código legado)
     */
    protected function session()
    {
        return session();
    }

    /**
     * Obter usuário autenticado
     */
    protected function getCurrentUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->getAuthIdentity()?->getUser();
    }
}

