<?php

namespace App\Application\OrgaoResponsavel\UseCases;

use App\Domain\OrgaoResponsavel\Repositories\OrgaoResponsavelRepositoryInterface;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Deletar Responsável de Órgão
 */
class DeletarOrgaoResponsavelUseCase
{
    public function __construct(
        private OrgaoResponsavelRepositoryInterface $responsavelRepository,
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(int $id, int $orgaoId): void
    {
        $context = TenantContext::get();

        // Validar que o órgão existe e pertence à empresa
        $orgao = $this->orgaoRepository->buscarPorId($orgaoId);
        if (!$orgao) {
            throw new DomainException('Órgão não encontrado.');
        }

        if ($orgao->empresaId !== $context->empresaId) {
            throw new DomainException('Órgão não pertence à empresa ativa.');
        }

        // Buscar responsável
        $responsavel = $this->responsavelRepository->buscarPorId($id);
        if (!$responsavel) {
            throw new DomainException('Responsável não encontrado.');
        }

        // Validar que o responsável pertence ao órgão e à empresa
        if ($responsavel->orgaoId !== $orgaoId) {
            throw new DomainException('Responsável não pertence ao órgão informado.');
        }

        if ($responsavel->empresaId !== $context->empresaId) {
            throw new DomainException('Responsável não pertence à empresa ativa.');
        }

        $this->responsavelRepository->deletar($id);
    }
}

