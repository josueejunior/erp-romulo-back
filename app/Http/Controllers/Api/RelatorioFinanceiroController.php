<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\CustoIndireto;
use App\Services\FinanceiroService;
use App\Services\RedisService;
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

        if ($request->data_inicio) {
            $query->where('created_at', '>=', $request->data_inicio);
        }

        if ($request->data_fim) {
            $query->where('created_at', '<=', $request->data_fim);
        }

        $processos = $query->orderBy('created_at', 'desc')->get();

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
}




