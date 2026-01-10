<?php

namespace App\Application\Orgao\UseCases;

use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use DomainException;

/**
 * Use Case: Deletar Órgão
 */
class DeletarOrgaoUseCase
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(int $id, int $empresaId): void
    {
        $orgao = $this->orgaoRepository->buscarPorId($id);
        
        if (!$orgao) {
            throw new DomainException('Órgão não encontrado.');
        }

        // Validar se pertence à empresa
        if ($orgao->empresaId !== $empresaId) {
            throw new DomainException('Órgão não pertence à empresa ativa.');
        }

        $this->orgaoRepository->deletar($id);
    }
}




