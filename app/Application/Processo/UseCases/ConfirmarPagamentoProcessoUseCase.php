<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use DomainException;
use Carbon\Carbon;

/**
 * Use Case: Confirmar Pagamento de Processo
 * 
 * ⚠️ NOTA: Ainda trabalha com modelos Eloquent para acessar relacionamentos (itens).
 * Idealmente, ProcessoItem deveria ser um agregado raiz ou parte do agregado Processo.
 */
class ConfirmarPagamentoProcessoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executar o caso de uso
     */
    public function executar(int $processoId, int $empresaId, ?Carbon $dataRecebimento = null): \App\Modules\Processo\Models\Processo
    {
        // Buscar processo (domain entity)
        $processoDomain = $this->processoRepository->buscarPorId($processoId);
        
        if (!$processoDomain) {
            throw new NotFoundException('Processo', $processoId);
        }
        
        // Validar que o processo pertence à empresa
        if ($processoDomain->empresaId !== $empresaId) {
            throw new DomainException('Processo não pertence à empresa ativa.');
        }
        
        // Validar regra de negócio
        if (!$processoDomain->estaEmExecucao()) {
            throw new DomainException('Apenas processos em execução podem ter pagamento confirmado.');
        }
        
        // Buscar modelo Eloquent para atualização (precisa de relacionamentos)
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId, ['itens']);
        
        if (!$processoModel) {
            throw new NotFoundException('Processo', $processoId);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($processoModel, $dataRecebimento) {
            // Atualizar data de recebimento
            $processoModel->data_recebimento_pagamento = $dataRecebimento ?? now();
            
            // Atualizar valores financeiros de todos os itens
            foreach ($processoModel->itens as $item) {
                $item->atualizarValoresFinanceiros();
            }
            
            // Verificar se todos os itens foram pagos
            $todosItensPagos = $processoModel->itens()
                ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
                ->get()
                ->every(function ($item) {
                    return $item->saldo_aberto <= 0;
                });

            // Se todos os itens foram pagos, mudar status para encerramento
            if ($todosItensPagos && $processoModel->itens()->whereIn('status_item', ['aceito', 'aceito_habilitado'])->count() > 0) {
                $processoModel->status = 'encerramento';
            }

            $processoModel->save();

            return $processoModel->load(['orgao', 'setor', 'itens']);
        });
    }
}

