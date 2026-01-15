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
        \Log::info('CriarOrcamentoUseCase::executar - Iniciando criação de orçamento', [
            'processo_id' => $dto->processoId,
            'processo_item_id' => $dto->processoItemId,
            'fornecedor_id' => $dto->fornecedorId,
            'transportadora_id' => $dto->transportadoraId,
            'empresa_id_dto' => $dto->empresaId,
            'custo_produto' => $dto->custoProduto,
            'frete' => $dto->frete,
        ]);
        
        // Resolver empresa_id usando o trait (fallbacks robustos)
        $empresaId = $this->resolveEmpresaId($dto->empresaId);
        
        \Log::info('CriarOrcamentoUseCase::executar - Empresa ID resolvido', [
            'empresa_id_resolvido' => $empresaId,
            'empresa_id_dto' => $dto->empresaId,
        ]);
        
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

        \Log::info('CriarOrcamentoUseCase::executar - Chamando repository->criar', [
            'empresa_id' => $empresaId,
            'processo_id' => $dto->processoId,
            'processo_item_id' => $dto->processoItemId,
        ]);
        
        $orcamento = $this->orcamentoRepository->criar($orcamento);
        
        \Log::info('CriarOrcamentoUseCase::executar - Orçamento criado no repository', [
            'orcamento_id' => $orcamento->id,
            'empresa_id' => $orcamento->empresaId,
            'processo_id' => $orcamento->processoId,
            'processo_item_id' => $orcamento->processoItemId,
        ]);

        // Se o orçamento foi criado com processo_item_id, criar também o OrcamentoItem
        // Isso é necessário para que o relacionamento hasManyThrough funcione corretamente
        if ($dto->processoItemId) {
            \Log::info('CriarOrcamentoUseCase::executar - Criando OrcamentoItem', [
                'orcamento_id' => $orcamento->id,
                'processo_item_id' => $dto->processoItemId,
            ]);
            
            $orcamentoItem = new OrcamentoItem(
                id: null,
                empresaId: $empresaId,
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

            $orcamentoItem = $this->orcamentoItemRepository->criar($orcamentoItem);
            
            \Log::info('CriarOrcamentoUseCase::executar - OrcamentoItem criado', [
                'orcamento_item_id' => $orcamentoItem->id,
                'orcamento_id' => $orcamentoItem->orcamentoId,
                'processo_item_id' => $orcamentoItem->processoItemId,
            ]);
        }

        \Log::info('CriarOrcamentoUseCase::executar - Concluído', [
            'orcamento_id' => $orcamento->id,
        ]);

        return $orcamento;
    }
}


