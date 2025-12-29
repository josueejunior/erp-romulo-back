<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Application\CustoIndireto\DTOs\CriarCustoIndiretoDTO;
use App\Domain\CustoIndireto\Entities\CustoIndireto;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Custo Indireto
 */
class CriarCustoIndiretoUseCase
{
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoRepository,
    ) {}

    public function executar(CriarCustoIndiretoDTO $dto): CustoIndireto
    {
        $custo = new CustoIndireto(
            id: null,
            empresaId: $dto->empresaId,
            descricao: $dto->descricao,
            data: $dto->data,
            valor: $dto->valor,
            categoria: $dto->categoria,
            observacoes: $dto->observacoes,
        );

        return $this->custoRepository->criar($custo);
    }
}

