<?php

namespace App\Domain\Contrato\Queries;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Query Object para cálculo de indicadores de contratos
 * 
 * ✅ Usa agregação SQL (não carrega dados em memória)
 * ✅ Performance otimizada
 * ✅ Reutilizável
 */
class ContratoIndicadoresQuery
{
    /**
     * Calcula indicadores usando agregação SQL
     * 
     * ✅ Não carrega dados em memória
     * ✅ Queries otimizadas
     */
    public static function calcular(Builder $query): array
    {
        $hoje = Carbon::now();
        $trintaDias = $hoje->copy()->addDays(30);

        // ✅ Agregação SQL ao invés de carregar tudo
        $contratosAtivos = (clone $query)
            ->where('vigente', true)
            ->count();

        $contratosAVencer = (clone $query)
            ->where('vigente', true)
            ->whereBetween('data_fim', [$hoje, $trintaDias])
            ->count();

        $saldos = (clone $query)
            ->selectRaw('
                COALESCE(SUM(valor_total), 0) as total_contratado,
                COALESCE(SUM(valor_empenhado), 0) as total_faturado,
                COALESCE(SUM(saldo), 0) as total_restante
            ')
            ->first();

        return [
            'contratos_ativos' => $contratosAtivos,
            'contratos_a_vencer' => $contratosAVencer,
            'saldo_total_contratado' => (float) ($saldos->total_contratado ?? 0),
            'saldo_ja_faturado' => (float) ($saldos->total_faturado ?? 0),
            'saldo_restante' => (float) ($saldos->total_restante ?? 0),
            'margem_media' => 0, // Será calculado separadamente (requer notas fiscais)
        ];
    }

    /**
     * Calcula margem média usando agregação SQL
     * 
     * ✅ Uma query ao invés de N queries
     * ✅ Performance otimizada
     * 
     * Nota: NotaFiscal pode ter contrato_id diretamente OU através de empenho
     */
    public static function calcularMargemMedia(array $contratosIds, int $empresaId): float
    {
        if (empty($contratosIds)) {
            return 0;
        }

        // ✅ Buscar custos (notas de entrada) - direto ou via empenho
        $custos = \DB::table('notas_fiscais as nf')
            ->leftJoin('empenhos as e', 'e.id', '=', 'nf.empenho_id')
            ->where(function($q) use ($contratosIds) {
                $q->whereIn('nf.contrato_id', $contratosIds)
                  ->orWhereIn('e.contrato_id', $contratosIds);
            })
            ->where('nf.tipo', 'entrada')
            ->where('nf.empresa_id', $empresaId)
            ->selectRaw('
                COALESCE(nf.contrato_id, e.contrato_id) as contrato_id,
                COALESCE(SUM(COALESCE(nf.custo_total, nf.custo_produto, 0)), 0) as custo_total
            ')
            ->groupBy(\DB::raw('COALESCE(nf.contrato_id, e.contrato_id)'))
            ->get()
            ->keyBy('contrato_id');

        // ✅ Buscar receitas (notas de saída) - direto ou via empenho
        $receitas = \DB::table('notas_fiscais as nf')
            ->leftJoin('empenhos as e', 'e.id', '=', 'nf.empenho_id')
            ->where(function($q) use ($contratosIds) {
                $q->whereIn('nf.contrato_id', $contratosIds)
                  ->orWhereIn('e.contrato_id', $contratosIds);
            })
            ->where('nf.tipo', 'saida')
            ->where('nf.empresa_id', $empresaId)
            ->selectRaw('
                COALESCE(nf.contrato_id, e.contrato_id) as contrato_id,
                COALESCE(SUM(nf.valor), 0) as receita_total
            ')
            ->groupBy(\DB::raw('COALESCE(nf.contrato_id, e.contrato_id)'))
            ->get()
            ->keyBy('contrato_id');

        // ✅ Buscar valor_total dos contratos (fallback para receita)
        $contratos = \DB::table('contratos')
            ->whereIn('id', $contratosIds)
            ->where('empresa_id', $empresaId)
            ->select('id', 'valor_total')
            ->get()
            ->keyBy('id');

        // Calcular margens
        $margensCalculadas = [];
        foreach ($contratosIds as $contratoId) {
            $custoTotal = (float) ($custos->get($contratoId)->custo_total ?? 0);
            $receitaTotal = (float) ($receitas->get($contratoId)->receita_total ?? 0);
            
            // Se não tem receita de notas, usar valor_total do contrato
            if ($receitaTotal == 0) {
                $receitaTotal = (float) ($contratos->get($contratoId)->valor_total ?? 0);
            }

            if ($receitaTotal > 0 && $custoTotal > 0) {
                $lucro = $receitaTotal - $custoTotal;
                $margemPercentual = ($lucro / $receitaTotal) * 100;
                $margensCalculadas[] = $margemPercentual;
            }
        }

        if (empty($margensCalculadas)) {
            return 0;
        }

        return round(array_sum($margensCalculadas) / count($margensCalculadas), 2);
    }
}

