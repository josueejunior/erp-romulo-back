<?php

namespace App\Application\Fornecedor\UseCases;

use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Exceptions\DomainException;

/**
 * Use Case: Deletar Fornecedor
 */
class DeletarFornecedorUseCase
{
    public function __construct(
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    public function executar(int $id): void
    {
        $fornecedor = $this->fornecedorRepository->buscarPorId($id);
        
        if (!$fornecedor) {
            throw new DomainException('Fornecedor não encontrado.');
        }
        
        $this->fornecedorRepository->deletar($id);
    }
}



