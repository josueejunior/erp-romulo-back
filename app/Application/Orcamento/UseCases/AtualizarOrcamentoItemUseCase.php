<?php

namespace App\Application\Orcamento\UseCases;

use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Modules\Orcamento\Models\OrcamentoItem as OrcamentoItemModel;

/**
 * Application Service: AtualizarOrcamentoItemUseCase
 * 
 * Atualiza o fornecedor_escolhido de um orcamento_item específico
 */
class AtualizarOrcamentoItemUseCase
{
    public function __construct(
        private OrcamentoRepositoryInterface $orcamentoRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    public function executar(int $orcamentoId, int $processoId, int $orcamentoItemId, bool $fornecedorEscolhido, int $empresaId)
    {
        // Buscar orçamento
        $orcamento = $this->orcamentoRepository->buscarPorId($orcamentoId);
        
        if (!$orcamento) {
            throw new DomainException('Orçamento não encontrado.');
        }

        // Validar se pertence à empresa
        if ($orcamento->empresaId !== $empresaId) {
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

        // Regra de negócio: não permitir alterar seleção em processos em execução
        // Buscar modelo para verificar status
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if ($processoModel && $processoModel->isEmExecucao()) {
            throw new DomainException('Não é possível alterar seleção de orçamentos em processos em execução.');
        }

        // Buscar modelo Eloquent para atualizar orcamento_item
        $orcamentoModel = $this->orcamentoRepository->buscarModeloPorId($orcamentoId);
        if (!$orcamentoModel) {
            throw new DomainException('Orçamento não encontrado.');
        }

        // Buscar o orcamento_item específico
        $orcamentoItem = $orcamentoModel->itens()->find($orcamentoItemId);
        if (!$orcamentoItem) {
            throw new DomainException('Item do orçamento não encontrado.');
        }

        // Atualizar fornecedor_escolhido
        $orcamentoItem->fornecedor_escolhido = $fornecedorEscolhido;
        $orcamentoItem->save();

        // Se marcou como escolhido, desmarcar outros do mesmo processo_item
        if ($fornecedorEscolhido) {
            $processoItemId = $orcamentoItem->processo_item_id;
            OrcamentoItemModel::query()
                ->where('processo_item_id', $processoItemId)
                ->where('id', '!=', $orcamentoItemId)
                ->whereHas('orcamento', function ($q) use ($processoId, $empresaId) {
                    $q->where('processo_id', $processoId)->where('empresa_id', $empresaId);
                })
                ->update(['fornecedor_escolhido' => false]);
        }

        // Sincronizar valor mínimo de venda do item com o orçamento escolhido (estrutura nova)
        $processoItem = $orcamentoItem->processoItem;
        if ($processoItem) {
            $escolhido = OrcamentoItemModel::query()
                ->where('processo_item_id', $processoItem->id)
                ->where('fornecedor_escolhido', true)
                ->whereHas('orcamento', function ($q) use ($processoId, $empresaId) {
                    $q->where('processo_id', $processoId)->where('empresa_id', $empresaId);
                })
                ->with('formacaoPreco')
                ->latest('id')
                ->first();

            if ($escolhido) {
                $custoProduto = (float) ($escolhido->custo_produto ?? 0);
                $frete = (float) ($escolhido->frete ?? 0);
                $freteIncluido = (bool) ($escolhido->frete_incluido ?? false);
                $valorMinimo = $escolhido->formacaoPreco?->preco_minimo;
                if ($valorMinimo === null) {
                    $valorMinimo = $custoProduto + ($freteIncluido ? 0 : $frete);
                }
                $processoItem->valor_minimo_venda = $valorMinimo;
            } else {
                $processoItem->valor_minimo_venda = null;
            }
            $processoItem->save();
        }

        // Recarregar relacionamentos
        $orcamentoModel->refresh();
        $orcamentoModel->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);

        return $orcamentoModel;
    }
}

