<?php

namespace App\Application\Setor\UseCases;

use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Modules\Orgao\Models\Setor as SetorModel;
use DomainException;

/**
 * Use Case: Deletar Setor
 */
class DeletarSetorUseCase
{
    public function __construct(
        private SetorRepositoryInterface $setorRepository,
    ) {}

    public function executar(int $id): void
    {
        $context = TenantContext::get();

        $setor = $this->setorRepository->buscarPorId($id);
        if (!$setor) {
            throw new DomainException('Setor não encontrado.');
        }

        if ($setor->empresaId !== $context->empresaId) {
            throw new DomainException('Setor não pertence à empresa ativa.');
        }

        // Verificar se há processos vinculados
        $setorModel = SetorModel::find($id);
        if ($setorModel && $setorModel->processos()->count() > 0) {
            throw new DomainException('Não é possível excluir um setor que possui processos vinculados.');
        }

        $this->setorRepository->deletar($id);
    }
}

