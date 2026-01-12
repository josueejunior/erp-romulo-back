<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Application\CustoIndireto\DTOs\ListarCustoIndiretosDTO;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Custos Indiretos
 * 
 * ✅ DDD: Recebe DTO, não array genérico.
 */
class ListarCustoIndiretosUseCase
{
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(ListarCustoIndiretosDTO $dto): LengthAwarePaginator
    {
        return $this->custoIndiretoRepository->buscarComFiltros($dto->toRepositoryFilters());
    }
}






