<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Domain\Tenant\Entities\Tenant;
use App\Services\AdminTenancyRunner;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: UseCase para buscar usuário por email no admin
 */
class BuscarUsuarioPorEmailAdminUseCase
{
    public function __construct(
        private UserReadRepositoryInterface $userReadRepository,
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Busca usuário por email no contexto de um tenant
     * 
     * @param string $email
     * @param Tenant $tenant
     * @return array
     * @throws DomainException
     */
    public function executar(string $email, Tenant $tenant): array
    {
        $user = $this->adminTenancyRunner->runForTenant($tenant, function () use ($email) {
            return $this->userReadRepository->buscarPorEmail($email);
        });

        if (!$user) {
            throw new DomainException('Usuário não encontrado com este e-mail.');
        }

        return $user;
    }
}





