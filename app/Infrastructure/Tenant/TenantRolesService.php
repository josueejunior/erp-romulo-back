<?php

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Services\TenantRolesServiceInterface;
use Database\Seeders\RolesPermissionsSeeder;

/**
 * Implementação do serviço de roles do tenant
 */
class TenantRolesService implements TenantRolesServiceInterface
{
    public function inicializarRoles(Tenant $tenant): void
    {
        // Limpar cache de permissões
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar roles e permissões
        $seeder = new RolesPermissionsSeeder();
        $seeder->run();

        // Limpar cache novamente
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

