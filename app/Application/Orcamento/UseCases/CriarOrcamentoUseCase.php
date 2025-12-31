<?php

namespace App\Application\Orcamento\UseCases;

use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\OrcamentoItem\Entities\OrcamentoItem;
use App\Domain\OrcamentoItem\Repositories\OrcamentoItemRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use DomainException;

/**
 * Use Case: Criar Orçamento
 */
class CriarOrcamentoUseCase
{
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
        private OrcamentoItemRepositoryInterface $orcamentoItemRepository,
    ) {}

    public function executar(CriarOrcamentoDTO $dto): Orcamento
    {
        $context = TenantContext::get();
        
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

        $orcamento = $this->orcamentoRepository->criar($orcamento);

        // Se o orçamento foi criado com processo_item_id, criar também o OrcamentoItem
        // Isso é necessário para que o relacionamento hasManyThrough funcione corretamente
        if ($dto->processoItemId) {
            $orcamentoItem = new OrcamentoItem(
                id: null,
                empresaId: $context->empresaId,
                orcamentoId: $orcamento->id,
                processoItemId: $dto->processoItemId,
                custoProduto: $dto->custoProduto,
                marcaModelo: $dto->marcaModelo,
                ajustesEspecificacao: $dto->ajustesEspecificacao,
                frete: $dto->frete,
                freteIncluido: $dto->freteIncluido,
                fornecedorEscolhido: $dto->fornecedorEscolhido,
                observacoes: $dto->observacoes,
            );

            $this->orcamentoItemRepository->criar($orcamentoItem);
        }

        return $orcamento;
    }
}


