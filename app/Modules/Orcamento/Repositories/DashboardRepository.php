<?php

namespace App\Modules\Orcamento\Repositories;

use App\Modules\Orcamento\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Orcamento\Domain\ValueObjects\MetricaOrcamento;
use App\Modules\Orcamento\Domain\ValueObjects\AnalisePrecoItem;
use App\Modules\Orcamento\Domain\ValueObjects\PerformanceFornecedor;
use App\Modules\Orcamento\Domain\ValueObjects\ResumoStatusOrcamento;
use App\Modules\Orcamento\Models\Orcamento;
use App\Modules\Orcamento\Models\OrcamentoItem;
use App\Modules\Processo\Models\Processo;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function obterMetricas(int $empresaId): MetricaOrcamento
    {
        $totalOrcamentos = Orcamento::where('empresa_id', $empresaId)->count();
        $valorTotal = Orcamento::where('empresa_id', $empresaId)->sum('valor_total') ?? 0;

        return new MetricaOrcamento($totalOrcamentos, $valorTotal);
    }

    public function obterAnalisePrecos(int $empresaId): array
    {
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
        ->get();

        return $analise->map(function ($item) {
            return new AnalisePrecoItem(
                $item->processo_item_id,
                $item->processoItem?->descricao ?? 'N/A',
                $item->preco_minimo,
                $item->preco_maximo,
                $item->preco_medio,
                $item->total_cotacoes
            );
        })->toArray();
    }

    public function obterPerformanceFornecedores(int $empresaId): array
    {
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
            ->get();

        return $performance->map(function ($orcamento) {
            $total = $orcamento->total_orcamentos ?? 1;
            $taxaAprovacao = ($orcamento->orcamentos_aprovados / $total) * 100;
            $taxaRejeicao = ($orcamento->orcamentos_rejeitados / $total) * 100;

            return new PerformanceFornecedor(
                $orcamento->fornecedor_id,
                $orcamento->fornecedor?->nome ?? 'N/A',
                $orcamento->total_orcamentos,
                $orcamento->valor_total ?? 0,
                $orcamento->valor_medio ?? 0,
                $taxaAprovacao,
                $taxaRejeicao
            );
        })->toArray();
    }

    public function obterResumoStatus(int $empresaId): array
    {
        $resumo = Orcamento::where('empresa_id', $empresaId)
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(valor_total) as valor'))
            ->groupBy('status')
            ->get();

        $totalGeral = $resumo->sum('total');

        return $resumo->map(function ($item) use ($totalGeral) {
            return new ResumoStatusOrcamento($item->status, $item->total, $item->valor ?? 0);
        })->map(fn($item) => $item->toArray($totalGeral))->toArray();
    }

    public function obterTimeline(int $empresaId, int $limit = 10): array
    {
        return Orcamento::where('empresa_id', $empresaId)
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
            })->toArray();
    }

    public function obterComparacaoPeriodos(int $empresaId, int $meses = 12): array
    {
        $dados = Orcamento::where('empresa_id', $empresaId)
            ->select(
                DB::raw('DATE_TRUNC(\'month\', created_at) as periodo'),
                DB::raw('count(*) as total'),
                DB::raw('sum(valor_total) as valor')
            )
            ->groupBy('periodo')
            ->orderBy('periodo', 'asc')
            ->limit($meses)
            ->get();

        return $dados->map(function ($item) {
            return [
                'periodo' => $item->periodo,
                'total' => $item->total,
                'valor' => round($item->valor ?? 0, 2)
            ];
        })->toArray();
    }

    public function obterProcessosMaiorGasto(int $empresaId, int $limit = 5): array
    {
        return Processo::where('empresa_id', $empresaId)
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
                    'valor_total' => round($total, 2)
                ];
            })
            ->sortByDesc('valor_total')
            ->take($limit)
            ->toArray();
    }
}
