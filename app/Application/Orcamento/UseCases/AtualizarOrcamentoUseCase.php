<?php

namespace App\Application\Orcamento\UseCases;

use App\Application\Orcamento\DTOs\AtualizarOrcamentoDTO;
use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use DomainException;

/**
 * Application Service: AtualizarOrcamentoUseCase
 * 
 * Orquestra a atualização de orçamento seguindo as regras de negócio
 */
class AtualizarOrcamentoUseCase
{
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
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
        if ($orcamentoExistente->processoItemId !== $itemId) {
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
                $itemModel->orcamentos()
                    ->where('id', '!=', $orcamentoExistente->id)
                    ->update(['fornecedor_escolhido' => false]);
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




