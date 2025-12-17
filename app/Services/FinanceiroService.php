<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\CustoIndireto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceiroService
{
    /**
     * Calcula os custos diretos de um processo
     * (custos de produtos, fretes, impostos das notas fiscais de entrada)
     */
    public function calcularCustosDiretos(Processo $processo): array
    {
        $notasEntrada = $processo->notasFiscais()
            ->where('tipo', 'entrada')
            ->get();

        $custoProduto = $notasEntrada->sum('custo_produto') ?? 0;
        $custoFrete = $notasEntrada->sum('custo_frete') ?? 0;
        $custoTotal = $notasEntrada->sum('custo_total') ?? 0;

        return [
            'custo_produto' => round($custoProduto, 2),
            'custo_frete' => round($custoFrete, 2),
            'custo_total' => round($custoTotal, 2),
            'quantidade_notas' => $notasEntrada->count(),
        ];
    }

    /**
     * Calcula a receita de um processo
     * (valores dos itens vencidos/arrematados)
     */
    public function calcularReceita(Processo $processo): array
    {
        $itensVencidos = $processo->itens()
            ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
            ->get();

        $receitaEstimada = $itensVencidos->sum('valor_estimado') ?? 0;
        $receitaFinal = $itensVencidos->sum('valor_final_sessao') ?? 0;
        $receitaArrematada = $itensVencidos->sum('valor_arrematado') ?? 0;
        $receitaNegociada = $itensVencidos->sum('valor_negociado') ?? 0;

        // Prioridade: valor arrematado > valor negociado > valor final sessão > valor estimado
        $receitaTotal = $receitaArrematada > 0 
            ? $receitaArrematada 
            : ($receitaNegociada > 0 
                ? $receitaNegociada 
                : ($receitaFinal > 0 ? $receitaFinal : $receitaEstimada));

        return [
            'receita_estimada' => round($receitaEstimada, 2),
            'receita_final' => round($receitaFinal, 2),
            'receita_arrematada' => round($receitaArrematada, 2),
            'receita_negociada' => round($receitaNegociada, 2),
            'receita_total' => round($receitaTotal, 2),
            'quantidade_itens' => $itensVencidos->count(),
        ];
    }

    /**
     * Calcula o lucro de um processo (receita - custos diretos)
     */
    public function calcularLucro(Processo $processo): array
    {
        $custosDiretos = $this->calcularCustosDiretos($processo);
        $receita = $this->calcularReceita($processo);

        $lucroBruto = $receita['receita_total'] - $custosDiretos['custo_total'];
        $margemLucro = $receita['receita_total'] > 0 
            ? ($lucroBruto / $receita['receita_total']) * 100 
            : 0;

        return [
            'receita_total' => $receita['receita_total'],
            'custos_diretos' => $custosDiretos['custo_total'],
            'lucro_bruto' => round($lucroBruto, 2),
            'margem_lucro' => round($margemLucro, 2),
        ];
    }

    /**
     * Calcula custos indiretos em um período
     */
    public function calcularCustosIndiretosPeriodo(Carbon $dataInicio, Carbon $dataFim): array
    {
        $custos = CustoIndireto::whereBetween('data', [$dataInicio, $dataFim])
            ->get();

        $total = $custos->sum('valor') ?? 0;

        return [
            'total' => round($total, 2),
            'quantidade' => $custos->count(),
            'custos' => $custos,
        ];
    }

    /**
     * Calcula lucro por período (incluindo custos indiretos)
     * Considera apenas processos encerrados (com data_recebimento_pagamento)
     */
    public function calcularLucroPeriodo(Carbon $dataInicio, Carbon $dataFim): array
    {
        // Processos encerrados (com data de recebimento do pagamento) no período
        $processos = Processo::whereNotNull('data_recebimento_pagamento')
            ->whereBetween('data_recebimento_pagamento', [$dataInicio, $dataFim])
            ->whereHas('itens', function ($query) {
                $query->whereIn('status_item', ['aceito', 'aceito_habilitado']);
            })
            ->get();

        $receitaTotal = 0;
        $custosDiretosTotal = 0;

        foreach ($processos as $processo) {
            $receita = $this->calcularReceita($processo);
            $custos = $this->calcularCustosDiretos($processo);
            
            $receitaTotal += $receita['receita_total'];
            $custosDiretosTotal += $custos['custo_total'];
        }

        $custosIndiretos = $this->calcularCustosIndiretosPeriodo($dataInicio, $dataFim);
        $custosIndiretosTotal = $custosIndiretos['total'];

        $lucroBruto = $receitaTotal - $custosDiretosTotal;
        $lucroLiquido = $lucroBruto - $custosIndiretosTotal;

        $margemBruta = $receitaTotal > 0 
            ? ($lucroBruto / $receitaTotal) * 100 
            : 0;
        
        $margemLiquida = $receitaTotal > 0 
            ? ($lucroLiquido / $receitaTotal) * 100 
            : 0;

        return [
            'periodo' => [
                'inicio' => $dataInicio->format('d/m/Y'),
                'fim' => $dataFim->format('d/m/Y'),
            ],
            'receita_total' => round($receitaTotal, 2),
            'custos_diretos' => round($custosDiretosTotal, 2),
            'custos_indiretos' => round($custosIndiretosTotal, 2),
            'lucro_bruto' => round($lucroBruto, 2),
            'lucro_liquido' => round($lucroLiquido, 2),
            'margem_bruta' => round($margemBruta, 2),
            'margem_liquida' => round($margemLiquida, 2),
            'quantidade_processos' => $processos->count(),
        ];
    }

    /**
     * Calcula saldo pendente de um processo
     * (valor vencido - valor já pago)
     */
    public function calcularSaldoPendente(Processo $processo): array
    {
        $receita = $this->calcularReceita($processo);
        $receitaTotal = $receita['receita_total'];

        // Valor já pago (notas fiscais de saída pagas)
        $valorPago = $processo->notasFiscais()
            ->where('tipo', 'saida')
            ->where('situacao', 'paga')
            ->sum('valor') ?? 0;

        $saldoPendente = $receitaTotal - $valorPago;

        return [
            'receita_total' => round($receitaTotal, 2),
            'valor_pago' => round($valorPago, 2),
            'saldo_pendente' => round($saldoPendente, 2),
            'percentual_pago' => $receitaTotal > 0 
                ? round(($valorPago / $receitaTotal) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Calcula gestão financeira mensal automática
     * Considera apenas processos encerrados (com data_recebimento_pagamento)
     * Cruza custos diretos (NFs entrada) vs vendas (NFs saída) e desconta custos indiretos
     */
    public function calcularGestaoFinanceiraMensal(?Carbon $mes = null): array
    {
        if (!$mes) {
            $mes = Carbon::now();
        }

        $dataInicio = $mes->copy()->startOfMonth();
        $dataFim = $mes->copy()->endOfMonth();

        // Processos encerrados no mês (com data de recebimento do pagamento)
        $processosEncerrados = Processo::whereNotNull('data_recebimento_pagamento')
            ->whereBetween('data_recebimento_pagamento', [$dataInicio, $dataFim])
            ->with(['itens', 'notasFiscais'])
            ->get();

        $receitaTotal = 0;
        $custosDiretosTotal = 0;
        $processosDetalhados = [];

        foreach ($processosEncerrados as $processo) {
            // Receita: notas fiscais de saída (vendas)
            $receita = $processo->notasFiscais()
                ->where('tipo', 'saida')
                ->sum('valor') ?? 0;

            // Se não tiver NF de saída, usar valores dos itens
            if ($receita == 0) {
                $receita = $this->calcularReceita($processo)['receita_total'];
            }

            // Custos diretos: notas fiscais de entrada (compras)
            $custosDiretos = $processo->notasFiscais()
                ->where('tipo', 'entrada')
                ->sum('custo_total') ?? 0;

            // Se não tiver NF de entrada, usar custo_produto
            if ($custosDiretos == 0) {
                $custosDiretos = $processo->notasFiscais()
                    ->where('tipo', 'entrada')
                    ->sum('custo_produto') ?? 0;
            }

            $lucroBruto = $receita - $custosDiretos;
            $margem = $receita > 0 ? ($lucroBruto / $receita) * 100 : 0;

            $receitaTotal += $receita;
            $custosDiretosTotal += $custosDiretos;

            $processosDetalhados[] = [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numero_modalidade,
                'objeto_resumido' => $processo->objeto_resumido,
                'data_recebimento' => $processo->data_recebimento_pagamento?->format('d/m/Y'),
                'receita' => round($receita, 2),
                'custos_diretos' => round($custosDiretos, 2),
                'lucro_bruto' => round($lucroBruto, 2),
                'margem' => round($margem, 2),
            ];
        }

        // Custos indiretos do mês
        $custosIndiretos = $this->calcularCustosIndiretosPeriodo($dataInicio, $dataFim);
        $custosIndiretosTotal = $custosIndiretos['total'];

        // Cálculo final
        $lucroBruto = $receitaTotal - $custosDiretosTotal;
        $lucroLiquido = $lucroBruto - $custosIndiretosTotal;

        $margemBruta = $receitaTotal > 0 
            ? ($lucroBruto / $receitaTotal) * 100 
            : 0;
        
        $margemLiquida = $receitaTotal > 0 
            ? ($lucroLiquido / $receitaTotal) * 100 
            : 0;

        return [
            'mes' => $mes->format('m/Y'),
            'periodo' => [
                'inicio' => $dataInicio->format('d/m/Y'),
                'fim' => $dataFim->format('d/m/Y'),
            ],
            'resumo' => [
                'receita_total' => round($receitaTotal, 2),
                'custos_diretos' => round($custosDiretosTotal, 2),
                'custos_indiretos' => round($custosIndiretosTotal, 2),
                'lucro_bruto' => round($lucroBruto, 2),
                'lucro_liquido' => round($lucroLiquido, 2),
                'margem_bruta' => round($margemBruta, 2),
                'margem_liquida' => round($margemLiquida, 2),
            ],
            'quantidade_processos' => $processosEncerrados->count(),
            'processos' => $processosDetalhados,
            'custos_indiretos_detalhados' => $custosIndiretos['custos']->map(function($custo) {
                return [
                    'id' => $custo->id,
                    'descricao' => $custo->descricao,
                    'valor' => round($custo->valor, 2),
                    'data' => $custo->data->format('d/m/Y'),
                ];
            }),
        ];
    }
}
