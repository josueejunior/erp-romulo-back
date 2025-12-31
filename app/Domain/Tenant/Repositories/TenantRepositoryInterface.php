<?php

namespace App\Domain\Tenant\Repositories;

use App\Domain\Tenant\Entities\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface do Repository de Tenant
 * O domínio não sabe se é MySQL, MongoDB, API, etc.
 */
interface TenantRepositoryInterface
{
    /**
     * Criar um novo tenant
     */
    public function criar(Tenant $tenant): Tenant;

    /**
     * Buscar tenant por ID
     */
    public function buscarPorId(int $id): ?Tenant;

    /**
     * Buscar tenant por CNPJ
     */
    public function buscarPorCnpj(string $cnpj): ?Tenant;

    /**
     * Buscar tenants com filtros
     */
    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator;

    /**
     * Atualizar tenant
     */
    public function atualizar(Tenant $tenant): Tenant;

    /**
     * Deletar tenant
     */
    public function deletar(int $id): void;

    /**
     * Buscar modelo Eloquent por ID (para casos especiais onde precisa do modelo, não da entidade)
     * Use apenas quando realmente necessário (ex: relacionamentos)
     * 
     * @return \App\Models\Tenant|null
     */
    public function buscarModeloPorId(int $id): ?\App\Models\Tenant;
}


