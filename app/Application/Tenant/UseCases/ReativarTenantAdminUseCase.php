<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: UseCase para reativar tenant
 */
class ReativarTenantAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Reativa tenant
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
        $tenantAtivo = $tenant->reativar();

        $this->tenantRepository->atualizar($tenantAtivo);
    }
}



