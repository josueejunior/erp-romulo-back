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
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(CriarCustoIndiretoDTO $dto): CustoIndireto
    {
        // Criar entidade CustoIndireto (regras de negócio)
        $custoIndireto = new CustoIndireto(
            id: null, // Será gerado pelo repository
            empresaId: $dto->empresaId,
            descricao: $dto->descricao,
            data: $dto->data,
            valor: $dto->valor,
            categoria: $dto->categoria,
            observacoes: $dto->observacoes,
        );

        // Persistir custo indireto (infraestrutura)
        return $this->custoIndiretoRepository->criar($custoIndireto);
    }
}
