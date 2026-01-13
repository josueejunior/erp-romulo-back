<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: UseCase para deletar usuário no admin
 */
class DeletarUsuarioAdminUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Deleta usuário (soft delete)
     * 
     * @param int $userId
     * @return void
     * @throws DomainException
     */
    public function executar(int $userId): void
    {
        $user = $this->userRepository->buscarPorId($userId);

        if (!$user) {
            throw new DomainException('Usuário não encontrado.');
        }

        $this->userRepository->deletar($userId);
    }
}





