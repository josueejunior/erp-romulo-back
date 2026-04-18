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
        $this->atualizarValoresFinanceirosProcessoItem($notaFiscal);
        
        // Se for nota de saída paga, registrar pagamento
        if ($notaFiscal->tipo === 'saida' && $notaFiscal->situacao === 'paga') {
            $this->saldoService->registrarPagamento($notaFiscal);
        }
    }
    
    public function updated(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
        $this->atualizarValoresFinanceirosProcessoItem($notaFiscal);
        
        // Se mudou para paga, registrar pagamento
        if ($notaFiscal->tipo === 'saida' && $notaFiscal->situacao === 'paga' && !$notaFiscal->data_pagamento) {
            $this->saldoService->registrarPagamento($notaFiscal);
        }
    }
    
    public function deleted(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
        $this->atualizarValoresFinanceirosProcessoItem($notaFiscal);
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
    
    /**
     * Atualiza valores financeiros dos itens do processo vinculados à NF
     * NF de saída atualiza valor_faturado, NF de entrada atualiza valor_pago
     */
    protected function atualizarValoresFinanceirosProcessoItem(NotaFiscal $notaFiscal): void
    {
        // Se a NF está diretamente vinculada a um item do processo
        if ($notaFiscal->processoItem) {
            \App\Modules\Processo\Models\ProcessoItem::withoutEvents(function () use ($notaFiscal) {
                $notaFiscal->processoItem->atualizarValoresFinanceiros();
            });
            return;
        }
        
        // Se a NF está vinculada a um empenho, atualizar itens vinculados ao empenho
        if ($notaFiscal->empenho_id && $notaFiscal->empenho) {
            $this->atualizarItensProcessoVinculados($notaFiscal);
        }
        
        // Se a NF está vinculada a um contrato, atualizar itens vinculados ao contrato
        if ($notaFiscal->contrato_id && $notaFiscal->contrato) {
            $this->atualizarItensProcessoVinculadosContrato($notaFiscal);
        }
        
        // Se a NF está vinculada a uma AF, atualizar itens vinculados à AF
        if ($notaFiscal->autorizacao_fornecimento_id && $notaFiscal->autorizacaoFornecimento) {
            $this->atualizarItensProcessoVinculadosAF($notaFiscal);
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
            // ProcessoItemVinculo não tem processo_id diretamente, precisa buscar através do processoItem
            $vinculos = \App\Modules\Processo\Models\ProcessoItemVinculo::where('empenho_id', $notaFiscal->empenho_id)
                ->whereHas('processoItem', function ($query) use ($processoId) {
                    $query->where('processo_id', $processoId);
                })
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
    
    protected function atualizarItensProcessoVinculadosContrato(NotaFiscal $notaFiscal): void
    {
        if (!$notaFiscal->contrato_id) {
            return;
        }
        
        try {
            $processoId = $notaFiscal->processo_id;
            if (!$processoId && $notaFiscal->contrato) {
                $processoId = $notaFiscal->contrato->processo_id;
            }
            
            if (!$processoId) {
                return;
            }
            
            $vinculos = \App\Modules\Processo\Models\ProcessoItemVinculo::where('contrato_id', $notaFiscal->contrato_id)
                ->whereHas('processoItem', function ($query) use ($processoId) {
                    $query->where('processo_id', $processoId);
                })
                ->with('processoItem')
                ->get();
            
            foreach ($vinculos as $vinculo) {
                if ($vinculo->processoItem) {
                    \App\Modules\Processo\Models\ProcessoItem::withoutEvents(function () use ($vinculo) {
                        $vinculo->processoItem->atualizarValoresFinanceiros();
                    });
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Erro ao atualizar valores financeiros dos itens do processo vinculados ao contrato: " . $e->getMessage());
        }
    }
    
    protected function atualizarItensProcessoVinculadosAF(NotaFiscal $notaFiscal): void
    {
        if (!$notaFiscal->autorizacao_fornecimento_id) {
            return;
        }
        
        try {
            $processoId = $notaFiscal->processo_id;
            if (!$processoId && $notaFiscal->autorizacaoFornecimento) {
                $processoId = $notaFiscal->autorizacaoFornecimento->processo_id;
            }
            
            if (!$processoId) {
                return;
            }
            
            $vinculos = \App\Modules\Processo\Models\ProcessoItemVinculo::where('autorizacao_fornecimento_id', $notaFiscal->autorizacao_fornecimento_id)
                ->whereHas('processoItem', function ($query) use ($processoId) {
                    $query->where('processo_id', $processoId);
                })
                ->with('processoItem')
                ->get();
            
            foreach ($vinculos as $vinculo) {
                if ($vinculo->processoItem) {
                    \App\Modules\Processo\Models\ProcessoItem::withoutEvents(function () use ($vinculo) {
                        $vinculo->processoItem->atualizarValoresFinanceiros();
                    });
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Erro ao atualizar valores financeiros dos itens do processo vinculados à AF: " . $e->getMessage());
        }
    }
}

