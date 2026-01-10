<?php

namespace App\Application\Setor\UseCases;

use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Setores
 */
class ListarSetoresUseCase
{
    public function __construct(
        private SetorRepositoryInterface $setorRepository,
    ) {}

    public function executar(array $filtros = []): LengthAwarePaginator
    {
        $context = TenantContext::get();

        $filtros['empresa_id'] = $context->empresaId;
        $filtros['per_page'] = $filtros['per_page'] ?? 15;

        return $this->setorRepository->buscarComFiltros($filtros);
    }
}



