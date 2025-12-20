<?php

namespace App\Observers;

use App\Models\Empenho;

class EmpenhoObserver
{
    public function created(Empenho $empenho)
    {
        $this->atualizarDocumentoVinculado($empenho);
    }
    
    public function updated(Empenho $empenho)
    {
        $this->atualizarDocumentoVinculado($empenho);
    }
    
    public function deleted(Empenho $empenho)
    {
        $this->atualizarDocumentoVinculado($empenho);
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
}

