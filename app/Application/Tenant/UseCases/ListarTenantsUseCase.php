<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Tenants
 */
class ListarTenantsUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param array $filtros Filtros opcionais (status, search, per_page, etc)
     * @return LengthAwarePaginator
     */
    public function executar(array $filtros = []): LengthAwarePaginator
    {
        return $this->tenantRepository->buscarComFiltros($filtros);
    }
}


