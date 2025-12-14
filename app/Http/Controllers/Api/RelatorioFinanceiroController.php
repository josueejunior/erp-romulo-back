<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\CustoIndireto;
use Illuminate\Http\Request;

class RelatorioFinanceiroController extends Controller
{
    public function index(Request $request)
    {
        $query = Processo::where('status', 'execucao')
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

        $totalCustosIndiretos = CustoIndireto::query()
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
                    return $item->valor_negociado ?? $item->valor_final_sessao ?? 0;
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
}

