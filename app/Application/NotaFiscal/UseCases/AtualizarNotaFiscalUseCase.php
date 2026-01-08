<?php

namespace App\Application\NotaFiscal\UseCases;

use App\Application\NotaFiscal\DTOs\AtualizarNotaFiscalDTO;
use App\Domain\NotaFiscal\Entities\NotaFiscal;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use DomainException;

/**
 * Application Service: AtualizarNotaFiscalUseCase
 * 
 * Orquestra a atualização de nota fiscal seguindo as regras de negócio
 */
class AtualizarNotaFiscalUseCase
{
    public function __construct(
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    public function executar(AtualizarNotaFiscalDTO $dto, int $empresaId): NotaFiscal
    {
        // Buscar nota fiscal existente
        $notaFiscalExistente = $this->notaFiscalRepository->buscarPorId($dto->notaFiscalId);
        
        if (!$notaFiscalExistente) {
            throw new DomainException('Nota fiscal não encontrada.');
        }

        // Validar se pertence à empresa
        if ($notaFiscalExistente->empresaId !== $empresaId) {
            throw new DomainException('Nota fiscal não pertence à empresa ativa.');
        }

        // Se processo foi alterado, validar novo processo
        $processoId = $dto->processoId ?? $notaFiscalExistente->processoId;
        if ($processoId !== $notaFiscalExistente->processoId) {
            $processo = $this->processoRepository->buscarPorId($processoId);
            if (!$processo) {
                throw new DomainException('Processo não encontrado.');
            }
            
            // Validar que o processo pertence à empresa
            if ($processo->empresaId !== $empresaId) {
                throw new DomainException('Processo não pertence à empresa ativa.');
            }
            
            // Validar que o processo está em execução
            if (!$processo->estaEmExecucao()) {
                throw new DomainException('Notas fiscais só podem ser vinculadas a processos em execução.');
            }
        }

        // Validar que há pelo menos um vínculo (empenho, contrato ou autorização)
        $empenhoId = $dto->empenhoId ?? $notaFiscalExistente->empenhoId;
        $contratoId = $dto->contratoId ?? $notaFiscalExistente->contratoId;
        $autorizacaoFornecimentoId = $dto->autorizacaoFornecimentoId ?? $notaFiscalExistente->autorizacaoFornecimentoId;
        
        if (!$empenhoId && !$contratoId && !$autorizacaoFornecimentoId) {
            throw new DomainException('Nota fiscal deve estar vinculada a um Empenho, Contrato ou Autorização de Fornecimento.');
        }

        // Calcular custo total se custo_produto ou custo_frete foram alterados
        $custoProduto = $dto->custoProduto ?? $notaFiscalExistente->custoProduto;
        $custoFrete = $dto->custoFrete ?? $notaFiscalExistente->custoFrete;
        $custoTotal = round($custoProduto + $custoFrete, 2);

        // Criar nova instância com dados atualizados (entidade imutável)
        $notaFiscalAtualizada = new NotaFiscal(
            id: $notaFiscalExistente->id,
            empresaId: $notaFiscalExistente->empresaId,
            processoId: $processoId,
            processoItemId: $dto->processoItemId ?? $notaFiscalExistente->processoItemId,
            empenhoId: $empenhoId,
            contratoId: $contratoId,
            autorizacaoFornecimentoId: $autorizacaoFornecimentoId,
            tipo: $dto->tipo ?? $notaFiscalExistente->tipo,
            numero: $dto->numero ?? $notaFiscalExistente->numero,
            serie: $dto->serie ?? $notaFiscalExistente->serie,
            dataEmissao: $dto->dataEmissao ?? $notaFiscalExistente->dataEmissao,
            fornecedorId: $dto->fornecedorId ?? $notaFiscalExistente->fornecedorId,
            transportadora: $dto->transportadora ?? $notaFiscalExistente->transportadora,
            numeroCte: $dto->numeroCte ?? $notaFiscalExistente->numeroCte,
            dataEntregaPrevista: $dto->dataEntregaPrevista ?? $notaFiscalExistente->dataEntregaPrevista,
            dataEntregaRealizada: $dto->dataEntregaRealizada ?? $notaFiscalExistente->dataEntregaRealizada,
            situacaoLogistica: $dto->situacaoLogistica ?? $notaFiscalExistente->situacaoLogistica,
            valor: $dto->valor ?? $notaFiscalExistente->valor,
            custoProduto: $custoProduto,
            custoFrete: $custoFrete,
            custoTotal: $custoTotal,
            comprovantePagamento: $dto->comprovantePagamento ?? $notaFiscalExistente->comprovantePagamento,
            arquivo: $dto->arquivo ?? $notaFiscalExistente->arquivo,
            situacao: $dto->situacao ?? $notaFiscalExistente->situacao,
            dataPagamento: $dto->dataPagamento ?? $notaFiscalExistente->dataPagamento,
            observacoes: $dto->observacoes ?? $notaFiscalExistente->observacoes,
        );

        // Persistir atualização
        return $this->notaFiscalRepository->atualizar($notaFiscalAtualizada);
    }
}

