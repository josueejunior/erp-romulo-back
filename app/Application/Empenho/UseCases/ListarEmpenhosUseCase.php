<?php

namespace App\Application\Empenho\UseCases;

use App\Application\Empenho\DTOs\ListarEmpenhosDTO;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use Case: Listar Empenhos
 * 
 * ✅ Recebe DTO explícito com empresaId obrigatório
 * ✅ Toda lógica de negócio fica aqui (não no Controller)
 */
class ListarEmpenhosUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {}

    /**
     * Executar caso de uso
     * 
     * @param ListarEmpenhosDTO $dto DTO com filtros e empresaId obrigatório
     * @return LengthAwarePaginator Paginação com entidades de domínio
     */
    public function executar(ListarEmpenhosDTO $dto): LengthAwarePaginator
    {
        // Converter DTO para filtros do Repository
        $filtros = $dto->toRepositoryFilters();
        
        // Repository retorna entidades de domínio (não modelos Eloquent)
        return $this->empenhoRepository->buscarComFiltros($filtros);
    }
}




