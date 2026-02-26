<?php

namespace App\Modules\Processo\Observers;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\ProcessoStatusService;
use App\Services\RedisService;
use Illuminate\Support\Facades\Log;

class ProcessoObserver
{
    protected ProcessoStatusService $statusService;

    public function __construct(ProcessoStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Handle the Processo "created" event.
     */
    public function created(Processo $processo): void
    {
        // Se o processo foi criado com data_hora_sessao_publica no passado, ajustar status
        if ($processo->data_hora_sessao_publica) {
            $this->verificarEAtualizarStatusPorData($processo, true);
        }
        
        $this->clearCache($processo);
    }

    /**
     * Handle the Processo "updated" event.
     */
    public function updated(Processo $processo): void
    {
        // Se o processo acabou de entrar em execução, recalcular valores financeiros dos itens
        if ($processo->wasChanged('status') && $processo->status === 'execucao') {
            $this->recalcularValoresFinanceirosItens($processo);
        }
        
        // Atualizar status pelo período (início/fim da disputa) quando datas mudam
        if ($processo->wasChanged('data_hora_sessao_publica') || $processo->wasChanged('data_hora_inicio_disputa')) {
            $this->verificarEAtualizarStatusPorData($processo);
        }
        
        // 🔥 NOVO: Verificar se há sugestão de mudança de status baseado em regras de negócio
        // Verificar se itens foram alterados (através de relacionamento)
        if ($processo->wasChanged('status') || $processo->relationLoaded('itens')) {
            $this->verificarSugestaoStatus($processo);
        }
        
        $this->clearCache($processo);
    }
    
    /**
     * Verifica e atualiza status do processo pelo período (em preparação / em disputa / em julgamento).
     *
     * @param Processo $processo
     * @param bool $isCreated Se true, está sendo criado (não usar saveQuietly)
     */
    protected function verificarEAtualizarStatusPorData(Processo $processo, bool $isCreated = false): void
    {
        try {
            $sugerido = $this->statusService->getStatusSugeridoPorPeriodo($processo);
            if ($sugerido === null || $sugerido === $processo->status) {
                return;
            }

            $statusAnterior = $processo->getOriginal('status') ?? $processo->status;
            $statusPermitidosParaAjuste = ['participacao', 'em_disputa', 'julgamento_habilitacao'];
            if (!in_array($processo->status, $statusPermitidosParaAjuste)) {
                return;
            }

            $result = $this->statusService->alterarStatus($processo, $sugerido, true);
            if (!$result['pode']) {
                Log::warning('ProcessoObserver - Não foi possível atualizar status pelo período', [
                    'processo_id' => $processo->id,
                    'status_atual' => $processo->status,
                    'status_desejado' => $sugerido,
                    'motivo' => $result['motivo'] ?? 'Validação falhou',
                ]);
                return;
            }

            Log::info('ProcessoObserver - Status atualizado automaticamente pelo período', [
                'processo_id' => $processo->id,
                'status_anterior' => $statusAnterior,
                'status_novo' => $sugerido,
                'motivo' => 'Período início/fim da disputa',
                'is_created' => $isCreated,
            ]);
        } catch (\Exception $e) {
            Log::warning("Erro ao verificar status do processo por data: " . $e->getMessage(), [
                'processo_id' => $processo->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    
    /**
     * Verifica se há sugestão de mudança de status baseado em regras de negócio
     * 
     * @param Processo $processo
     */
    protected function verificarSugestaoStatus(Processo $processo): void
    {
        try {
            $sugestao = $this->statusService->sugerirProximoStatus($processo);
            
            if ($sugestao && $sugestao !== $processo->status) {
                // Log da sugestão (não altera automaticamente, apenas sugere)
                Log::debug('ProcessoObserver - Sugestão de mudança de status', [
                    'processo_id' => $processo->id,
                    'status_atual' => $processo->status,
                    'status_sugerido' => $sugestao,
                ]);
                
                // Em casos específicos, pode aplicar automaticamente
                // Por exemplo, se está em participação e a data passou, já foi tratado em verificarEAtualizarStatusPorData
            }
        } catch (\Exception $e) {
            Log::warning("Erro ao verificar sugestão de status: " . $e->getMessage(), [
                'processo_id' => $processo->id,
            ]);
        }
    }
    
    /**
     * Recalcula valores financeiros de todos os itens quando processo entra em execução
     */
    protected function recalcularValoresFinanceirosItens(Processo $processo): void
    {
        try {
            $itens = $processo->itens()
                ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
                ->get();
            
            foreach ($itens as $item) {
                $item->atualizarValoresFinanceiros();
            }
        } catch (\Exception $e) {
            // Log erro mas não interrompe o fluxo
            \Log::warning("Erro ao recalcular valores financeiros dos itens do processo {$processo->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the Processo "deleted" event.
     */
    public function deleted(Processo $processo): void
    {
        $this->clearCache($processo);
    }

    /**
     * Limpar caches relacionados ao processo
     */
    protected function clearCache(Processo $processo): void
    {
        if (!RedisService::isAvailable()) {
            return;
        }

        $tenantId = tenancy()->tenant?->id;
        if (!$tenantId) {
            return;
        }

        // Limpar cache de dashboard
        RedisService::clearDashboard($tenantId);
        
        // Limpar cache de processos
        RedisService::clearProcessos($tenantId);
        
        // Limpar cache de saldo deste processo
        RedisService::clearSaldo($tenantId, $processo->id);
        
        // Limpar cache de calendário (pode ter eventos deste processo)
        RedisService::clearCalendario($tenantId);
        
        // Limpar cache de relatórios financeiros se o processo foi encerrado
        if ($processo->status === 'encerramento' || $processo->data_recebimento_pagamento) {
            RedisService::clearRelatorioFinanceiro($tenantId);
        }
    }
}

