<?php

namespace App\Observers;

use App\Models\NotaFiscal;
use App\Modules\Processo\Services\SaldoService;

class NotaFiscalObserver
{
    protected SaldoService $saldoService;

    public function __construct(SaldoService $saldoService)
    {
        $this->saldoService = $saldoService;
    }

    public function created(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
        
        // Se for nota de saída paga, registrar pagamento
        if ($notaFiscal->tipo === 'saida' && $notaFiscal->situacao === 'paga') {
            $this->saldoService->registrarPagamento($notaFiscal);
        }
    }
    
    public function updated(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
        
        // Se mudou para paga, registrar pagamento
        if ($notaFiscal->tipo === 'saida' && $notaFiscal->situacao === 'paga' && !$notaFiscal->data_pagamento) {
            $this->saldoService->registrarPagamento($notaFiscal);
        }
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

