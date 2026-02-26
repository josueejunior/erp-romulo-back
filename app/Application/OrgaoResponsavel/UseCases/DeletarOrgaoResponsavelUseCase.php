<?php

namespace App\Application\OrgaoResponsavel\UseCases;

use App\Domain\OrgaoResponsavel\Repositories\OrgaoResponsavelRepositoryInterface;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use DomainException;

/**
 * Use Case: Deletar Responsável de Órgão
 * (Isolamento por tenant/empresa já é garantido pelo contexto da requisição.)
 */
class DeletarOrgaoResponsavelUseCase
{
    public function __construct(
        private OrgaoResponsavelRepositoryInterface $responsavelRepository,
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(int $id, int $orgaoId): void
    {
        $id = (int) $id;
        $orgaoId = (int) $orgaoId;

        $orgao = $this->orgaoRepository->buscarPorId($orgaoId);
        if (!$orgao) {
            throw new DomainException('Órgão não encontrado.');
        }

        $responsavel = $this->responsavelRepository->buscarPorId($id);
        if (!$responsavel) {
            throw new DomainException('Responsável não encontrado.');
        }

        if ((int) $responsavel->orgaoId !== $orgaoId) {
            throw new DomainException('Responsável não pertence ao órgão informado.');
        }

        $this->responsavelRepository->deletar($id);
    }
}



