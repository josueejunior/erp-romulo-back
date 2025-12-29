<?php

namespace App\Application\Orcamento\UseCases;

use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Orçamento
 */
class CriarOrcamentoUseCase
{
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
    ) {}

    public function executar(CriarOrcamentoDTO $dto): Orcamento
    {
        $orcamento = new Orcamento(
            id: null,
            empresaId: $dto->empresaId,
            processoId: $dto->processoId,
            processoItemId: $dto->processoItemId,
            fornecedorId: $dto->fornecedorId,
            transportadoraId: $dto->transportadoraId,
            custoProduto: $dto->custoProduto,
            marcaModelo: $dto->marcaModelo,
            ajustesEspecificacao: $dto->ajustesEspecificacao,
            frete: $dto->frete,
            freteIncluido: $dto->freteIncluido,
            fornecedorEscolhido: $dto->fornecedorEscolhido,
            observacoes: $dto->observacoes,
        );

        return $this->orcamentoRepository->criar($orcamento);
    }
}

