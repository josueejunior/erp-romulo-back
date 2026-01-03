<?php

namespace App\Application\Empenho\UseCases;

use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Empenhos
 */
class ListarEmpenhosUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function executar(array $filtros = []): LengthAwarePaginator
    {
        return $this->empenhoRepository->buscarComFiltros($filtros);
    }
}



