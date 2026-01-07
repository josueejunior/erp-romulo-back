<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\NotaFiscal\Models\NotaFiscal;
use Carbon\Carbon;

class SaldoService
{
    /**
     * Validar processo pertence à empresa
     */
    public function validarProcessoEmpresa(Processo $processo, int $empresaId): void
    {
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado ou não pertence à empresa ativa.');
        }
    }

    /**
     * Validar processo está em execução
     */
    public function validarProcessoEmExecucao(Processo $processo): void
    {
        if (!$processo->isEmExecucao()) {
            throw new \Exception('Apenas processos em execução possuem saldo.');
        }
    }

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

    /**
     * Calcula comparativo de custos: Pré-Certame (inicial) vs Pós-Negociação (real)
     * 
     * Custo Inicial: Baseado nos orçamentos escolhidos durante a fase de participação
     * Custo Real: Baseado nas notas fiscais de entrada registradas durante a execução
     */
    public function calcularComparativoCustos(Processo $processo): array
    {
        // Carregar itens com orçamentos escolhidos
        $itens = $processo->itens()
            ->with(['orcamentoEscolhido', 'orcamentoEscolhido.formacaoPreco'])
            ->get();

        // Calcular custo inicial (pré-certame) - baseado nos orçamentos escolhidos
        $custoInicialTotal = 0;
        $itensDetalhados = [];

        foreach ($itens as $item) {
            $orcamento = $item->orcamentoEscolhido;
            $custoInicialItem = 0;

            if ($orcamento) {
                $custoProduto = $orcamento->custo_produto ?? 0;
                $frete = ($orcamento->frete_incluido ?? false) ? 0 : ($orcamento->frete ?? 0);
                $custoInicialItem = ($custoProduto + $frete) * ($item->quantidade ?? 0);
            }

            $custoInicialTotal += $custoInicialItem;

            // Buscar custo real do item (notas fiscais de entrada vinculadas ao item)
            $notasEntradaItem = NotaFiscal::where('processo_id', $processo->id)
                ->where('tipo', 'entrada')
                ->where('processo_item_id', $item->id)
                ->get();

            $custoRealItem = $notasEntradaItem->sum(function ($nf) {
                return $nf->custo_total ?? $nf->custo_produto ?? 0;
            });

            $diferencaItem = $custoRealItem - $custoInicialItem;
            $variacaoItem = $custoInicialItem > 0 
                ? ($diferencaItem / $custoInicialItem) * 100 
                : 0;

            $itensDetalhados[] = [
                'item_id' => $item->id,
                'numero_item' => $item->numero_item,
                'especificacao_tecnica' => $item->especificacao_tecnica,
                'quantidade' => $item->quantidade,
                'custo_inicial' => round($custoInicialItem, 2),
                'custo_real' => round($custoRealItem, 2),
                'diferenca' => round($diferencaItem, 2),
                'variacao_percentual' => round($variacaoItem, 2),
                'quantidade_notas_entrada' => $notasEntradaItem->count(),
            ];
        }

        // Calcular custo real total (pós-negociação) - baseado nas notas fiscais de entrada
        $notasEntrada = NotaFiscal::where('processo_id', $processo->id)
            ->where('tipo', 'entrada')
            ->get();

        $custoRealTotal = $notasEntrada->sum(function ($nf) {
            return $nf->custo_total ?? $nf->custo_produto ?? 0;
        });

        $diferencaTotal = $custoRealTotal - $custoInicialTotal;
        $variacaoPercentual = $custoInicialTotal > 0 
            ? ($diferencaTotal / $custoInicialTotal) * 100 
            : 0;

        return [
            'resumo' => [
                'custo_inicial' => round($custoInicialTotal, 2),
                'custo_real' => round($custoRealTotal, 2),
                'diferenca' => round($diferencaTotal, 2),
                'variacao_percentual' => round($variacaoPercentual, 2),
                'quantidade_notas_entrada' => $notasEntrada->count(),
            ],
            'itens' => $itensDetalhados,
        ];
    }

    /**
     * Recalcula valores financeiros de todos os itens do processo
     * Útil quando o processo entra em execução ou quando há necessidade de atualizar valores
     */
    public function recalcularValoresFinanceirosItens(Processo $processo): array
    {
        $itens = $processo->itens()
            ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
            ->get();

        $itensAtualizados = [];
        foreach ($itens as $item) {
            $item->atualizarValoresFinanceiros();
            $itensAtualizados[] = [
                'item_id' => $item->id,
                'numero_item' => $item->numero_item,
                'valor_vencido' => $item->valor_vencido,
                'valor_empenhado' => $item->valor_empenhado,
                'valor_faturado' => $item->valor_faturado,
                'valor_pago' => $item->valor_pago,
                'saldo_aberto' => $item->saldo_aberto,
                'lucro_bruto' => $item->lucro_bruto,
                'lucro_liquido' => $item->lucro_liquido,
            ];
        }

        return [
            'processo_id' => $processo->id,
            'itens_atualizados' => count($itensAtualizados),
            'itens' => $itensAtualizados,
        ];
    }
}





