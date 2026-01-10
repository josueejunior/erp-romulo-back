<?php

namespace App\Application\Orgao\UseCases;

use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Órgãos
 */
class ListarOrgaosUseCase
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(array $filtros = []): LengthAwarePaginator
    {
        return $this->orgaoRepository->buscarComFiltros($filtros);
    }
}




