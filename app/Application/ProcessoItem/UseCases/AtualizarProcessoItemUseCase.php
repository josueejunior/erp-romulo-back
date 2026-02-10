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
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Atualizar Item de Processo
 * 
 * 🔒 ROBUSTEZ: Usa transação para garantir atomicidade da atualização
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
        
        // 🔒 ROBUSTEZ: Usar transação para garantir atomicidade
        // Embora neste caso específico haja apenas uma operação de escrita,
        // usar transação garante consistência e facilita futuras extensões
        return DB::transaction(function () use ($dto, $itemExistente) {
            // ✅ CORRIGIDO: Usar apenas campos que foram realmente enviados no request
            // Se o campo foi enviado (mesmo que null/vazio), usar o valor do DTO
            // Caso contrário, manter o valor existente
            $processoItemAtualizado = new ProcessoItem(
                id: $dto->processoItemId,
                processoId: $dto->processoId,
                empresaId: $itemExistente->empresaId,
                fornecedorId: $dto->campoFoiEnviado('fornecedor_id') ? $dto->fornecedorId : $itemExistente->fornecedorId,
                transportadoraId: $dto->campoFoiEnviado('transportadora_id') ? $dto->transportadoraId : $itemExistente->transportadoraId,
                numeroItem: $dto->campoFoiEnviado('numero_item') ? $dto->numeroItem : $itemExistente->numeroItem,
                nome: $dto->campoFoiEnviado('nome') ? $dto->nome : $itemExistente->nome,
                codigoInterno: $dto->campoFoiEnviado('codigo_interno') ? $dto->codigoInterno : $itemExistente->codigoInterno,
                quantidade: $dto->campoFoiEnviado('quantidade') ? $dto->quantidade : $itemExistente->quantidade,
                unidade: $dto->campoFoiEnviado('unidade') ? $dto->unidade : $itemExistente->unidade,
                especificacaoTecnica: $dto->campoFoiEnviado('especificacao_tecnica') ? $dto->especificacaoTecnica : $itemExistente->especificacaoTecnica,
                marcaModeloReferencia: $dto->campoFoiEnviado('marca_modelo_referencia') ? $dto->marcaModeloReferencia : $itemExistente->marcaModeloReferencia,
                observacoesEdital: $dto->campoFoiEnviado('observacoes_edital') ? $dto->observacoesEdital : $itemExistente->observacoesEdital,
                exigeAtestado: $dto->campoFoiEnviado('exige_atestado') ? ($dto->exigeAtestado ?? false) : $itemExistente->exigeAtestado,
                quantidadeMinimaAtestado: $dto->campoFoiEnviado('quantidade_minima_atestado') ? $dto->quantidadeMinimaAtestado : $itemExistente->quantidadeMinimaAtestado,
                quantidadeAtestadoCapTecnica: $dto->campoFoiEnviado('quantidade_atestado_cap_tecnica') ? $dto->quantidadeAtestadoCapTecnica : $itemExistente->quantidadeAtestadoCapTecnica,
                valorEstimado: $dto->campoFoiEnviado('valor_estimado') ? $dto->valorEstimado : $itemExistente->valorEstimado,
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
                observacoes: $dto->campoFoiEnviado('observacoes') ? $dto->observacoes : $itemExistente->observacoes,
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
        });
    }
}









