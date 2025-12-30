<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Entities\User;
use DomainException;

/**
 * Use Case: Buscar Usuário
 * Orquestra a busca de um usuário específico
 */
class BuscarUsuarioUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna entidade de domínio
     */
    public function executar(int $userId): User
    {
        $user = $this->userRepository->buscarPorId($userId);
        
        if (!$user) {
            throw new DomainException('Usuário não encontrado.');
        }

        return $user;
    }
}

