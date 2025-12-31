<?php

namespace App\Modules\Orcamento\Domain\Services;

use App\Modules\Orcamento\Domain\ValueObjects\FiltrosRelatorio;
use App\Modules\Orcamento\Models\Orcamento;
use App\Modules\Orcamento\Models\OrcamentoItem;
use Illuminate\Support\Facades\DB;

class RelatorioDomainService
{
    /**
     * Gerar relatório de orçamentos por período
     */
    public function relatorioOrcamentosPorPeriodo(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): array {
        $query = Orcamento::where('empresa_id', $empresaId);

        $this->aplicarFiltros($query, $filtros);

        $orcamentos = $query->with(['fornecedor', 'processo', 'itens'])
            ->get()
            ->map(function ($o) {
                return [
                    'id' => $o->id,
                    'data' => $o->created_at->format('d/m/Y'),
                    'fornecedor' => $o->fornecedor?->nome ?? 'N/A',
                    'processo' => $o->processo?->numero ?? 'N/A',
                    'valor_total' => $o->valor_total,
                    'status' => $o->status,
                    'total_itens' => $o->itens->count()
                ];
            });

        return [
            'titulo' => 'Relatório de Orçamentos por Período',
            'filtros' => $filtros->toArray(),
            'total_registros' => $orcamentos->count(),
            'valor_total' => round($orcamentos->sum('valor_total'), 2),
            'valor_medio' => round($orcamentos->avg('valor_total'), 2),
            'dados' => $orcamentos->toArray()
        ];
    }

    /**
     * Gerar relatório por fornecedor
     */
    public function relatorioPorFornecedor(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): array {
        $query = Orcamento::where('empresa_id', $empresaId);
        $this->aplicarFiltros($query, $filtros);

        $por_fornecedor = $query->select(
            'fornecedor_id',
            DB::raw('count(*) as total_orcamentos'),
            DB::raw('sum(valor_total) as valor_total'),
            DB::raw('avg(valor_total) as valor_medio'),
            DB::raw('count(case when status = "aprovado" then 1 end) as aprovados'),
            DB::raw('count(case when status = "rejeitado" then 1 end) as rejeitados')
        )
        ->with('fornecedor')
        ->groupBy('fornecedor_id')
        ->get()
        ->map(function ($item) {
            $total = $item->total_orcamentos ?? 1;
            return [
                'fornecedor' => $item->fornecedor?->nome ?? 'N/A',
                'total_orcamentos' => $item->total_orcamentos,
                'valor_total' => round($item->valor_total, 2),
                'valor_medio' => round($item->valor_medio, 2),
                'taxa_aprovacao' => round(($item->aprovados / $total * 100), 2) . '%',
                'taxa_rejeicao' => round(($item->rejeitados / $total * 100), 2) . '%'
            ];
        });

        return [
            'titulo' => 'Relatório de Orçamentos por Fornecedor',
            'filtros' => $filtros->toArray(),
            'total_fornecedores' => $por_fornecedor->count(),
            'valor_total_geral' => round($por_fornecedor->sum('valor_total'), 2),
            'dados' => $por_fornecedor->toArray()
        ];
    }

    /**
     * Gerar relatório por status
     */
    public function relatorioPorStatus(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): array {
        $query = Orcamento::where('empresa_id', $empresaId);
        $this->aplicarFiltros($query, $filtros);

        $por_status = $query->select(
            'status',
            DB::raw('count(*) as total'),
            DB::raw('sum(valor_total) as valor_total'),
            DB::raw('avg(valor_total) as valor_medio')
        )
        ->groupBy('status')
        ->get()
        ->map(function ($item) {
            return [
                'status' => $item->status,
                'total' => $item->total,
                'valor_total' => round($item->valor_total, 2),
                'valor_medio' => round($item->valor_medio, 2)
            ];
        });

        return [
            'titulo' => 'Relatório de Orçamentos por Status',
            'filtros' => $filtros->toArray(),
            'total_geral' => $por_status->sum('total'),
            'valor_total_geral' => round($por_status->sum('valor_total'), 2),
            'dados' => $por_status->toArray()
        ];
    }

    /**
     * Gerar relatório de análise de preços
     */
    public function relatorioAnalisePrecos(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): array {
        $analise = OrcamentoItem::whereHas('orcamento', function ($query) use ($empresaId, $filtros) {
            $query->where('empresa_id', $empresaId);
            $this->aplicarFiltrosOrcamento($query, $filtros);
        })
        ->select(
            'processo_item_id',
            DB::raw('min(valor_unitario) as preco_minimo'),
            DB::raw('max(valor_unitario) as preco_maximo'),
            DB::raw('avg(valor_unitario) as preco_medio'),
            DB::raw('count(distinct orcamento_id) as total_cotacoes'),
            DB::raw('stddev(valor_unitario) as desvio_padrao')
        )
        ->with('processoItem')
        ->groupBy('processo_item_id')
        ->get()
        ->map(function ($item) {
            $variacao = $item->preco_minimo > 0 
                ? round((($item->preco_maximo - $item->preco_minimo) / $item->preco_minimo * 100), 2)
                : 0;

            return [
                'item' => $item->processoItem?->descricao ?? 'N/A',
                'preco_minimo' => round($item->preco_minimo, 2),
                'preco_maximo' => round($item->preco_maximo, 2),
                'preco_medio' => round($item->preco_medio, 2),
                'total_cotacoes' => $item->total_cotacoes,
                'variacao_percentual' => $variacao . '%',
                'desvio_padrao' => round($item->desvio_padrao, 2)
            ];
        });

        return [
            'titulo' => 'Relatório de Análise de Preços',
            'filtros' => $filtros->toArray(),
            'total_itens' => $analise->count(),
            'variacao_media' => $this->calcularVariacaoMedia($analise),
            'dados' => $analise->toArray()
        ];
    }

    /**
     * Gerar relatório executivo
     */
    public function relatorioExecutivo(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): array {
        $query = Orcamento::where('empresa_id', $empresaId);
        $this->aplicarFiltros($query, $filtros);

        $orcamentos = $query->get();

        $valor_total = $orcamentos->sum('valor_total');
        $total_orcamentos = $orcamentos->count();
        $total_aprovados = $orcamentos->where('status', 'aprovado')->count();
        $total_rejeitados = $orcamentos->where('status', 'rejeitado')->count();

        return [
            'titulo' => 'Relatório Executivo',
            'filtros' => $filtros->toArray(),
            'resumo' => [
                'total_orcamentos' => $total_orcamentos,
                'valor_total' => round($valor_total, 2),
                'valor_medio' => round($valor_total / max($total_orcamentos, 1), 2),
                'taxa_aprovacao' => round(($total_aprovados / max($total_orcamentos, 1)) * 100, 2) . '%',
                'taxa_rejeicao' => round(($total_rejeitados / max($total_orcamentos, 1)) * 100, 2) . '%',
                'total_fornecedores' => $orcamentos->pluck('fornecedor_id')->unique()->count(),
                'total_processos' => $orcamentos->pluck('processo_id')->unique()->count()
            ],
            'tendencias' => $this->calcularTendencias($orcamentos),
            'top_fornecedores' => $this->topFornecedores($orcamentos, 5),
            'top_processos' => $this->topProcessos($orcamentos, 5)
        ];
    }

    // ====== MÉTODOS AUXILIARES ======

    private function aplicarFiltros(&$query, FiltrosRelatorio $filtros)
    {
        if ($filtros->getDataInicio()) {
            $query->whereDate('created_at', '>=', $filtros->getDataInicio());
        }
        if ($filtros->getDataFim()) {
            $query->whereDate('created_at', '<=', $filtros->getDataFim());
        }
        if ($filtros->getFornecedorId()) {
            $query->where('fornecedor_id', $filtros->getFornecedorId());
        }
        if ($filtros->getProcessoId()) {
            $query->where('processo_id', $filtros->getProcessoId());
        }
        if ($filtros->getStatus()) {
            $query->where('status', $filtros->getStatus());
        }
    }

    private function aplicarFiltrosOrcamento(&$query, FiltrosRelatorio $filtros)
    {
        if ($filtros->getDataInicio()) {
            $query->whereDate('created_at', '>=', $filtros->getDataInicio());
        }
        if ($filtros->getDataFim()) {
            $query->whereDate('created_at', '<=', $filtros->getDataFim());
        }
    }

    private function calcularVariacaoMedia(array $itens): string
    {
        if (empty($itens)) {
            return '0%';
        }

        $variacoes = array_filter(
            array_map(fn($i) => str_replace('%', '', $i['variacao_percentual']), $itens),
            fn($v) => is_numeric($v)
        );

        $media = !empty($variacoes) ? array_sum($variacoes) / count($variacoes) : 0;

        return round($media, 2) . '%';
    }

    private function calcularTendencias($orcamentos): array
    {
        $porMes = $orcamentos->groupBy(fn($o) => $o->created_at->format('Y-m'));
        
        $tendencias = [];
        foreach ($porMes as $mes => $items) {
            $tendencias[$mes] = [
                'total' => $items->count(),
                'valor' => round($items->sum('valor_total'), 2)
            ];
        }

        return $tendencias;
    }

    private function topFornecedores($orcamentos, int $limite): array
    {
        return $orcamentos->groupBy('fornecedor_id')
            ->map(fn($items) => [
                'fornecedor_id' => $items[0]->fornecedor_id,
                'total' => $items->count(),
                'valor' => round($items->sum('valor_total'), 2)
            ])
            ->sortByDesc('valor')
            ->take($limite)
            ->toArray();
    }

    private function topProcessos($orcamentos, int $limite): array
    {
        return $orcamentos->groupBy('processo_id')
            ->map(fn($items) => [
                'processo_id' => $items[0]->processo_id,
                'total' => $items->count(),
                'valor' => round($items->sum('valor_total'), 2)
            ])
            ->sortByDesc('valor')
            ->take($limite)
            ->toArray();
    }
}
