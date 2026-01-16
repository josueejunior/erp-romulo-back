<?php

namespace App\Modules\Processo\Observers;

use App\Modules\Processo\Models\Processo;
use App\Services\RedisService;

class ProcessoObserver
{
    /**
     * Handle the Processo "created" event.
     */
    public function created(Processo $processo): void
    {
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
        
        // 🔥 NOVO: Atualizar status automaticamente quando data_hora_sessao_publica muda
        // Se a data da sessão pública foi alterada e o processo está em participação,
        // verificar se deve mudar para julgamento_habilitacao
        if ($processo->wasChanged('data_hora_sessao_publica') && $processo->status === 'participacao') {
            $this->verificarEAtualizarStatusPorData($processo);
        }
        
        // 🔥 NOVO: Se a data da sessão pública mudou e o processo está em julgamento_habilitacao,
        // mas a nova data é no futuro, voltar para participacao (se fizer sentido)
        if ($processo->wasChanged('data_hora_sessao_publica') && $processo->status === 'julgamento_habilitacao') {
            $this->verificarEAtualizarStatusPorData($processo);
        }
        
        $this->clearCache($processo);
    }
    
    /**
     * Verifica e atualiza status do processo baseado na data da sessão pública
     */
    protected function verificarEAtualizarStatusPorData(Processo $processo): void
    {
        try {
            if (!$processo->data_hora_sessao_publica) {
                return;
            }
            
            $dataHoraSessao = \Carbon\Carbon::parse($processo->data_hora_sessao_publica);
            $agora = \Carbon\Carbon::now();
            
            // Se a sessão já passou e o processo está em participação, mudar para julgamento_habilitacao
            if ($processo->status === 'participacao' && $agora->isAfter($dataHoraSessao)) {
                $processo->status = 'julgamento_habilitacao';
                $processo->saveQuietly(); // Usar saveQuietly para evitar loop infinito
                
                \Log::info('ProcessoObserver - Status atualizado automaticamente por data', [
                    'processo_id' => $processo->id,
                    'status_anterior' => 'participacao',
                    'status_novo' => 'julgamento_habilitacao',
                    'data_sessao' => $processo->data_hora_sessao_publica,
                    'motivo' => 'Data da sessão pública já passou',
                ]);
            }
            // 🔥 CORREÇÃO: Se a sessão é no futuro e o processo está em julgamento_habilitacao,
            // voltar para participacao para permitir criar orçamento/disputa
            elseif ($processo->status === 'julgamento_habilitacao' && $agora->isBefore($dataHoraSessao)) {
                $processo->status = 'participacao';
                $processo->saveQuietly(); // Usar saveQuietly para evitar loop infinito
                
                \Log::info('ProcessoObserver - Status revertido para participação (data alterada para o futuro)', [
                    'processo_id' => $processo->id,
                    'status_anterior' => 'julgamento_habilitacao',
                    'status_novo' => 'participacao',
                    'data_sessao' => $processo->data_hora_sessao_publica,
                    'motivo' => 'Data da sessão pública foi alterada para o futuro - permitindo criar orçamento/disputa',
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning("Erro ao verificar status do processo por data: " . $e->getMessage(), [
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

