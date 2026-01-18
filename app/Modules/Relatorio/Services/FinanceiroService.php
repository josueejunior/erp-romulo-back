<?php

namespace App\Modules\Relatorio\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Custo\Models\CustoIndireto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;

class FinanceiroService
{
    public function __construct(
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
        private ProcessoItemRepositoryInterface $processoItemRepository,
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
        private ProcessoRepositoryInterface $processoRepository,
    ) {}
    /**
     * Calcula os custos diretos de um processo
     * (custos de produtos, fretes, impostos das notas fiscais de entrada)
     */
    public function calcularCustosDiretos(Processo $processo): array
    {
        // Usar repository em vez de acessar relacionamento diretamente
        $notasEntrada = $this->notaFiscalRepository->buscarPorProcesso($processo->id, [
            'tipo' => 'entrada',
            'empresa_id' => $processo->empresa_id,
        ]);

        $custoProduto = collect($notasEntrada)->sum(fn($nf) => $nf->custoProduto ?? 0);
        $custoFrete = collect($notasEntrada)->sum(fn($nf) => $nf->custoFrete ?? 0);
        $custoTotal = collect($notasEntrada)->sum(fn($nf) => $nf->custoTotal ?? 0);

        return [
            'custo_produto' => round($custoProduto, 2),
            'custo_frete' => round($custoFrete, 2),
            'custo_total' => round($custoTotal, 2),
            'quantidade_notas' => count($notasEntrada),
        ];
    }

    /**
     * Calcula a receita de um processo
     * (valores dos itens vencidos/arrematados)
     */
    public function calcularReceita(Processo $processo): array
    {
        // Usar repository para buscar itens aceitos
        $itensPorProcesso = $this->processoItemRepository->buscarPorProcesso($processo->id);
        $itensVencidos = collect($itensPorProcesso)->filter(function ($item) {
            return in_array($item->statusItem, ['aceito', 'aceito_habilitado']);
        });

        $receitaEstimada = $itensVencidos->sum(fn($item) => ($item->valorEstimado ?? 0) * ($item->quantidade ?? 1));
        $receitaFinal = $itensVencidos->sum(fn($item) => ($item->valorFinalSessao ?? 0) * ($item->quantidade ?? 1));
        $receitaArrematada = $itensVencidos->sum(fn($item) => ($item->valorArrematado ?? 0) * ($item->quantidade ?? 1));
        $receitaNegociada = $itensVencidos->sum(fn($item) => ($item->valorNegociado ?? 0) * ($item->quantidade ?? 1));

        // Prioridade: valor arrematada > valor negociada > valor final sessão > valor estimada
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
    public function calcularCustosIndiretosPeriodo(Carbon $dataInicio, Carbon $dataFim, ?int $empresaId = null): array
    {
        // Usar repository para buscar custos indiretos
        $filtros = [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];
        
        if ($empresaId) {
            $filtros['empresa_id'] = $empresaId;
        }
        
        $paginator = $this->custoIndiretoRepository->buscarComFiltros($filtros);
        $custos = $paginator->getCollection();

        $total = $custos->sum(fn($custo) => $custo->valor ?? 0);

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
    public function calcularLucroPeriodo(Carbon $dataInicio, Carbon $dataFim, ?int $empresaId = null): array
    {
        // Usar repository para buscar processos encerrados
        $filtros = [
            'data_recebimento_pagamento_inicio' => $dataInicio,
            'data_recebimento_pagamento_fim' => $dataFim,
            'tem_item_aceito' => true, // Apenas processos com itens aceitos
        ];
        
        if ($empresaId) {
            $filtros['empresa_id'] = $empresaId;
        }
        
        $processos = $this->processoRepository->buscarModelosComFiltros($filtros, ['itens']);

        $receitaTotal = 0;
        $custosDiretosTotal = 0;

        foreach ($processos as $processo) {
            $receita = $this->calcularReceita($processo);
            $custos = $this->calcularCustosDiretos($processo);
            
            $receitaTotal += $receita['receita_total'];
            $custosDiretosTotal += $custos['custo_total'];
        }

        $custosIndiretos = $this->calcularCustosIndiretosPeriodo($dataInicio, $dataFim, $empresaId);
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

        // Valor já pago (notas fiscais de saída pagas) - usar repository
        $notasSaidaPagas = $this->notaFiscalRepository->buscarPorProcesso($processo->id, [
            'tipo' => 'saida',
            'situacao' => 'paga',
            'empresa_id' => $processo->empresa_id,
        ]);
        
        $valorPago = collect($notasSaidaPagas)->sum(fn($nf) => $nf->valor ?? 0);

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
    public function calcularGestaoFinanceiraMensal(?Carbon $mes = null, ?int $empresaId = null): array
    {
        if (!$mes) {
            $mes = Carbon::now();
        }

        $dataInicio = $mes->copy()->startOfMonth();
        $dataFim = $mes->copy()->endOfMonth();

        // Usar repository para buscar processos encerrados no mês
        $filtros = [
            'data_recebimento_pagamento_inicio' => $dataInicio,
            'data_recebimento_pagamento_fim' => $dataFim,
        ];
        
        if ($empresaId) {
            $filtros['empresa_id'] = $empresaId;
        }
        
        $processosEncerrados = $this->processoRepository->buscarModelosComFiltros($filtros, ['itens', 'notasFiscais']);

        $receitaTotal = 0;
        $custosDiretosTotal = 0;
        $processosDetalhados = [];

        foreach ($processosEncerrados as $processo) {
            // Receita: notas fiscais de saída (vendas) - usar repository
            $notasSaida = $this->notaFiscalRepository->buscarPorProcesso($processo->id, [
                'tipo' => 'saida',
                'empresa_id' => $processo->empresa_id,
            ]);
            $receita = collect($notasSaida)->sum(fn($nf) => $nf->valor ?? 0);

            // Se não tiver NF de saída, usar valores dos itens
            if ($receita == 0) {
                $receita = $this->calcularReceita($processo)['receita_total'];
            }

            // Custos diretos: notas fiscais de entrada (compras) - usar repository
            $notasEntrada = $this->notaFiscalRepository->buscarPorProcesso($processo->id, [
                'tipo' => 'entrada',
                'empresa_id' => $processo->empresa_id,
            ]);
            $custosDiretos = collect($notasEntrada)->sum(fn($nf) => $nf->custoTotal ?? 0);

            // Se não tiver NF de entrada, usar custo_produto
            if ($custosDiretos == 0) {
                $custosDiretos = collect($notasEntrada)->sum(fn($nf) => $nf->custoProduto ?? 0);
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
        $custosIndiretos = $this->calcularCustosIndiretosPeriodo($dataInicio, $dataFim, $empresaId);
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

