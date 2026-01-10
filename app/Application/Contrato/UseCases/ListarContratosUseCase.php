<?php

namespace App\Application\Contrato\UseCases;

use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Contratos
 */
class ListarContratosUseCase
{
    public function __construct(
        private ContratoRepositoryInterface $contratoRepository,
    ) {}

    public function executar(array $filtros = []): LengthAwarePaginator
    {
        return $this->contratoRepository->buscarComFiltros($filtros);
    }
}




