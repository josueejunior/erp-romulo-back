<?php

namespace App\Application\Plano\UseCases;

use App\Domain\Plano\Entities\Plano;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

/**
 * Use Case: Buscar Plano por ID
 * Orquestra a busca de um plano específico
 */
class BuscarPlanoUseCase
{
    public function __construct(
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $id ID do plano
     * @return Plano
     * @throws NotFoundException Se o plano não for encontrado
     */
    public function executar(int $id): Plano
    {
        $plano = $this->planoRepository->buscarPorId($id);

        if (!$plano) {
            throw new NotFoundException("Plano não encontrado.");
        }

        return $plano;
    }
}


