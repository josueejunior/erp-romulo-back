<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use DomainException;

/**
 * Use Case: Deletar Usuário
 * Orquestra a exclusão (soft delete) de usuário
 */
class DeletarUsuarioUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $userId): void
    {
        // Verificar se usuário existe
        $user = $this->userRepository->buscarPorId($userId);
        if (!$user) {
            throw new DomainException('Usuário não encontrado.');
        }

        // Deletar (soft delete)
        $this->userRepository->deletar($userId);
    }
}




