<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Contrato;
use App\Models\AutorizacaoFornecimento;
use App\Models\Empenho;
use App\Models\NotaFiscal;
use Carbon\Carbon;

class SaldoService
{
    /**
     * Calcula o saldo vencido de um processo
     * (valor dos itens vencidos/arrematados)
     */
    public function calcularSaldoVencido(Processo $processo): array
    {
        $itensVencidos = $processo->itens()
            ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
            ->get();

        $saldoVencido = 0;
        $itensDetalhados = [];

        foreach ($itensVencidos as $item) {
            // Usar valor negociado se disponível, senão final, senão estimado
            $valorItem = $item->valor_negociado > 0 
                ? $item->valor_negociado 
                : ($item->valor_final_sessao > 0 ? $item->valor_final_sessao : $item->valor_estimado);
            
            $valorTotal = $valorItem * $item->quantidade;
            $saldoVencido += $valorTotal;

            $itensDetalhados[] = [
                'item_id' => $item->id,
                'numero_item' => $item->numero_item,
                'quantidade' => $item->quantidade,
                'valor_unitario' => $valorItem,
                'valor_total' => $valorTotal,
            ];
        }

        return [
            'saldo_vencido' => round($saldoVencido, 2),
            'quantidade_itens' => count($itensDetalhados),
            'itens' => $itensDetalhados,
        ];
    }

    /**
     * Calcula o saldo vinculado (contratos + AFs)
     */
    public function calcularSaldoVinculado(Processo $processo): array
    {
        $contratos = $processo->contratos()->sum('valor_total') ?? 0;
        $afs = $processo->autorizacoesFornecimento()->sum('valor') ?? 0;
        $totalVinculado = $contratos + $afs;

        return [
            'valor_contratos' => round($contratos, 2),
            'valor_afs' => round($afs, 2),
            'total_vinculado' => round($totalVinculado, 2),
        ];
    }

    /**
     * Calcula o saldo empenhado
     */
    public function calcularSaldoEmpenhado(Processo $processo): array
    {
        $empenhos = $processo->empenhos()->get();
        
        $valorTotalEmpenhado = $empenhos->sum('valor') ?? 0;
        $valorPago = 0;

        foreach ($empenhos as $empenho) {
            // Valor pago = notas fiscais de saída pagas vinculadas ao empenho
            $valorPago += $empenho->notasFiscais()
                ->where('tipo', 'saida')
                ->where('situacao', 'paga')
                ->sum('valor') ?? 0;
        }

        $saldoPendente = $valorTotalEmpenhado - $valorPago;

        return [
            'valor_total_empenhado' => round($valorTotalEmpenhado, 2),
            'valor_pago' => round($valorPago, 2),
            'saldo_pendente' => round($saldoPendente, 2),
            'percentual_pago' => $valorTotalEmpenhado > 0 
                ? round(($valorPago / $valorTotalEmpenhado) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Calcula o saldo completo do processo
     * Vincula: Processo -> Contratos/AFs -> Empenhos -> NFs
     */
    public function calcularSaldoCompleto(Processo $processo): array
    {
        $saldoVencido = $this->calcularSaldoVencido($processo);
        $saldoVinculado = $this->calcularSaldoVinculado($processo);
        $saldoEmpenhado = $this->calcularSaldoEmpenhado($processo);

        // Saldo não vinculado = vencido - vinculado
        $saldoNaoVinculado = $saldoVencido['saldo_vencido'] - $saldoVinculado['total_vinculado'];

        return [
            'saldo_vencido' => $saldoVencido,
            'saldo_vinculado' => $saldoVinculado,
            'saldo_empenhado' => $saldoEmpenhado,
            'saldo_nao_vinculado' => round($saldoNaoVinculado, 2),
            'resumo' => [
                'total_vencido' => $saldoVencido['saldo_vencido'],
                'total_vinculado' => $saldoVinculado['total_vinculado'],
                'total_empenhado' => $saldoEmpenhado['valor_total_empenhado'],
                'total_pago' => $saldoEmpenhado['valor_pago'],
                'total_pendente' => $saldoEmpenhado['saldo_pendente'],
            ],
        ];
    }

    /**
     * Atualiza saldo de um contrato baseado nos empenhos e NFs
     */
    public function atualizarSaldoContrato(Contrato $contrato): void
    {
        $valorEmpenhado = $contrato->empenhos()->sum('valor') ?? 0;
        $valorPago = 0;

        foreach ($contrato->empenhos as $empenho) {
            $valorPago += $empenho->notasFiscais()
                ->where('tipo', 'saida')
                ->where('situacao', 'paga')
                ->sum('valor') ?? 0;
        }

        $contrato->valor_empenhado = $valorEmpenhado;
        $contrato->saldo = $contrato->valor_total - $valorEmpenhado;
        $contrato->save();
    }

    /**
     * Atualiza saldo de uma AF baseado nos empenhos e NFs
     */
    public function atualizarSaldoAF(AutorizacaoFornecimento $af): void
    {
        $valorEmpenhado = $af->empenhos()->sum('valor') ?? 0;
        $valorPago = 0;

        foreach ($af->empenhos as $empenho) {
            $valorPago += $empenho->notasFiscais()
                ->where('tipo', 'saida')
                ->where('situacao', 'paga')
                ->sum('valor') ?? 0;
        }

        $af->valor_empenhado = $valorEmpenhado;
        $af->saldo = $af->valor - $valorEmpenhado;
        $af->save();
    }

    /**
     * Registra pagamento e atualiza saldos
     */
    public function registrarPagamento(NotaFiscal $notaFiscal): void
    {
        if ($notaFiscal->tipo !== 'saida' || $notaFiscal->situacao !== 'paga') {
            return;
        }

        $notaFiscal->situacao = 'paga';
        $notaFiscal->data_pagamento = now();
        $notaFiscal->save();

        // Atualizar saldo do empenho
        if ($notaFiscal->empenho) {
            $empenho = $notaFiscal->empenho;
            
            // Atualizar saldo do contrato se houver
            if ($empenho->contrato) {
                $this->atualizarSaldoContrato($empenho->contrato);
            }

            // Atualizar saldo da AF se houver
            if ($empenho->autorizacaoFornecimento) {
                $this->atualizarSaldoAF($empenho->autorizacaoFornecimento);
            }
        }
    }
}



