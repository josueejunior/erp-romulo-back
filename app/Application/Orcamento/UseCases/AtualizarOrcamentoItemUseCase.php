<?php

namespace App\Application\Orcamento\UseCases;

use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use DomainException;

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
            $orcamentoModel->itens()
                ->where('processo_item_id', $processoItemId)
                ->where('id', '!=', $orcamentoItemId)
                ->update(['fornecedor_escolhido' => false]);
        }

        // Recarregar relacionamentos
        $orcamentoModel->refresh();
        $orcamentoModel->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);

        return $orcamentoModel;
    }
}

