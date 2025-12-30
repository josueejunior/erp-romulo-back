<?php

namespace App\Observers;

use App\Modules\Empenho\Models\Empenho;
use App\Modules\Processo\Services\SaldoService;

class EmpenhoObserver
{
    protected SaldoService $saldoService;

    public function __construct(SaldoService $saldoService)
    {
        $this->saldoService = $saldoService;
    }

    public function created(Empenho $empenho)
    {
        $this->atualizarDocumentoVinculado($empenho);
        $this->atualizarSaldoProcesso($empenho);
    }
    
    public function updated(Empenho $empenho)
    {
        $this->atualizarDocumentoVinculado($empenho);
        $this->atualizarSaldoProcesso($empenho);
        
        // Atualizar situação do empenho baseado em prazos
        $empenho->atualizarSituacao();
    }
    
    public function deleted(Empenho $empenho)
    {
        $this->atualizarDocumentoVinculado($empenho);
        $this->atualizarSaldoProcesso($empenho);
    }
    
    protected function atualizarDocumentoVinculado(Empenho $empenho)
    {
        if ($empenho->contrato_id && $empenho->contrato) {
            $empenho->contrato->atualizarSaldo();
        }
        
        if ($empenho->autorizacao_fornecimento_id && $empenho->autorizacaoFornecimento) {
            $empenho->autorizacaoFornecimento->atualizarSaldo();
        }
    }

    protected function atualizarSaldoProcesso(Empenho $empenho)
    {
        if ($empenho->processo_id && $empenho->processo) {
            // O saldo do processo é calculado dinamicamente, mas podemos forçar recálculo se necessário
            // Por enquanto, apenas garantir que o processo está carregado
        }
    }
}

