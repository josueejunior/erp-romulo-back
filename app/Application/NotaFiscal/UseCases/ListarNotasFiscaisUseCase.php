<?php

namespace App\Application\NotaFiscal\UseCases;

use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Notas Fiscais
 */
class ListarNotasFiscaisUseCase
{
    public function __construct(
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {}

    public function executar(array $filtros = []): LengthAwarePaginator
    {
        return $this->notaFiscalRepository->buscarComFiltros($filtros);
    }
}



