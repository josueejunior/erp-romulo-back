<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: UseCase para atualizar tenant no admin
 */
class AtualizarTenantAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Atualiza tenant com dados fornecidos
     * 
     * @param int $tenantId
     * @param array $dados Array com dados a atualizar (snake_case ou camelCase)
     * @return Tenant
     * @throws DomainException
     */
    public function executar(int $tenantId, array $dados): Tenant
    {
        $tenant = $this->tenantRepository->buscarPorId($tenantId);

        if (!$tenant) {
            throw new DomainException('Empresa não encontrada.');
        }

        // 🔥 DDD: Usar método imutável da entidade
        $tenantAtualizado = $tenant->withUpdates($dados);

        return $this->tenantRepository->atualizar($tenantAtualizado);
    }
}


