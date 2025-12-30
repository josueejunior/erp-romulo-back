<?php

namespace App\Contracts;

/**
 * Interface para representar a identidade do usuário autenticado
 * Permite acesso padronizado aos dados do usuário, tenant e empresa
 */
interface IAuthIdentity
{
    /**
     * Obter ID do usuário
     */
    public function getUserId(): ?int;

    /**
     * Obter ID do tenant
     */
    public function getTenantId(): ?string;

    /**
     * Obter ID da empresa ativa
     */
    public function getEmpresaId(): ?int;

    /**
     * Obter objeto do usuário completo
     */
    public function getUser(): ?\Illuminate\Contracts\Auth\Authenticatable;

    /**
     * Obter objeto do tenant
     */
    public function getTenant(): ?\App\Models\Tenant;

    /**
     * Obter objeto da empresa ativa
     */
    public function getEmpresa(): ?\App\Models\Empresa;

    /**
     * Verificar se é admin central
     */
    public function isAdminCentral(): bool;

    /**
     * Verificar se é usuário do tenant
     */
    public function isTenantUser(): bool;

    /**
     * Obter escopo de autenticação (api-v1, admin, etc)
     */
    public function getScope(): string;
}





