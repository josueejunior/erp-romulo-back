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
        
        $this->clearCache($processo);
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

