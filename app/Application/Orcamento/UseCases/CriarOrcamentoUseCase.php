<?php

namespace App\Application\Orcamento\UseCases;

use App\Application\Orcamento\DTOs\CriarOrcamentoDTO;
use App\Application\Shared\Traits\HasApplicationContext;
use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\OrcamentoItem\Entities\OrcamentoItem;
use App\Domain\OrcamentoItem\Repositories\OrcamentoItemRepositoryInterface;
use DomainException;

/**
 * Use Case: Criar Orçamento
 * 
 * Usa o trait HasApplicationContext para resolver empresa_id de forma robusta.
 */
class CriarOrcamentoUseCase
{
    use HasApplicationContext;
    
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
        private OrcamentoItemRepositoryInterface $orcamentoItemRepository,
    ) {}

    public function executar(CriarOrcamentoDTO $dto): Orcamento
    {
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        $orcamento = new Orcamento(
            id: null,
            empresaId: $empresaId,
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


