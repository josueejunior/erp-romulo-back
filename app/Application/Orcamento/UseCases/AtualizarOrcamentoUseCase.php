<?php

namespace App\Application\Orcamento\UseCases;

use App\Application\Orcamento\DTOs\AtualizarOrcamentoDTO;
use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\OrcamentoItem\Repositories\OrcamentoItemRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Application Service: AtualizarOrcamentoUseCase
 * 
 * Orquestra a atualização de orçamento seguindo as regras de negócio
 */
class AtualizarOrcamentoUseCase
{
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
        private OrcamentoItemRepositoryInterface $orcamentoItemRepository,
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
    ) {}

    public function executar(AtualizarOrcamentoDTO $dto, int $empresaId, int $processoId, int $itemId): Orcamento
    {
        // Buscar orçamento existente
        $orcamentoExistente = $this->orcamentoRepository->buscarPorId($dto->orcamentoId);
        
        if (!$orcamentoExistente) {
            throw new DomainException('Orçamento não encontrado.');
        }

        // Validar se pertence à empresa (regra de domínio)
        if ($orcamentoExistente->empresaId !== $empresaId) {
            throw new DomainException('Orçamento não pertence à empresa ativa.');
        }

        // Validar processo
        $processo = $this->processoRepository->buscarPorId($processoId);
        if (!$processo) {
            throw new DomainException('Processo não encontrado.');
        }
        
        if ($processo->empresaId !== $empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }

        // Validar item
        $item = $this->processoItemRepository->buscarPorId($itemId);
        if (!$item) {
            throw new DomainException('Item não encontrado.');
        }
        
        if ($item->processoId !== $processoId) {
            throw new DomainException('Item não pertence ao processo.');
        }

        // Validar que orçamento pertence ao item
        // 🔥 CORREÇÃO: Orçamentos podem ter processo_item_id diretamente (formato antigo)
        // ou ter múltiplos itens em orcamento_itens (formato novo)
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoExistente->id);
        $pertenceAoItem = false;
        
        // Verificar formato antigo (processo_item_id direto no orçamento)
        if ($orcamentoExistente->processoItemId === $itemId) {
            $pertenceAoItem = true;
        }
        // Verificar formato novo (processo_item_id em orcamento_itens)
        elseif ($orcamentoModel && $orcamentoModel->itens) {
            $temItem = $orcamentoModel->itens()
                ->where('processo_item_id', $itemId)
                ->exists();
            if ($temItem) {
                $pertenceAoItem = true;
            }
        }
        
        if (!$pertenceAoItem) {
            Log::warning('AtualizarOrcamentoUseCase - Orçamento não pertence ao item', [
                'orcamento_id' => $orcamentoExistente->id,
                'orcamento_processo_item_id' => $orcamentoExistente->processoItemId,
                'item_id_solicitado' => $itemId,
                'tem_itens' => $orcamentoModel && $orcamentoModel->itens ? $orcamentoModel->itens->count() : 0,
            ]);
            throw new DomainException('Orçamento não pertence ao item.');
        }

        // Aplicar atualizações (entidade imutável - criar nova instância)
        $orcamentoAtualizado = new Orcamento(
            id: $orcamentoExistente->id,
            empresaId: $orcamentoExistente->empresaId,
            processoId: $orcamentoExistente->processoId,
            processoItemId: $orcamentoExistente->processoItemId,
            fornecedorId: $dto->fornecedorId ?? $orcamentoExistente->fornecedorId,
            transportadoraId: $dto->transportadoraId ?? $orcamentoExistente->transportadoraId,
            custoProduto: $dto->custoProduto ?? $orcamentoExistente->custoProduto,
            marcaModelo: $dto->marcaModelo ?? $orcamentoExistente->marcaModelo,
            ajustesEspecificacao: $dto->ajustesEspecificacao ?? $orcamentoExistente->ajustesEspecificacao,
            frete: $dto->frete ?? $orcamentoExistente->frete,
            freteIncluido: $dto->freteIncluido ?? $orcamentoExistente->freteIncluido,
            fornecedorEscolhido: $dto->fornecedorEscolhido ?? $orcamentoExistente->fornecedorEscolhido,
            observacoes: $dto->observacoes ?? $orcamentoExistente->observacoes,
        );

        // Regra de negócio: Se marcar como escolhido, desmarcar outros do mesmo item
        if ($dto->fornecedorEscolhido === true) {
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
            if ($itemModel) {
                // Desmarcar outros orçamentos do mesmo item (formato antigo)
                // 🔥 CORREÇÃO: Buscar IDs primeiro e depois atualizar diretamente na tabela para evitar ambiguidade no HasManyThrough
                $outrosOrcamentosIds = $itemModel->orcamentos()
                    ->where('orcamentos.id', '!=', $orcamentoExistente->id)
                    ->pluck('orcamentos.id')
                    ->toArray();
                
                if (!empty($outrosOrcamentosIds)) {
                    \App\Modules\Orcamento\Models\Orcamento::whereIn('id', $outrosOrcamentosIds)
                        ->update(['fornecedor_escolhido' => false]);
                }
                
                // 🔥 CORREÇÃO: Também desmarcar outros orcamento_itens do mesmo processo_item (formato novo)
                $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoExistente->id);
                if ($orcamentoModel) {
                    // Buscar o orcamento_item correspondente a este processo_item
                    $orcamentoItem = $orcamentoModel->itens()
                        ->where('processo_item_id', $itemId)
                        ->first();
                    
                    if ($orcamentoItem) {
                        // Desmarcar outros orcamento_itens do mesmo processo_item
                        $this->orcamentoItemRepository->desmarcarEscolhido($orcamentoModel->id, $itemId);
                        
                        // Marcar este orcamento_item como escolhido
                        $orcamentoItem->fornecedor_escolhido = true;
                        $orcamentoItem->save();
                        
                        Log::info('AtualizarOrcamentoUseCase - OrcamentoItem marcado como escolhido', [
                            'orcamento_id' => $orcamentoExistente->id,
                            'orcamento_item_id' => $orcamentoItem->id,
                            'processo_item_id' => $itemId,
                        ]);
                    }
                }
            }
        } elseif ($dto->fornecedorEscolhido === false) {
            // Se desmarcou, também desmarcar o orcamento_item correspondente
            $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoExistente->id);
            if ($orcamentoModel) {
                $orcamentoItem = $orcamentoModel->itens()
                    ->where('processo_item_id', $itemId)
                    ->first();
                
                if ($orcamentoItem) {
                    $orcamentoItem->fornecedor_escolhido = false;
                    $orcamentoItem->save();
                    
                    Log::info('AtualizarOrcamentoUseCase - OrcamentoItem desmarcado', [
                        'orcamento_id' => $orcamentoExistente->id,
                        'orcamento_item_id' => $orcamentoItem->id,
                        'processo_item_id' => $itemId,
                    ]);
                }
            }
        }

        // Persistir atualização
        $orcamentoAtualizado = $this->orcamentoRepository->atualizar($orcamentoAtualizado);

        // Atualizar valor mínimo no item se fornecedor foi escolhido
        if ($dto->fornecedorEscolhido === true) {
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
            if ($itemModel) {
                $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoAtualizado->id, ['formacaoPreco']);
                if ($orcamentoModel && $orcamentoModel->formacaoPreco) {
                    $itemModel->valor_minimo_venda = $orcamentoModel->formacaoPreco->preco_minimo;
                    if (method_exists($itemModel, 'calcularValorMinimoVenda')) {
                        $itemModel->calcularValorMinimoVenda();
                    }
                    $itemModel->save();
                }
            }
        } elseif ($dto->fornecedorEscolhido === false) {
            // Se desmarcou, limpar valor mínimo
            $itemModel = $this->processoItemRepository->buscarModeloPorId($itemId);
            if ($itemModel) {
                $itemModel->valor_minimo_venda = null;
                $itemModel->save();
            }
        }

        return $orcamentoAtualizado;
    }
}









