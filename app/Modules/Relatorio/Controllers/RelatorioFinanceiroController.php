<?php

namespace App\Modules\Relatorio\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Relatorio\UseCases\GerarRelatorioFinanceiroUseCase;
use App\Modules\Relatorio\Services\FinanceiroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;
use Carbon\Carbon;

/**
 * Controller para Relatórios Financeiros
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Use Case para lógica de negócio
 * - Usa Query Object para queries complexas
 * - Cache gerenciado pelo Use Case
 * - Controller fino: apenas recebe request e retorna response
 */
class RelatorioFinanceiroController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private GerarRelatorioFinanceiroUseCase $gerarRelatorioFinanceiroUseCase,
        private FinanceiroService $financeiroService, // Mantido para métodos de exportação que ainda usam
    ) {}

    /**
     * Gera relatório financeiro
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Valida acesso ao relatório (plano e permissões)
     * - Obtém empresa e tenant do contexto
     * - Chama Use Case para gerar relatório
     * - Retorna resposta JSON
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não faz queries diretas
     * - Não gerencia cache (Use Case faz isso)
     * - Não contém lógica de negócio
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validar acesso ao relatório
            $tenant = $this->getTenant();
            if (!$tenant || !$tenant->temAcessoRelatorios()) {
                return response()->json([
                    'message' => 'Os relatórios não estão disponíveis no seu plano. Faça upgrade para o plano Profissional ou superior.',
                ], 403);
            }

            // RBAC: apenas usuários autorizados podem ver relatórios financeiros
            if (!PermissionHelper::canViewFinancialReports()) {
                return response()->json([
                    'message' => 'Você não tem permissão para visualizar relatórios financeiros.',
                ], 403);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            $tenantId = $this->getTenantId();

            // Se for gestão financeira mensal (apenas processos encerrados)
            if ($request->tipo === 'mensal' || $request->mes) {
                $mes = $request->mes 
                    ? Carbon::createFromFormat('Y-m', $request->mes)
                    : Carbon::now();
                
                // Executar Use Case (gerencia cache internamente)
                $resultado = $this->gerarRelatorioFinanceiroUseCase->executarMensal(
                    $empresa->id,
                    $mes,
                    $tenantId
                );
                
                return response()->json($resultado);
            }

            // Relatório padrão (processos em execução)
            // Executar Use Case (gerencia cache internamente)
            $resultado = $this->gerarRelatorioFinanceiroUseCase->executarExecucao(
                $empresa->id,
                $request->data_inicio,
                $request->data_fim,
                $tenantId
            );

            return response()->json($resultado);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao gerar relatório financeiro');
        }
    }

    /**
     * Endpoint específico para gestão financeira mensal
     * 
     * ✅ Refatorado para usar Use Case
     */
    public function gestaoMensal(Request $request): JsonResponse
    {
        try {
            // Validar acesso ao relatório
            $tenant = $this->getTenant();
            if (!$tenant || !$tenant->temAcessoRelatorios()) {
                return response()->json([
                    'message' => 'Os relatórios não estão disponíveis no seu plano. Faça upgrade para o plano Profissional ou superior.',
                ], 403);
            }

            if (!PermissionHelper::canViewFinancialReports()) {
                return response()->json([
                    'message' => 'Você não tem permissão para visualizar relatórios financeiros.',
                ], 403);
            }

            $empresa = $this->getEmpresaAtivaOrFail();
            $tenantId = $this->getTenantId();
            
            $mes = $request->mes 
                ? Carbon::createFromFormat('Y-m', $request->mes)
                : Carbon::now();

            // Executar Use Case (gerencia cache internamente)
            $resultado = $this->gerarRelatorioFinanceiroUseCase->executarMensal(
                $empresa->id,
                $mes,
                $tenantId
            );

            return response()->json($resultado);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao gerar relatório financeiro mensal');
        }
    }

    /**
     * Exporta relatório financeiro em PDF ou CSV
     */
    public function exportar(Request $request)
    {
        if (!PermissionHelper::canViewFinancialReports()) {
            return response()->json([
                'message' => 'Você não tem permissão para exportar relatórios financeiros.',
            ], 403);
        }

        $formato = $request->formato ?? 'pdf';
        $tipo = $request->tipo ?? 'completo';
        $empresa = $this->getEmpresaAtivaOrFail();

        try {
            if ($request->mes || $request->tipo === 'mensal') {
                // Exportar gestão mensal
                $mes = $request->mes 
                    ? Carbon::createFromFormat('Y-m', $request->mes)
                    : Carbon::now();
                
                $data = $this->financeiroService->calcularGestaoFinanceiraMensal($mes, $empresa->id);
                
                if ($formato === 'csv') {
                    return $this->exportarCSVMensal($data, $mes);
                } else {
                    return $this->exportarPDFMensal($data, $mes);
                }
            } else {
                // Exportar processos em execução
                $filters = [
                    'data_inicio' => $request->data_inicio,
                    'data_fim' => $request->data_fim,
                ];
                
                $data = $this->index($request)->getData(true);
                
                if ($formato === 'csv') {
                    return $this->exportarCSVExecucao($data, $filters);
                } else {
                    return $this->exportarPDFExecucao($data, $filters);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao exportar relatório: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporta relatório mensal em CSV
     */
    protected function exportarCSVMensal($data, Carbon $mes): \Illuminate\Http\Response
    {
        $resumo = $data['resumo'] ?? [];
        $processos = $data['processos'] ?? [];
        
        $filename = "relatorio_financeiro_mensal_{$mes->format('Y-m')}.csv";
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($resumo, $processos, $mes) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalho
            fputcsv($file, ['Relatório Financeiro Mensal'], ';');
            fputcsv($file, ["Período: {$mes->format('m/Y')}"], ';');
            fputcsv($file, []); // Linha em branco
            
            // Resumo
            fputcsv($file, ['RESUMO'], ';');
            fputcsv($file, ['Receita Total', 'Custos Diretos', 'Custos Indiretos', 'Lucro Bruto', 'Lucro Líquido', 'Margem Bruta (%)', 'Margem Líquida (%)'], ';');
            fputcsv($file, [
                number_format($resumo['receita_total'] ?? 0, 2, ',', '.'),
                number_format($resumo['custos_diretos'] ?? 0, 2, ',', '.'),
                number_format($resumo['custos_indiretos'] ?? 0, 2, ',', '.'),
                number_format($resumo['lucro_bruto'] ?? 0, 2, ',', '.'),
                number_format($resumo['lucro_liquido'] ?? 0, 2, ',', '.'),
                number_format($resumo['margem_bruta'] ?? 0, 2, ',', '.'),
                number_format($resumo['margem_liquida'] ?? 0, 2, ',', '.'),
            ], ';');
            fputcsv($file, []); // Linha em branco
            
            // Processos
            if (count($processos) > 0) {
                fputcsv($file, ['PROCESSOS ENCERRADOS'], ';');
                fputcsv($file, ['Processo', 'Data Recebimento', 'Receita', 'Custos Diretos', 'Lucro Bruto', 'Margem (%)'], ';');
                foreach ($processos as $processo) {
                    fputcsv($file, [
                        $processo['numero_modalidade'] ?? '',
                        $processo['data_recebimento'] ?? '',
                        number_format($processo['receita'] ?? 0, 2, ',', '.'),
                        number_format($processo['custos_diretos'] ?? 0, 2, ',', '.'),
                        number_format($processo['lucro_bruto'] ?? 0, 2, ',', '.'),
                        number_format($processo['margem'] ?? 0, 2, ',', '.'),
                    ], ';');
                }
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Exporta relatório de execução em CSV
     */
    protected function exportarCSVExecucao($data, array $filters): \Illuminate\Http\Response
    {
        $resumo = $data['resumo'] ?? [];
        $processos = $data['processos'] ?? [];
        
        $filename = "relatorio_financeiro_execucao_" . date('Y-m-d') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($resumo, $processos, $filters) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalho
            fputcsv($file, ['Relatório Financeiro - Processos em Execução'], ';');
            if ($filters['data_inicio'] || $filters['data_fim']) {
                fputcsv($file, [
                    "Período: " . ($filters['data_inicio'] ?? 'Início') . " a " . ($filters['data_fim'] ?? 'Fim')
                ], ';');
            }
            fputcsv($file, []); // Linha em branco
            
            // Resumo
            fputcsv($file, ['RESUMO'], ';');
            fputcsv($file, ['Total a Receber', 'Custos Diretos', 'Custos Indiretos', 'Lucro Bruto', 'Lucro Líquido', 'Margem Bruta (%)', 'Margem Líquida (%)'], ';');
            fputcsv($file, [
                number_format($resumo['total_receber'] ?? 0, 2, ',', '.'),
                number_format($resumo['total_custos_diretos'] ?? 0, 2, ',', '.'),
                number_format($resumo['total_custos_indiretos'] ?? 0, 2, ',', '.'),
                number_format($resumo['lucro_bruto'] ?? 0, 2, ',', '.'),
                number_format($resumo['lucro_liquido'] ?? 0, 2, ',', '.'),
                number_format($resumo['margem_bruta'] ?? 0, 2, ',', '.'),
                number_format($resumo['margem_liquida'] ?? 0, 2, ',', '.'),
            ], ';');
            fputcsv($file, []); // Linha em branco
            
            // Processos
            if (count($processos) > 0) {
                fputcsv($file, ['PROCESSOS EM EXECUÇÃO'], ';');
                fputcsv($file, ['Processo', 'Objeto', 'Receita', 'Custos Diretos', 'Lucro', 'Margem (%)'], ';');
                foreach ($processos as $processo) {
                    fputcsv($file, [
                        $processo['numero_modalidade'] ?? '',
                        $processo['objeto_resumido'] ?? '',
                        number_format($processo['receita'] ?? 0, 2, ',', '.'),
                        number_format($processo['custos_diretos'] ?? 0, 2, ',', '.'),
                        number_format($processo['lucro'] ?? 0, 2, ',', '.'),
                        number_format($processo['margem'] ?? 0, 2, ',', '.'),
                    ], ';');
                }
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Exporta relatório mensal em PDF (simplificado - retorna HTML por enquanto)
     */
    protected function exportarPDFMensal($data, Carbon $mes): \Illuminate\Http\Response
    {
        // Por enquanto, retornar CSV já que PDF requer biblioteca externa
        return $this->exportarCSVMensal($data, $mes);
    }

    /**
     * Exporta relatório de execução em PDF (simplificado - retorna HTML por enquanto)
     */
    protected function exportarPDFExecucao($data, array $filters): \Illuminate\Http\Response
    {
        // Por enquanto, retornar CSV já que PDF requer biblioteca externa
        return $this->exportarCSVExecucao($data, $filters);
    }
}

