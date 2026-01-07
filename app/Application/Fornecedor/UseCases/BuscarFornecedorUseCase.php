<?php

namespace App\Application\Fornecedor\UseCases;

use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use DomainException;

/**
 * Use Case: Buscar Fornecedor por ID
 */
class BuscarFornecedorUseCase
{
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    public function executar(int $id): Fornecedor
    {
        $fornecedor = $this->fornecedorRepository->buscarPorId($id);
        
        if (!$fornecedor) {
            throw new DomainException('Fornecedor não encontrado.');
        }
        
        return $fornecedor;
    }
}



