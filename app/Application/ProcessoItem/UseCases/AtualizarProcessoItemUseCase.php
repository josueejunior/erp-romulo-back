<?php

namespace App\Application\ProcessoItem\UseCases;

use App\Application\ProcessoItem\DTOs\AtualizarProcessoItemDTO;
use App\Domain\ProcessoItem\Entities\ProcessoItem;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\ProcessoEmExecucaoException;
use App\Domain\Exceptions\EntidadeNaoPertenceException;
use DomainException;

/**
 * Use Case: Atualizar Item de Processo
 */
class AtualizarProcessoItemUseCase
{
    public function __construct(
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(AtualizarProcessoItemDTO $dto): ProcessoItem
    {
        // Buscar processo existente
        $processo = $this->processoRepository->buscarPorId($dto->processoId);
        
        if (!$processo) {
            throw new NotFoundException('Processo', $dto->processoId);
        }
        
        // Validar que o processo pertence à empresa
        if ($processo->empresaId !== $dto->empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        // Buscar item existente
        $itemExistente = $this->processoItemRepository->buscarPorId($dto->processoItemId);
        
        if (!$itemExistente) {
            throw new NotFoundException('Item de Processo', $dto->processoItemId);
        }
        
        // Validar que o item pertence ao processo
        if ($itemExistente->processoId !== $dto->processoId) {
            throw new EntidadeNaoPertenceException(
                'Item de Processo',
                'Processo',
                $dto->processoItemId,
                $dto->processoId
            );
        }
        
        // Validar regra de negócio: processo não pode estar em execução
        if ($processo->estaEmExecucao()) {
            throw new ProcessoEmExecucaoException('Não é possível editar itens de processos em execução.', $dto->processoId);
        }
        
        // Criar nova instância com valores atualizados (imutabilidade)
        $processoItemAtualizado = new ProcessoItem(
            id: $dto->processoItemId,
            processoId: $dto->processoId,
            fornecedorId: $dto->fornecedorId ?? $itemExistente->fornecedorId,
            transportadoraId: $dto->transportadoraId ?? $itemExistente->transportadoraId,
            numeroItem: $dto->numeroItem ?? $itemExistente->numeroItem,
            codigoInterno: $dto->codigoInterno ?? $itemExistente->codigoInterno,
            quantidade: $dto->quantidade ?? $itemExistente->quantidade,
            unidade: $dto->unidade ?? $itemExistente->unidade,
            especificacaoTecnica: $dto->especificacaoTecnica ?? $itemExistente->especificacaoTecnica,
            marcaModeloReferencia: $dto->marcaModeloReferencia ?? $itemExistente->marcaModeloReferencia,
            observacoesEdital: $dto->observacoesEdital ?? $itemExistente->observacoesEdital,
            exigeAtestado: $dto->exigeAtestado ?? $itemExistente->exigeAtestado,
            quantidadeMinimaAtestado: $dto->quantidadeMinimaAtestado ?? $itemExistente->quantidadeMinimaAtestado,
            quantidadeAtestadoCapTecnica: $dto->quantidadeAtestadoCapTecnica ?? $itemExistente->quantidadeAtestadoCapTecnica,
            valorEstimado: $dto->valorEstimado ?? $itemExistente->valorEstimado,
            valorEstimadoTotal: $itemExistente->calcularValorEstimadoTotal(), // Recalcular baseado nos valores atualizados
            fonteValor: $itemExistente->fonteValor,
            valorMinimoVenda: $itemExistente->valorMinimoVenda,
            valorFinalSessao: $itemExistente->valorFinalSessao,
            valorArrematado: $itemExistente->valorArrematado,
            dataDisputa: $itemExistente->dataDisputa,
            valorNegociado: $itemExistente->valorNegociado,
            classificacao: $itemExistente->classificacao,
            statusItem: $itemExistente->statusItem,
            situacaoFinal: $itemExistente->situacaoFinal,
            chanceArremate: $itemExistente->chanceArremate,
            chancePercentual: $itemExistente->chancePercentual,
            temChance: $itemExistente->temChance,
            lembretes: $itemExistente->lembretes,
            observacoes: $dto->observacoes ?? $itemExistente->observacoes,
            valorVencido: $itemExistente->valorVencido,
            valorEmpenhado: $itemExistente->valorEmpenhado,
            valorFaturado: $itemExistente->valorFaturado,
            valorPago: $itemExistente->valorPago,
            saldoAberto: $itemExistente->saldoAberto,
            lucroBruto: $itemExistente->lucroBruto,
            lucroLiquido: $itemExistente->lucroLiquido,
        );
        
        // Persistir alterações
        return $this->processoItemRepository->atualizar($processoItemAtualizado);
    }
}

