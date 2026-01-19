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
        // Buscar itens do processo que foram aceitos
        $itens = $this->processoItemRepository->buscarPorProcesso($processo->id);
        $itensAceitos = collect($itens)->filter(function ($item) {
            return in_array($item->status_item, ['aceito', 'aceito_habilitado']);
        });

        // Valores baseados nos novos campos (atualizados via ProcessoItemVinculoService)
        $valorArrematado = $itensAceitos->sum('valor_arrematado');
        $valorEmpenhado = $itensAceitos->sum('valor_empenhado');
        $valorFaturado = $itensAceitos->sum('valor_faturado');
        $valorPago = $itensAceitos->sum('valor_pago');

        // Receita Total (pode variar dependendo do que o usuário considera "Receita")
        // No dashboard geralmente usamos o Arrematado como "Potencial" e Faturado como "Realizado"
        $receitaTotal = $valorArrematado; 

        return [
            'arrematado' => round($valorArrematado, 2),
            'empenhado' => round($valorEmpenhado, 2),
            'faturado' => round($valorFaturado, 2),
            'pago' => round($valorPago, 2),
            'receita_total' => round($receitaTotal, 2),
            'quantidade_itens' => $itensAceitos->count(),
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

        // 1. Receita (NFs de Saída no período)
        $notasSaida = $this->notaFiscalRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'tipo' => 'saida',
            'data_emissao_inicio' => $dataInicio,
            'data_emissao_fim' => $dataFim,
        ]);

        $receitaTotal = collect($notasSaida->items())->sum('valor');

        // 2. Custos Diretos (NFs de Entrada no período)
        $notasEntrada = $this->notaFiscalRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'tipo' => 'entrada',
            'data_emissao_inicio' => $dataInicio,
            'data_emissao_fim' => $dataFim,
        ]);

        $custosDiretosTotal = collect($notasEntrada->items())->sum(function($nf) {
            // Prioridade: custoTotal > (custoProduto + custoFrete) > valor da NF
            if ($nf->custoTotal > 0) {
                return $nf->custoTotal;
            }
            $custosDetalhados = ($nf->custoProduto ?? 0) + ($nf->custoFrete ?? 0);
            if ($custosDetalhados > 0) {
                return $custosDetalhados;
            }
            // Fallback: usar o valor da NF de entrada como custo direto
            return $nf->valor ?? 0;
        });

        // 3. Custos Indiretos do mês
        $custosIndiretos = $this->calcularCustosIndiretosPeriodo($dataInicio, $dataFim, $empresaId);
        $custosIndiretosTotal = $custosIndiretos['total'];

        // 4. Detalhar processos que tiveram movimento no período
        $processosIds = collect($notasSaida->items())->pluck('processoId')
            ->merge(collect($notasEntrada->items())->pluck('processoId'))
            ->unique()
            ->filter()
            ->toArray();

        $processosDetalhados = [];
        if (!empty($processosIds)) {
            $processos = \App\Modules\Processo\Models\Processo::whereIn('id', $processosIds)->get();
            foreach ($processos as $processo) {
                // Receita do processo no mês
                $recProc = collect($notasSaida->items())->where('processoId', $processo->id)->sum('valor');
                // Custo do processo no mês
                $custoProc = collect($notasEntrada->items())->where('processoId', $processo->id)->sum(function($nf) {
                    if ($nf->custoTotal > 0) {
                        return $nf->custoTotal;
                    }
                    $custosDetalhados = ($nf->custoProduto ?? 0) + ($nf->custoFrete ?? 0);
                    if ($custosDetalhados > 0) {
                        return $custosDetalhados;
                    }
                    return $nf->valor ?? 0;
                });

                $processosDetalhados[] = [
                    'id' => $processo->id,
                    'numero_modalidade' => $processo->numero_modalidade,
                    'objeto_resumido' => $processo->objeto_resumido,
                    'receita' => round($recProc, 2),
                    'custos_diretos' => round($custoProc, 2),
                    'lucro_bruto' => round($recProc - $custoProc, 2),
                    'margem' => $recProc > 0 ? round((($recProc - $custoProc) / $recProc) * 100, 2) : 0,
                ];
            }
        }

        // Cálculo final consolidado
        $lucroBruto = $receitaTotal - $custosDiretosTotal;
        $lucroLiquido = $lucroBruto - $custosIndiretosTotal;

        $margemBruta = $receitaTotal > 0 ? ($lucroBruto / $receitaTotal) * 100 : 0;
        $margemLiquida = $receitaTotal > 0 ? ($lucroLiquido / $receitaTotal) * 100 : 0;

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
            'quantidade_processos' => count($processosDetalhados),
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

