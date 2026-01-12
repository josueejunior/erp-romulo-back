<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: UseCase para reativar usuário no admin
 */
class ReativarUsuarioAdminUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Reativa usuário
     * 
     * @param int $userId
     * @return void
     * @throws DomainException
     */
    public function executar(int $userId): void
    {
        // Verificar se usuário existe (incluindo deletados)
        $user = $this->userRepository->buscarPorId($userId);

        // Se não encontrou, pode estar deletado - tentar reativar mesmo assim
        // O repository reativar() usa withTrashed, então vai encontrar
        try {
            $this->userRepository->reativar($userId);
        } catch (\Exception $e) {
            throw new DomainException('Usuário não encontrado ou não pode ser reativado.');
        }
    }
}


