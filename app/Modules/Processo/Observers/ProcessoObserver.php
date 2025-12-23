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
        $this->clearCache($processo);
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

