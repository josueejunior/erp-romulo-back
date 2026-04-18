<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Orcamento\Domain\Services\DashboardDomainService;
use App\Modules\Orcamento\UseCases\Dashboard\ObtenerAnalisePrecoAction;
use App\Modules\Orcamento\UseCases\Dashboard\ObtenerDashboardCompletoAction;
use App\Modules\Orcamento\UseCases\Dashboard\ObtenerMetricasDashboardAction;
use App\Modules\Orcamento\UseCases\Dashboard\ObtenerPerformanceFornecedoresAction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use HasAuthContext;

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
     * Dashboard completo
     */
    public function index(Request $request)
    {
        return $this->dashboardCompletoAction->execute($this->getEmpresaId());
    }

    /**
     * GET /dashboard/orcamentos/metricas
     */
    public function metricas(Request $request)
    {
        return $this->metricasAction->execute($this->getEmpresaId());
    }

    /**
     * GET /dashboard/orcamentos/analise-precos
     */
    public function analisePrecos(Request $request)
    {
        return $this->analisePrecoAction->execute($this->getEmpresaId());
    }

    /**
     * GET /dashboard/orcamentos/performance-fornecedores
     */
    public function performanceFornecedores(Request $request)
    {
        return $this->performanceAction->execute($this->getEmpresaId());
    }

    /**
     * GET /dashboard/orcamentos/resumo-status
     */
    public function resumoStatus(Request $request)
    {
        $empresaId = $this->getEmpresaId();
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
        $empresaId = $this->getEmpresaId();
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
        $empresaId = $this->getEmpresaId();
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
        $empresaId = $this->getEmpresaId();
        $meses = $request->query('meses', 12);
        $comparacao = $this->dashboardService->obterComparacaoPeriodos($empresaId, $meses);

        return response()->json([
            'success' => true,
            'data' => $comparacao
        ]);
    }
}
