<?php

namespace App\Application\Processo\UseCases;

use App\Application\Processo\DTOs\ListarProcessosDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Processos
 * 
 * ✅ DDD: Recebe DTO, não array genérico.
 * ✅ DDD: Não conhece detalhes de HTTP (Request).
 */
class ListarProcessosUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(ListarProcessosDTO $dto): LengthAwarePaginator
    {
        return $this->processoRepository->buscarComFiltros($dto->toRepositoryFilters());
    }
}






