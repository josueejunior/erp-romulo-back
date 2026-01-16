<?php

namespace App\Observers;

use App\Modules\NotaFiscal\Models\NotaFiscal;
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
            
            // Atualizar valores financeiros dos itens do processo vinculados ao empenho
            $this->atualizarItensProcessoVinculados($notaFiscal);
        }
    }
    
    /**
     * Atualiza valores financeiros dos itens do processo vinculados ao empenho
     * NF de saída atualiza valor_faturado, NF de entrada atualiza valor_pago
     */
    protected function atualizarItensProcessoVinculados(NotaFiscal $notaFiscal): void
    {
        if (!$notaFiscal->empenho_id) {
            return;
        }
        
        try {
            // Buscar processo_id através do empenho se não estiver na NF
            $processoId = $notaFiscal->processo_id;
            if (!$processoId && $notaFiscal->empenho) {
                $processoId = $notaFiscal->empenho->processo_id;
            }
            
            if (!$processoId) {
                return;
            }
            
            // Buscar vínculos entre itens do processo e o empenho
            $vinculos = \App\Modules\Processo\Models\ProcessoItemVinculo::where('empenho_id', $notaFiscal->empenho_id)
                ->where('processo_id', $processoId)
                ->with('processoItem')
                ->get();
            
            // Atualizar valores financeiros de cada item vinculado
            foreach ($vinculos as $vinculo) {
                if ($vinculo->processoItem) {
                    // Usar withoutEvents para evitar loops infinitos
                    \App\Modules\Processo\Models\ProcessoItem::withoutEvents(function () use ($vinculo) {
                        $vinculo->processoItem->atualizarValoresFinanceiros();
                    });
                }
            }
            
            \Log::debug('NotaFiscalObserver - Valores financeiros dos itens atualizados', [
                'nota_fiscal_id' => $notaFiscal->id,
                'tipo' => $notaFiscal->tipo,
                'empenho_id' => $notaFiscal->empenho_id,
                'processo_id' => $processoId,
                'vinculos_atualizados' => $vinculos->count(),
            ]);
        } catch (\Exception $e) {
            \Log::warning("Erro ao atualizar valores financeiros dos itens do processo vinculados ao empenho: " . $e->getMessage(), [
                'nota_fiscal_id' => $notaFiscal->id,
                'empenho_id' => $notaFiscal->empenho_id,
                'processo_id' => $notaFiscal->processo_id,
            ]);
        }
    }
}

