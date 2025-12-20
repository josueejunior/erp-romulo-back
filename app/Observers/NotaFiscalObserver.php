<?php

namespace App\Observers;

use App\Models\NotaFiscal;

class NotaFiscalObserver
{
    public function created(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
    }
    
    public function updated(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
    }
    
    public function deleted(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
    }
    
    protected function atualizarDocumentoVinculado(NotaFiscal $notaFiscal)
    {
        if ($notaFiscal->contrato_id && $notaFiscal->contrato) {
            $notaFiscal->contrato->atualizarSaldo();
        }
        
        if ($notaFiscal->autorizacao_fornecimento_id && $notaFiscal->autorizacaoFornecimento) {
            $notaFiscal->autorizacaoFornecimento->atualizarSaldo();
        }
        
        if ($notaFiscal->empenho_id && $notaFiscal->empenho) {
            $notaFiscal->empenho->atualizarSaldo();
        }
    }
}

