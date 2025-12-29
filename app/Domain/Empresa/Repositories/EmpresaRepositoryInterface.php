<?php

namespace App\Domain\Empresa\Repositories;

use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Empresa\Entities\Empresa;

/**
 * Interface do Repository de Empresa
 */
interface EmpresaRepositoryInterface
{
    /**
     * Criar empresa dentro de um tenant
     */
    public function criarNoTenant(int $tenantId, CriarTenantDTO $dto): Empresa;

    /**
     * Buscar empresa por ID
     */
    public function buscarPorId(int $id): ?Empresa;

    /**
     * Listar todas as empresas do tenant atual
     */
    public function listar(): array;
}

