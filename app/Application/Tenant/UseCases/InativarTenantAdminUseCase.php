<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: UseCase para inativar tenant
 */
class InativarTenantAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Inativa tenant
     * 
     * @param int $tenantId
     * @return void
     * @throws DomainException
     */
    public function executar(int $tenantId): void
    {
        $tenant = $this->tenantRepository->buscarPorId($tenantId);

        if (!$tenant) {
            throw new DomainException('Empresa não encontrada.');
        }

        // 🔥 DDD: Usar método da entidade (regra de negócio)
        $tenantInativo = $tenant->inativar();

        $this->tenantRepository->atualizar($tenantInativo);
    }
}




