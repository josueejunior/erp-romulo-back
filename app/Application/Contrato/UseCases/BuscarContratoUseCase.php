<?php

namespace App\Application\Contrato\UseCases;

use App\Domain\Contrato\Entities\Contrato;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use DomainException;

/**
 * Use Case: Buscar Contrato por ID
 */
class BuscarContratoUseCase
{
    public function __construct(
        private ContratoRepositoryInterface $contratoRepository,
    ) {}

    public function executar(int $id): Contrato
    {
        $contrato = $this->contratoRepository->buscarPorId($id);
        
        if (!$contrato) {
            throw new DomainException('Contrato não encontrado.');
        }
        
        return $contrato;
    }
}


