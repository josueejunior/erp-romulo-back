<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orcamento\Models\Orcamento;
use App\Modules\Orcamento\Models\OrcamentoItem;
use App\Modules\Processo\Models\Processo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
        use App\Modules\Orcamento\Domain\Services\DashboardDomainService;
        use App\Modules\Orcamento\UseCases\Dashboard\ObtenerMetricasDashboardAction;
        use App\Modules\Orcamento\UseCases\Dashboard\ObtenerAnalisePrecoAction;
        use App\Modules\Orcamento\UseCases\Dashboard\ObtenerPerformanceFornecedoresAction;
        use App\Modules\Orcamento\UseCases\Dashboard\ObtenerDashboardCompletoAction;

        private ObtenerMetricasDashboardAction $metricasAction;
        private ObtenerAnalisePrecoAction $analisePrecoAction;
        private ObtenerPerformanceFornecedoresAction $performanceAction;
        private ObtenerDashboardCompletoAction $dashboardCompletoAction;
        private DashboardDomainService $dashboardService;

        public function __construct(
            ObtenerMetricasDashboardAction $metricasAction,
            ObtenerAnalisePrecoAction $analisePrecoAction,
            ObtenerPerformanceFornecedoresAction $performanceAction,
            ObtenerDashboardCompletoAction $dashboardCompletoAction,
            DashboardDomainService $dashboardService
        ) {
            $this->metricasAction = $metricasAction;
            $this->analisePrecoAction = $analisePrecoAction;
            $this->performanceAction = $performanceAction;
            $this->dashboardCompletoAction = $dashboardCompletoAction;
            $this->dashboardService = $dashboardService;
        }

        /**
         * GET /dashboard/orcamentos
         * Obter dashboard completo
         */
        public function index(Request $request)
        {
            return $this->dashboardCompletoAction->execute(auth()->user()->empresa_id);
        }

        /**
         * GET /dashboard/orcamentos/metricas
         */
        public function metricas(Request $request)
        {
            return $this->metricasAction->execute(auth()->user()->empresa_id);
        }

        /**
         * GET /dashboard/orcamentos/analise-precos
         */
        public function analisePrecos(Request $request)
        {
            return $this->analisePrecoAction->execute(auth()->user()->empresa_id);
        }

        /**
         * GET /dashboard/orcamentos/performance-fornecedores
         */
        public function performanceFornecedores(Request $request)
        {
            return $this->performanceAction->execute(auth()->user()->empresa_id);
        }

        /**
         * GET /dashboard/orcamentos/resumo-status
         */
        public function resumoStatus(Request $request)
        {
            $empresaId = auth()->user()->empresa_id;
            $resumo = $this->dashboardService->obterResumoStatus($empresaId);

            return response()->json([
                'success' => true,
                'data' => $resumo
            ]);
        }

        /**
         * GET /dashboard/orcamentos/timeline
         */
        public function timeline(Request $request)
        {
            $empresaId = auth()->user()->empresa_id;
            $limit = $request->query('limit', 10);
            $timeline = $this->dashboardService->obterTimeline($empresaId, $limit);

            return response()->json([
                'success' => true,
                'data' => $timeline
            ]);
        }

        /**
         * GET /dashboard/orcamentos/processos-maior-gasto
         */
        public function processosMaiorGasto(Request $request)
        {
            $empresaId = auth()->user()->empresa_id;
            $limit = $request->query('limit', 5);
            $processos = $this->dashboardService->obterProcessosMaiorGasto($empresaId, $limit);

            return response()->json([
                'success' => true,
                'data' => $processos
            ]);
        }

        /**
         * GET /dashboard/orcamentos/comparacao-periodos
         */
        public function comparacaoPeriodos(Request $request)
        {
            $empresaId = auth()->user()->empresa_id;
            $meses = $request->query('meses', 12);
            $comparacao = $this->dashboardService->obterComparacaoPeriodos($empresaId, $meses);

            return response()->json([
                'success' => true,
                'data' => $comparacao
            ]);
        }
    /**
     * Obter métricas gerais do dashboard de orçamentos
     *
     * GET /dashboard/orcamentos/metricas
     */
    public function metricas(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $totalOrcamentos = Orcamento::where('empresa_id', $empresaId)->count();
        $valorTotal = Orcamento::where('empresa_id', $empresaId)->sum('valor_total');
        
        $statusDistribuicao = Orcamento::where('empresa_id', $empresaId)
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(valor_total) as valor'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'total' => $item->total,
                    'valor' => $item->valor ?? 0
                ];
            });

        $processosMaiorGasto = Processo::where('empresa_id', $empresaId)
            ->with(['orcamentos' => function ($query) {
                $query->select('processo_id', DB::raw('sum(valor_total) as total'));
            }])
            ->get()
            ->map(function ($processo) {
                $total = $processo->orcamentos->sum('total') ?? 0;
                return [
                    'id' => $processo->id,
                    'numero' => $processo->numero,
                    'titulo' => $processo->titulo,
                    'valor_total' => $total
                ];
            })
            ->sortByDesc('valor_total')
            ->take(5);

        $fornecedoresMaisCotados = Orcamento::where('empresa_id', $empresaId)
            ->select('fornecedor_id', DB::raw('count(*) as total'), DB::raw('avg(valor_total) as valor_medio'))
            ->with('fornecedor')
            ->groupBy('fornecedor_id')
            ->get()
            ->map(function ($orcamento) {
                return [
                    'fornecedor_id' => $orcamento->fornecedor_id,
                    'fornecedor_nome' => $orcamento->fornecedor?->nome ?? 'N/A',
                    'total_orcamentos' => $orcamento->total,
                    'valor_medio' => round($orcamento->valor_medio ?? 0, 2)
                ];
            })
            ->sortByDesc('total_orcamentos')
            ->take(5);

        return response()->json([
            'total_orcamentos' => $totalOrcamentos,
            'valor_total' => round($valorTotal ?? 0, 2),
            'status_distribuicao' => $statusDistribuicao->values(),
            'processos_maior_gasto' => $processosMaiorGasto->values(),
            'fornecedores_mais_cotados' => $fornecedoresMaisCotados->values(),
        ]);
    }

    /**
     * Obter análise de preços por item
     *
     * GET /dashboard/orcamentos/analise-precos
     */
    public function analisePrecos(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $analise = OrcamentoItem::whereHas('orcamento', function ($query) use ($empresaId) {
            $query->where('empresa_id', $empresaId);
        })
        ->select(
            'processo_item_id',
            DB::raw('min(valor_unitario) as preco_minimo'),
            DB::raw('max(valor_unitario) as preco_maximo'),
            DB::raw('avg(valor_unitario) as preco_medio'),
            DB::raw('count(distinct orcamento_id) as total_cotacoes')
        )
        ->with('processoItem')
        ->groupBy('processo_item_id')
        ->get()
        ->map(function ($item) {
            return [
                'processo_item_id' => $item->processo_item_id,
                'descricao' => $item->processoItem?->descricao ?? 'N/A',
                'preco_minimo' => round($item->preco_minimo, 2),
                'preco_maximo' => round($item->preco_maximo, 2),
                'preco_medio' => round($item->preco_medio, 2),
                'total_cotacoes' => $item->total_cotacoes,
                'variacao_percentual' => $item->preco_minimo > 0 
                    ? round((($item->preco_maximo - $item->preco_minimo) / $item->preco_minimo * 100), 2)
                    : 0
            ];
        });

        return response()->json([
            'analise_precos' => $analise->values(),
            'total_itens_analisados' => $analise->count()
        ]);
    }

    /**
     * Obter performance de fornecedores
     *
     * GET /dashboard/orcamentos/performance-fornecedores
     */
    public function performanceFornecedores(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $performance = Orcamento::where('empresa_id', $empresaId)
            ->select(
                'fornecedor_id',
                DB::raw('count(*) as total_orcamentos'),
                DB::raw('sum(valor_total) as valor_total'),
                DB::raw('avg(valor_total) as valor_medio'),
                DB::raw('count(case when status = "aprovado" then 1 end) as orcamentos_aprovados'),
                DB::raw('count(case when status = "rejeitado" then 1 end) as orcamentos_rejeitados')
            )
            ->with('fornecedor')
            ->groupBy('fornecedor_id')
            ->get()
            ->map(function ($orcamento) {
                $total = $orcamento->total_orcamentos ?? 1;
                return [
                    'fornecedor_id' => $orcamento->fornecedor_id,
                    'fornecedor_nome' => $orcamento->fornecedor?->nome ?? 'N/A',
                    'total_orcamentos' => $orcamento->total_orcamentos,
                    'valor_total' => round($orcamento->valor_total ?? 0, 2),
                    'valor_medio' => round($orcamento->valor_medio ?? 0, 2),
                    'taxa_aprovacao' => round(($orcamento->orcamentos_aprovados / $total * 100), 2),
                    'taxa_rejeicao' => round(($orcamento->orcamentos_rejeitados / $total * 100), 2)
                ];
            })
            ->sortByDesc('total_orcamentos');

        return response()->json([
            'performance_fornecedores' => $performance->values()
        ]);
    }

    /**
     * Obter timeline de orçamentos recentes
     *
     * GET /dashboard/orcamentos/timeline?limit=10
     */
    public function timeline(Request $request)
    {
        $limit = $request->query('limit', 10);
        $empresaId = auth()->user()->empresa_id;

        $timeline = Orcamento::where('empresa_id', $empresaId)
            ->with(['fornecedor', 'processo'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($orcamento) {
                return [
                    'id' => $orcamento->id,
                    'fornecedor' => $orcamento->fornecedor?->nome ?? 'N/A',
                    'processo' => $orcamento->processo?->numero ?? 'N/A',
                    'valor' => round($orcamento->valor_total, 2),
                    'status' => $orcamento->status,
                    'data_criacao' => $orcamento->created_at->format('d/m/Y H:i'),
                    'tipo_evento' => 'Orçamento Criado'
                ];
            });

        return response()->json([
            'timeline' => $timeline->values()
        ]);
    }

    /**
     * Obter resumo de status
     *
     * GET /dashboard/orcamentos/resumo-status
     */
    public function resumoStatus(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $resumo = Orcamento::where('empresa_id', $empresaId)
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(valor_total) as valor'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'total' => $item->total,
                    'valor' => round($item->valor ?? 0, 2),
                    'percentual' => 0 // será calculado no frontend
                ];
            });

        $totalGeral = $resumo->sum('total');
        
        $resumo = $resumo->map(function ($item) use ($totalGeral) {
            $item['percentual'] = $totalGeral > 0 ? round(($item['total'] / $totalGeral * 100), 2) : 0;
            return $item;
        });

        return response()->json([
            'resumo_status' => $resumo->values(),
            'total_geral' => $totalGeral
        ]);
    }

    /**
     * Obter dados para comparação de períodos
     *
     * GET /dashboard/orcamentos/comparacao-periodos?periodo=mes
     */
    public function comparacaoPeriodos(Request $request)
    {
        $periodo = $request->query('periodo', 'mes'); // mes, trimestre, ano
        $empresaId = auth()->user()->empresa_id;

        $query = Orcamento::where('empresa_id', $empresaId)
            ->select(
                DB::raw('DATE_TRUNC(\'month\', created_at) as periodo'),
                DB::raw('count(*) as total'),
                DB::raw('sum(valor_total) as valor')
            )
            ->groupBy('periodo')
            ->orderBy('periodo', 'desc')
            ->limit(12);

        $dados = $query->get()->map(function ($item) {
            return [
                'periodo' => $item->periodo,
                'total' => $item->total,
                'valor' => round($item->valor ?? 0, 2)
            ];
        });

        return response()->json([
            'comparacao_periodos' => $dados->reverse()->values()
        ]);
    }
}
