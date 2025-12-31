<?php

namespace App\Modules\Orcamento\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Orcamento\Domain\Services\RelatorioDomainService;
use App\Modules\Orcamento\Domain\ValueObjects\FiltrosRelatorio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RelatorioController extends Controller
{
    use HasAuthContext;

    private RelatorioDomainService $relatorioService;

    public function __construct(RelatorioDomainService $relatorioService)
    {
        $this->relatorioService = $relatorioService;
    }

    /**
     * GET /relatorios/orcamentos
     * Retorna lista de orçamentos com filtros para relatórios
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $filtros = FiltrosRelatorio::fromArray([
                'data_inicio' => $request->get('data_inicio'),
                'data_fim' => $request->get('data_fim'),
                'status' => $request->get('status'),
                'fornecedor_id' => $request->get('fornecedor'),
                'processo_id' => $request->get('processo'),
            ]);

            $relatorio = $this->relatorioService->relatorioOrcamentosPorPeriodo(
                $empresa->id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $relatorio['dados'] ?? [],
                'resumo' => [
                    'total_registros' => $relatorio['total_registros'] ?? 0,
                    'valor_total' => $relatorio['valor_total'] ?? 0,
                    'valor_medio' => $relatorio['valor_medio'] ?? 0,
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
     */
    public function export(Request $request): mixed
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $formato = $request->get('formato', 'csv');
            
            $filtros = FiltrosRelatorio::fromArray([
                'data_inicio' => $request->get('data_inicio'),
                'data_fim' => $request->get('data_fim'),
                'status' => $request->get('status'),
                'fornecedor_id' => $request->get('fornecedor'),
                'processo_id' => $request->get('processo'),
            ]);

            $relatorio = $this->relatorioService->relatorioOrcamentosPorPeriodo(
                $empresa->id,
                $filtros
            );

            if ($formato === 'json') {
                return response()->json($relatorio);
            }

            // Exportar CSV
            return $this->exportarCSV($relatorio['dados'] ?? []);
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
    public function porFornecedor(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $filtros = FiltrosRelatorio::fromArray([
                'data_inicio' => $request->get('data_inicio'),
                'data_fim' => $request->get('data_fim'),
                'status' => $request->get('status'),
            ]);

            $relatorio = $this->relatorioService->relatorioPorFornecedor(
                $empresa->id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $relatorio,
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
    public function porStatus(Request $request): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $filtros = FiltrosRelatorio::fromArray([
                'data_inicio' => $request->get('data_inicio'),
                'data_fim' => $request->get('data_fim'),
            ]);

            $relatorio = $this->relatorioService->relatorioPorStatus(
                $empresa->id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $relatorio,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao gerar relatório por status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erro ao gerar relatório: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar relatório em CSV
     */
    private function exportarCSV(array $dados): mixed
    {
        $filename = 'relatorio_orcamentos_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($dados) {
            $file = fopen('php://output', 'w');
            
            // Adicionar BOM UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalhos
            fputcsv($file, [
                'ID',
                'Data',
                'Fornecedor',
                'Processo',
                'Valor Total',
                'Status',
                'Total de Itens',
            ], ';');

            // Dados
            foreach ($dados as $item) {
                fputcsv($file, [
                    $item['id'] ?? '',
                    $item['data'] ?? '',
                    $item['fornecedor'] ?? '',
                    $item['processo'] ?? '',
                    number_format($item['valor_total'] ?? 0, 2, ',', '.'),
                    $item['status'] ?? '',
                    $item['total_itens'] ?? 0,
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
