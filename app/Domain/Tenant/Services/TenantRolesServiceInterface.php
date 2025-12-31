<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Entities\Tenant;

/**
 * Interface para serviços de roles e permissões do tenant
 */
interface TenantRolesServiceInterface
{
    /**
     * Inicializar roles e permissões no tenant
     */
    public function inicializarRoles(Tenant $tenant): void;
}


