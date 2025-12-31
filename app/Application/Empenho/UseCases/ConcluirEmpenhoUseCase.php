<?php

namespace App\Application\Empenho\UseCases;

use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use DomainException;

/**
 * Use Case: Concluir Empenho
 */
class ConcluirEmpenhoUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    public function executar(int $empenhoId): Empenho
    {
        $empenho = $this->empenhoRepository->buscarPorId($empenhoId);
        
        if (!$empenho) {
            throw new DomainException('Empenho não encontrado.');
        }

        $empenhoConcluido = $empenho->concluir();

        return $this->empenhoRepository->atualizar($empenhoConcluido);
    }
}


