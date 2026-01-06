<?php

namespace App\Application\Plano\UseCases;

use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Use Case: Listar Planos
 * Orquestra a listagem de planos com filtros
 */
class ListarPlanosUseCase
{
    public function __construct(
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna collection com entidades de domínio
     * 
     * @param array $filtros Filtros opcionais
     * @return Collection<Plano>
     */
    public function executar(array $filtros = []): Collection
    {
        return $this->planoRepository->listar($filtros);
    }
}


