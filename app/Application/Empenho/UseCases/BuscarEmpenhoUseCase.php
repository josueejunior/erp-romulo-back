<?php

namespace App\Application\Empenho\UseCases;

use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use DomainException;

/**
 * Use Case: Buscar Empenho por ID
 */
class BuscarEmpenhoUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function executar(int $id): Empenho
    {
        $empenho = $this->empenhoRepository->buscarPorId($id);
        
        if (!$empenho) {
            throw new DomainException('Empenho não encontrado.');
        }
        
        return $empenho;
    }
}



