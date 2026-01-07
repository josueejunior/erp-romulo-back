<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Usuários
 * Orquestra a listagem de usuários com filtros
 */
class ListarUsuariosUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna paginator com entidades de domínio
     */
    public function executar(array $filtros = []): LengthAwarePaginator
    {
        return $this->userRepository->buscarComFiltros($filtros);
    }
}




