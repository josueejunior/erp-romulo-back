<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Application\CustoIndireto\DTOs\CriarCustoIndiretoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\CustoIndireto\Entities\CustoIndireto;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Custo Indireto
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarCustoIndiretoUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoRepository,
    ) {}

    public function executar(CriarCustoIndiretoDTO $dto): CustoIndireto
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        $custo = new CustoIndireto(
            id: null,
            empresaId: $empresaId,
            descricao: $dto->descricao,
            data: $dto->data,
            valor: $dto->valor,
            categoria: $dto->categoria,
            observacoes: $dto->observacoes,
        );

        return $this->custoRepository->criar($custo);
    }
}



