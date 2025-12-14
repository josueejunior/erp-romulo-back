<?php

namespace App\Http\Controllers;

use App\Models\Processo;
use App\Models\CustoIndireto;
use Illuminate\Http\Request;

class RelatorioFinanceiroController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();

        $query = Processo::where('empresa_id', $empresa->id)
            ->where('status', 'execucao')
            ->with(['itens', 'contratos', 'empenhos', 'notasFiscais']);

        if ($request->data_inicio) {
            $query->where('created_at', '>=', $request->data_inicio);
        }

        if ($request->data_fim) {
            $query->where('created_at', '<=', $request->data_fim);
        }

        $processos = $query->orderBy('created_at', 'desc')->get();

        // Calcular totais
        $totalReceber = 0;
        $totalCustosDiretos = 0;
        $totalCustosIndiretos = CustoIndireto::where('empresa_id', $empresa->id)
            ->when($request->data_inicio, function($q) use ($request) {
                $q->where('data', '>=', $request->data_inicio);
            })
            ->when($request->data_fim, function($q) use ($request) {
                $q->where('data', '<=', $request->data_fim);
            })
            ->sum('valor');

        $totalSaldoReceber = 0; // Saldo a receber (contratos - empenhos)

        foreach ($processos as $processo) {
            // Receita: valor dos contratos ou soma dos valores negociados dos itens
            $receita = 0;
            if ($processo->contratos->count() > 0) {
                $receita = $processo->contratos->sum('valor_total');
                $totalSaldoReceber += $processo->contratos->sum('saldo');
            } else {
                // Se não tem contrato, usar valores negociados ou finais dos itens
                $receita = $processo->itens->sum(function($item) {
                    return $item->valor_negociado ?? $item->valor_final_sessao ?? 0;
                });
            }
            $totalReceber += $receita;

            // Custos diretos (notas fiscais de entrada)
            $custosDiretos = $processo->notasFiscais
                ->where('tipo', 'entrada')
                ->sum('valor');
            $totalCustosDiretos += $custosDiretos;
        }

        $lucroBruto = $totalReceber - $totalCustosDiretos;
        $lucroLiquido = $lucroBruto - $totalCustosIndiretos;
        $margemBruta = $totalReceber > 0 ? ($lucroBruto / $totalReceber) * 100 : 0;
        $margemLiquida = $totalReceber > 0 ? ($lucroLiquido / $totalReceber) * 100 : 0;

        return view('relatorios.financeiro', compact(
            'processos',
            'totalReceber',
            'totalCustosDiretos',
            'totalCustosIndiretos',
            'totalSaldoReceber',
            'lucroBruto',
            'lucroLiquido',
            'margemBruta',
            'margemLiquida'
        ));
    }
}
