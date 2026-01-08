<?php

namespace App\Application\Empenho\UseCases;

use App\Application\Empenho\DTOs\AtualizarEmpenhoDTO;
use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use DomainException;

/**
 * Application Service: AtualizarEmpenhoUseCase
 * 
 * Orquestra a atualização de empenho seguindo as regras de negócio
 */
class AtualizarEmpenhoUseCase
{
    public function __construct(
        private EmpenhoRepositoryInterface $empenhoRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    public function executar(AtualizarEmpenhoDTO $dto, int $empresaId): Empenho
    {
        // Buscar empenho existente
        $empenhoExistente = $this->empenhoRepository->buscarPorId($dto->empenhoId);
        
        if (!$empenhoExistente) {
            throw new DomainException('Empenho não encontrado.');
        }

        // Validar se pertence à empresa (regra de domínio)
        if ($empenhoExistente->empresaId !== $empresaId) {
            throw new DomainException('Empenho não pertence à empresa ativa.');
        }

        // Regra de negócio: empenho concluído não pode ser alterado
        if ($empenhoExistente->concluido) {
            throw new DomainException('Empenho concluído não pode ser alterado.');
        }

        // Se processo foi alterado, validar novo processo
        $processoId = $dto->processoId ?? $empenhoExistente->processoId;
        if ($processoId && $processoId !== $empenhoExistente->processoId) {
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
                throw new DomainException('Empenhos só podem ser vinculados a processos em execução.');
            }
        }

        // Calcular prazo de entrega se data_recebimento foi alterada
        $prazoEntregaCalculado = $dto->prazoEntregaCalculado ?? $empenhoExistente->prazoEntregaCalculado;
        if (!$prazoEntregaCalculado && ($dto->dataRecebimento || $empenhoExistente->dataRecebimento)) {
            $dataRecebimento = $dto->dataRecebimento ?? $empenhoExistente->dataRecebimento;
            if ($processoId) {
                $processoModel = \App\Modules\Processo\Models\Processo::find($processoId);
                if ($processoModel && $processoModel->prazo_entrega) {
                    $prazoEntregaCalculado = $this->calcularPrazoEntrega($processoModel, $dataRecebimento);
                }
            }
        }

        // Criar nova instância com dados atualizados (entidade imutável)
        $empenhoAtualizado = new Empenho(
            id: $empenhoExistente->id,
            empresaId: $empenhoExistente->empresaId,
            processoId: $processoId ?? $empenhoExistente->processoId,
            contratoId: $dto->contratoId ?? $empenhoExistente->contratoId,
            autorizacaoFornecimentoId: $dto->autorizacaoFornecimentoId ?? $empenhoExistente->autorizacaoFornecimentoId,
            numero: $dto->numero ?? $empenhoExistente->numero,
            data: $dto->data ?? $empenhoExistente->data,
            dataRecebimento: $dto->dataRecebimento ?? $empenhoExistente->dataRecebimento,
            prazoEntregaCalculado: $prazoEntregaCalculado,
            valor: $dto->valor ?? $empenhoExistente->valor,
            concluido: $empenhoExistente->concluido, // Não pode ser alterado via update
            situacao: $dto->situacao ?? $empenhoExistente->situacao,
            dataEntrega: $dto->dataEntrega ?? $empenhoExistente->dataEntrega,
            observacoes: $dto->observacoes ?? $empenhoExistente->observacoes,
            numeroCte: $dto->numeroCte ?? $empenhoExistente->numeroCte,
        );

        // Persistir atualização
        return $this->empenhoRepository->atualizar($empenhoAtualizado);
    }

    /**
     * Calcula o prazo de entrega baseado na data de recebimento e prazo do edital
     */
    private function calcularPrazoEntrega($processo, $dataRecebimento): ?\Carbon\Carbon
    {
        if (!$processo || !$processo->prazo_entrega) {
            return null;
        }

        $prazoEntrega = $this->parsePrazoEntrega($processo->prazo_entrega);
        if (!$prazoEntrega) {
            return null;
        }

        return \Carbon\Carbon::parse($dataRecebimento)->add($prazoEntrega);
    }

    /**
     * Faz parse do prazo de entrega do processo
     */
    private function parsePrazoEntrega(string $prazoEntrega): ?\DateInterval
    {
        $prazoEntrega = strtolower(trim($prazoEntrega));
        
        if (preg_match('/(\d+)\s*(dia|dias|mes|meses|mês|mêses|ano|anos)/', $prazoEntrega, $matches)) {
            $quantidade = (int) $matches[1];
            $unidade = $matches[2];
            
            switch ($unidade) {
                case 'dia':
                case 'dias':
                    return new \DateInterval("P{$quantidade}D");
                case 'mes':
                case 'meses':
                case 'mês':
                case 'mêses':
                    return new \DateInterval("P{$quantidade}M");
                case 'ano':
                case 'anos':
                    return new \DateInterval("P{$quantidade}Y");
            }
        }
        
        return null;
    }
}

