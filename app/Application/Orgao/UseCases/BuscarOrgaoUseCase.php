<?php

namespace App\Application\Orgao\UseCases;

use App\Domain\Orgao\Entities\Orgao;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use DomainException;

/**
 * Use Case: Buscar Órgão por ID
 */
class BuscarOrgaoUseCase
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    public function executar(int $id): Orgao
    {
        $orgao = $this->orgaoRepository->buscarPorId($id);
        
        if (!$orgao) {
            throw new DomainException('Órgão não encontrado.');
        }
        
        return $orgao;
    }
}



