<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Orcamento\Domain\Services\RelatorioDomainService;
use App\Modules\Orcamento\Domain\ValueObjects\FiltrosRelatorio;
use App\Application\Orcamento\Exporters\RelatorioExporterInterface;
use App\Application\Orcamento\Exporters\RelatorioCsvExporter;
use App\Http\Requests\Orcamento\RelatorioOrcamentoRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para relatórios de orçamentos
 * 
 * ✅ DDD Enterprise-Grade:
 * - Middleware valida assinatura (não no controller)
 * - FormRequest valida filtros
 * - Domain Service retorna Read Model (não array)
 * - Export Service formata dados (não no controller)
 * - Repository separa queries de regras
 */
class RelatorioController extends Controller
{
    use HasAuthContext;

    public function __construct(
        private RelatorioDomainService $relatorioService,
        RelatorioExporterInterface $exporter = null,
    ) {
        // Por padrão usa CSV, mas pode ser injetado outro exportador
        $this->exporter = $exporter ?? app(RelatorioCsvExporter::class);
    }
    
    private RelatorioExporterInterface $exporter;

    /**
     * GET /relatorios/orcamentos
     * Retorna lista de orçamentos com filtros para relatórios
     * 
     * ✅ DDD: Controller apenas orquestra
     * - Middleware valida assinatura (não aqui)
     * - FormRequest valida filtros
     * - Domain Service retorna Read Model
     */
    public function index(RelatorioOrcamentoRequest $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // FormRequest já validou os dados
            $filtros = FiltrosRelatorio::fromArray($request->validated());

            // Domain Service retorna Read Model (não array)
            $relatorio = $this->relatorioService->relatorioOrcamentosPorPeriodo(
                $empresa->id,
                $filtros
            );

            // Controller decide como serializar
            return response()->json([
                'success' => true,
                'data' => $relatorio->dados->toArray(),
                'resumo' => [
                    'total_registros' => $relatorio->totalRegistros,
                    'valor_total' => $relatorio->valorTotal,
                    'valor_medio' => $relatorio->valorMedio,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao gerar relatório de orçamentos: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Erro ao gerar relatório: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /relatorios/orcamentos/export
     * Exporta relatório em formato específico
     * 
     * ✅ DDD: Controller apenas decide qual exportador usar
     * Export Service formata dados (não no controller)
     */
    public function export(RelatorioOrcamentoRequest $request): mixed
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $formato = $request->get('formato', 'csv');
            
            // FormRequest já validou os dados
            $filtros = FiltrosRelatorio::fromArray($request->validated());

            // Domain Service retorna Read Model
            $relatorio = $this->relatorioService->relatorioOrcamentosPorPeriodo(
                $empresa->id,
                $filtros
            );

            // Controller decide formato, Export Service formata
            if ($formato === 'json') {
                return response()->json($relatorio->toArray());
            }

            // Export Service formata (CSV, Excel, PDF, etc.)
            return $this->exporter->export($relatorio);
        } catch (\Exception $e) {
            \Log::error('Erro ao exportar relatório: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Erro ao exportar relatório: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /relatorios/orcamentos/por-fornecedor
     * Relatório agrupado por fornecedor
     */
    public function porFornecedor(RelatorioOrcamentoRequest $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // FormRequest já validou os dados
            $filtros = FiltrosRelatorio::fromArray($request->validated());

            // Domain Service retorna Read Model
            $relatorio = $this->relatorioService->relatorioPorFornecedor(
                $empresa->id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $relatorio->dados->toArray(),
                'resumo' => $relatorio->resumo,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao gerar relatório por fornecedor: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erro ao gerar relatório: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /relatorios/orcamentos/por-status
     * Relatório agrupado por status
     */
    public function porStatus(RelatorioOrcamentoRequest $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // FormRequest já validou os dados
            $filtros = FiltrosRelatorio::fromArray($request->validated());

            // Domain Service retorna Read Model
            $relatorio = $this->relatorioService->relatorioPorStatus(
                $empresa->id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $relatorio->dados->toArray(),
                'resumo' => $relatorio->resumo,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao gerar relatório por status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erro ao gerar relatório: ' . $e->getMessage()
            ], 500);
        }
    }
}
