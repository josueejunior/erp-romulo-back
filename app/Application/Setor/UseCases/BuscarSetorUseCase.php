<?php

namespace App\Application\Setor\UseCases;

use App\Domain\Setor\Entities\Setor;
use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Buscar Setor por ID
 */
class BuscarSetorUseCase
{
    public function __construct(
        private SetorRepositoryInterface $setorRepository,
    ) {}

    public function executar(int $id): Setor
    {
        $context = TenantContext::get();

        $setor = $this->setorRepository->buscarPorId($id);
        if (!$setor) {
            throw new DomainException('Setor não encontrado.');
        }

        if ($setor->empresaId !== $context->empresaId) {
            throw new DomainException('Setor não pertence à empresa ativa.');
        }

        return $setor;
    }
}

