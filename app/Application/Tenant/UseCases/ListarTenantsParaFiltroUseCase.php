<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Use Case: Listar Tenants para Filtro (Admin)
 */
class ListarTenantsParaFiltroUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Executa o caso de uso
     * Retorna apenas dados básicos dos tenants para filtros
     */
    public function executar(): Collection
    {
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'status' => 'ativa',
            'per_page' => 1000,
        ]);

        return collect($tenantsPaginator->items())->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'razao_social' => $tenant->razaoSocial,
                'cnpj' => $tenant->cnpj,
            ];
        });
    }
}

