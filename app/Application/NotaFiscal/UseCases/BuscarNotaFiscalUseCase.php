<?php

namespace App\Application\NotaFiscal\UseCases;

use App\Domain\NotaFiscal\Entities\NotaFiscal;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use DomainException;

/**
 * Use Case: Buscar Nota Fiscal por ID
 */
class BuscarNotaFiscalUseCase
{
    public function __construct(
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {}

    public function executar(int $id): NotaFiscal
    {
        $notaFiscal = $this->notaFiscalRepository->buscarPorId($id);
        
        if (!$notaFiscal) {
            throw new DomainException('Nota fiscal não encontrada.');
        }
        
        return $notaFiscal;
    }
}



