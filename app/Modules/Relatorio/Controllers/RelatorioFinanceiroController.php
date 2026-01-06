<?php

namespace App\Modules\Relatorio\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Custo\Models\CustoIndireto;
use App\Modules\Relatorio\Services\FinanceiroService;
use App\Services\RedisService;
use App\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;
use Carbon\Carbon;

class RelatorioFinanceiroController extends BaseApiController
{
    protected FinanceiroService $financeiroService;

    public function __construct(FinanceiroService $financeiroService)
    {
        $this->financeiroService = $financeiroService;
    }

    public function index(Request $request)
    {
        // Verificar se o plano tem acesso a relatórios
        $tenant = tenancy()->tenant;
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

        $tenantId = tenancy()->tenant?->id;

        // Se for gestão financeira mensal (apenas processos encerrados)
        if ($request->tipo === 'mensal' || $request->mes) {
            $mes = $request->mes 
                ? Carbon::createFromFormat('Y-m', $request->mes)
                : Carbon::now();
            
            // Tentar obter do cache primeiro
            if ($tenantId && RedisService::isAvailable()) {
                $cached = RedisService::getRelatorioFinanceiro(
                    $tenantId, 
                    $mes->month, 
                    $mes->year
                );
                if ($cached !== null) {
                    return response()->json($cached);
                }
            }
            
            $empresa = $this->getEmpresaAtivaOrFail();
            $resultado = $this->financeiroService->calcularGestaoFinanceiraMensal($mes, $empresa->id);
            
            // Salvar no cache se disponível
            if ($tenantId && RedisService::isAvailable()) {
                RedisService::cacheRelatorioFinanceiro(
                    $tenantId, 
                    $mes->month, 
                    $mes->year, 
                    $resultado, 
                    3600
                ); // Cache por 1 hora
            }
            
            return response()->json($resultado);
        }

        // Relatório padrão (processos em execução)
        $empresa = $this->getEmpresaAtivaOrFail();
        $query = Processo::where('status', 'execucao')
            ->where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->with(['itens', 'contratos', 'empenhos', 'notasFiscais']);

        // Usar o timestamp customizado (criado_em) em vez de created_at
        $createdAtColumn = Processo::CREATED_AT ?? Blueprint::CREATED_AT;

        if ($request->data_inicio) {
            $query->where($createdAtColumn, '>=', $request->data_inicio);
        }

        if ($request->data_fim) {
            $query->where($createdAtColumn, '<=', $request->data_fim);
        }

        $processos = $query->orderBy($createdAtColumn, 'desc')->get();

        $totalReceber = 0;
        $totalCustosDiretos = 0;
        $totalSaldoReceber = 0;

        $totalCustosIndiretos = CustoIndireto::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->when($request->data_inicio, function($q) use ($request) {
                $q->where('data', '>=', $request->data_inicio);
            })
            ->when($request->data_fim, function($q) use ($request) {
                $q->where('data', '<=', $request->data_fim);
            })
            ->sum('valor');

        foreach ($processos as $processo) {
            if ($processo->contratos->count() > 0) {
                $receita = $processo->contratos->sum('valor_total');
                $totalSaldoReceber += $processo->contratos->sum('saldo');
            } else {
                $receita = $processo->itens->sum(function($item) {
                    return $item->valor_arrematado 
                        ?? $item->valor_negociado 
                        ?? $item->valor_final_sessao 
                        ?? 0;
                });
                $totalSaldoReceber += $receita;
            }
            $totalReceber += $receita;

            $custosDiretos = $processo->notasFiscais
                ->where('tipo', 'entrada')
                ->sum('valor');
            $totalCustosDiretos += $custosDiretos;
        }

        $lucroBruto = $totalReceber - $totalCustosDiretos;
        $lucroLiquido = $lucroBruto - $totalCustosIndiretos;
        $margemBruta = $totalReceber > 0 ? ($lucroBruto / $totalReceber) * 100 : 0;
        $margemLiquida = $totalReceber > 0 ? ($lucroLiquido / $totalReceber) * 100 : 0;

        return response()->json([
            'resumo' => [
                'total_receber' => $totalReceber,
                'total_custos_diretos' => $totalCustosDiretos,
                'total_custos_indiretos' => $totalCustosIndiretos,
                'total_saldo_receber' => $totalSaldoReceber,
                'lucro_bruto' => $lucroBruto,
                'lucro_liquido' => $lucroLiquido,
                'margem_bruta' => round($margemBruta, 2),
                'margem_liquida' => round($margemLiquida, 2),
            ],
            'processos' => $processos->map(function($processo) {
                if ($processo->contratos->count() > 0) {
                    $receita = $processo->contratos->sum('valor_total');
                    $saldoReceber = $processo->contratos->sum('saldo');
                } else {
                    $receita = $processo->itens->sum(function($item) {
                        return $item->valor_negociado ?? $item->valor_final_sessao ?? 0;
                    });
                    $saldoReceber = $receita;
                }
                $custosDiretos = $processo->notasFiscais->where('tipo', 'entrada')->sum('valor');
                $lucro = $receita - $custosDiretos;
                $margem = $receita > 0 ? ($lucro / $receita) * 100 : 0;

                return [
                    'id' => $processo->id,
                    'numero_modalidade' => $processo->numero_modalidade,
                    'objeto_resumido' => $processo->objeto_resumido,
                    'receita' => $receita,
                    'saldo_receber' => $saldoReceber,
                    'custos_diretos' => $custosDiretos,
                    'lucro' => $lucro,
                    'margem' => round($margem, 2),
                ];
            }),
        ]);
    }

    /**
     * Endpoint específico para gestão financeira mensal
     */
    public function gestaoMensal(Request $request)
    {
        // Verificar se o plano tem acesso a relatórios
        $tenant = tenancy()->tenant;
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
        
        $mes = $request->mes 
            ? Carbon::createFromFormat('Y-m', $request->mes)
            : Carbon::now();

        $resultado = $this->financeiroService->calcularGestaoFinanceiraMensal($mes, $empresa->id);

        return response()->json($resultado);
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

