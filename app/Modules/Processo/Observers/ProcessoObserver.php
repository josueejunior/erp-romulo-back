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
        
        // 🔥 MELHORIA: Atualizar status automaticamente quando data_hora_sessao_publica muda
        // Usa ProcessoStatusService para validações adequadas
        if ($processo->wasChanged('data_hora_sessao_publica')) {
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
     * Verifica e atualiza status do processo baseado na data da sessão pública
     * 
     * @param Processo $processo
     * @param bool $isCreated Se true, está sendo criado (não usar saveQuietly)
     */
    protected function verificarEAtualizarStatusPorData(Processo $processo, bool $isCreated = false): void
    {
        try {
            if (!$processo->data_hora_sessao_publica) {
                return;
            }
            
            $dataHoraSessao = \Carbon\Carbon::parse($processo->data_hora_sessao_publica);
            $agora = \Carbon\Carbon::now();
            $statusAnterior = $processo->getOriginal('status') ?? $processo->status;
            
            // Se a sessão já passou e o processo está em participação, mudar para julgamento_habilitacao
            if ($processo->status === 'participacao' && $agora->isAfter($dataHoraSessao)) {
                // Validar transição usando ProcessoStatusService
                $validacao = $this->statusService->podeAlterarStatus($processo, 'julgamento_habilitacao');
                
                if ($validacao['pode']) {
                    $processo->status = 'julgamento_habilitacao';
                    
                    if ($isCreated) {
                        $processo->save();
                    } else {
                        $processo->saveQuietly(); // Usar saveQuietly para evitar loop infinito
                    }
                    
                    Log::info('ProcessoObserver - Status atualizado automaticamente por data', [
                        'processo_id' => $processo->id,
                        'status_anterior' => $statusAnterior,
                        'status_novo' => 'julgamento_habilitacao',
                        'data_sessao' => $processo->data_hora_sessao_publica,
                        'motivo' => 'Data da sessão pública já passou',
                        'is_created' => $isCreated,
                    ]);
                } else {
                    Log::warning('ProcessoObserver - Não foi possível atualizar status automaticamente', [
                        'processo_id' => $processo->id,
                        'status_atual' => $processo->status,
                        'status_desejado' => 'julgamento_habilitacao',
                        'motivo' => $validacao['motivo'] ?? 'Validação falhou',
                    ]);
                }
            }
            // 🔥 CORREÇÃO: Se a sessão é no futuro e o processo está em julgamento_habilitacao,
            // voltar para participacao para permitir criar orçamento/disputa
            elseif ($processo->status === 'julgamento_habilitacao' && $agora->isBefore($dataHoraSessao)) {
                // Validar transição usando ProcessoStatusService
                // Nota: Retrocesso de julgamento_habilitacao para participacao não está nas transições padrão
                // Mas é permitido quando a data é alterada para o futuro (caso especial)
                $processo->status = 'participacao';
                
                if ($isCreated) {
                    $processo->save();
                } else {
                    $processo->saveQuietly(); // Usar saveQuietly para evitar loop infinito
                }
                
                Log::info('ProcessoObserver - Status revertido para participação (data alterada para o futuro)', [
                    'processo_id' => $processo->id,
                    'status_anterior' => $statusAnterior,
                    'status_novo' => 'participacao',
                    'data_sessao' => $processo->data_hora_sessao_publica,
                    'motivo' => 'Data da sessão pública foi alterada para o futuro - permitindo criar orçamento/disputa',
                    'is_created' => $isCreated,
                ]);
            }
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

