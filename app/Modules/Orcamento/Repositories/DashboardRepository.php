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
        $valorTotal = Orcamento::where('empresa_id', $empresaId)
            ->selectRaw('sum(coalesce(custo_produto, 0) + coalesce(frete, 0)) as total')
            ->value('total') ?? 0;

        return new MetricaOrcamento($totalOrcamentos, (float) $valorTotal);
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
                DB::raw('sum(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor_total'),
                DB::raw('avg(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor_medio'),
                DB::raw('count(case when fornecedor_escolhido = true then 1 end) as orcamentos_aprovados'),
                DB::raw('count(case when fornecedor_escolhido = false then 1 end) as orcamentos_rejeitados')
            )
            ->with(['fornecedor' => fn($q) => $q->withoutGlobalScopes()])
            ->groupBy('fornecedor_id')
            ->get();

        return $performance->map(function ($orcamento) {
            $total = $orcamento->total_orcamentos ?? 1;
            $taxaAprovacao = ($orcamento->orcamentos_aprovados / $total) * 100;
            $taxaRejeicao = ($orcamento->orcamentos_rejeitados / $total) * 100;

            return new PerformanceFornecedor(
                $orcamento->fornecedor_id,
                $orcamento->fornecedor?->nome_fantasia ?? $orcamento->fornecedor?->razao_social ?? 'N/A',
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
            ->select(
                'fornecedor_escolhido',
                DB::raw('count(*) as total'),
                DB::raw('sum(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor')
            )
            ->groupBy('fornecedor_escolhido')
            ->get();

        $totalGeral = $resumo->sum('total');

        return $resumo->map(function ($item) use ($totalGeral) {
            $status = $item->fornecedor_escolhido ? 'escolhido' : 'pendente';
            return new ResumoStatusOrcamento($status, $item->total, $item->valor ?? 0);
        })->map(fn($item) => $item->toArray($totalGeral))->toArray();
    }

    public function obterTimeline(int $empresaId, int $limit = 10): array
    {
        return Orcamento::where('empresa_id', $empresaId)
            ->with([
                'fornecedor' => fn($q) => $q->withoutGlobalScopes(),
                'processo' => fn($q) => $q->withoutGlobalScopes()
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($orcamento) {
                return [
                    'id' => $orcamento->id,
                    'fornecedor' => $orcamento->fornecedor?->nome_fantasia ?? $orcamento->fornecedor?->razao_social ?? 'N/A',
                    'processo' => $orcamento->processo?->numero ?? 'N/A',
                    'valor' => round($orcamento->custo_total, 2),
                    'status' => $orcamento->fornecedor_escolhido ? 'escolhido' : 'pendente',
                    'data_criacao' => $orcamento->created_at?->format('d/m/Y H:i') ?? 'N/A',
                    'tipo_evento' => 'Orçamento Criado'
                ];
            })->toArray();
    }

    public function obterComparacaoPeriodos(int $empresaId, int $meses = 12): array
    {
        $dados = Orcamento::where('empresa_id', $empresaId)
            ->whereNotNull('created_at')
            ->select(
                DB::raw('DATE_TRUNC(\'month\', created_at) as periodo'),
                DB::raw('count(*) as total'),
                DB::raw('sum(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor')
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
                $query->selectRaw('processo_id, sum(coalesce(custo_produto, 0) + coalesce(frete, 0)) as total')
                    ->groupBy('processo_id');
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
