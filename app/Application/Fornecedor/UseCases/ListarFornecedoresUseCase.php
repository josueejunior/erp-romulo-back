<?php

namespace App\Application\Fornecedor\UseCases;

use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Fornecedores
 */
class ListarFornecedoresUseCase
{
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    public function executar(array $filtros = []): LengthAwarePaginator
    {
        return $this->fornecedorRepository->buscarComFiltros($filtros);
    }
}



