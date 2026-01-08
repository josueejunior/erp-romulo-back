<?php

namespace App\Modules\Orcamento\Domain\Services;

use App\Modules\Orcamento\Domain\ValueObjects\FiltrosRelatorio;
use App\Domain\Orcamento\Repositories\RelatorioOrcamentoRepositoryInterface;
use App\Application\Orcamento\DTOs\RelatorioOrcamentosResult;
use Illuminate\Support\Collection;

/**
 * Domain Service para relatórios de orçamentos
 * 
 * ✅ DDD: Apenas regras de negócio, queries delegadas ao Repository
 */
class RelatorioDomainService
{
    public function __construct(
        private RelatorioOrcamentoRepositoryInterface $repository,
    ) {}

    /**
     * Gerar relatório de orçamentos por período
     * 
     * ✅ DDD: Retorna Read Model ao invés de array genérico
     */
    public function relatorioOrcamentosPorPeriodo(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): RelatorioOrcamentosResult {
        // Repository decide COMO buscar (queries)
        $orcamentos = $this->repository->buscarOrcamentosPorPeriodo($empresaId, $filtros);

        // Domain Service decide O QUE calcular (regras de negócio)
        $valorTotal = round($orcamentos->sum('valor_total'), 2);
        $valorMedio = round($orcamentos->avg('valor_total'), 2);

        return new RelatorioOrcamentosResult(
            titulo: 'Relatório de Orçamentos por Período',
            dados: $orcamentos,
            totalRegistros: $orcamentos->count(),
            valorTotal: $valorTotal,
            valorMedio: $valorMedio,
            filtros: $filtros->toArray(),
        );
    }

    /**
     * Gerar relatório por fornecedor
     */
    public function relatorioPorFornecedor(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): RelatorioOrcamentosResult {
        // Repository decide COMO buscar
        $porFornecedor = $this->repository->buscarOrcamentosPorFornecedor($empresaId, $filtros);

        // Domain Service calcula métricas (regras de negócio)
        $valorTotalGeral = round($porFornecedor->sum('valor_total'), 2);

        return new RelatorioOrcamentosResult(
            titulo: 'Relatório de Orçamentos por Fornecedor',
            dados: $porFornecedor,
            totalRegistros: $porFornecedor->count(),
            valorTotal: $valorTotalGeral,
            valorMedio: round($porFornecedor->avg('valor_total'), 2),
            filtros: $filtros->toArray(),
            resumo: [
                'total_fornecedores' => $porFornecedor->count(),
                'valor_total_geral' => $valorTotalGeral,
            ],
        );
    }

    /**
     * Gerar relatório por status
     */
    public function relatorioPorStatus(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): RelatorioOrcamentosResult {
        // Repository decide COMO buscar
        $porStatus = $this->repository->buscarOrcamentosPorStatus($empresaId, $filtros);

        // Domain Service calcula métricas (regras de negócio)
        $totalGeral = $porStatus->sum('total');
        $valorTotalGeral = round($porStatus->sum('valor_total'), 2);

        return new RelatorioOrcamentosResult(
            titulo: 'Relatório de Orçamentos por Status',
            dados: $porStatus,
            totalRegistros: $totalGeral,
            valorTotal: $valorTotalGeral,
            valorMedio: round($porStatus->avg('valor_medio'), 2),
            filtros: $filtros->toArray(),
            resumo: [
                'total_geral' => $totalGeral,
                'valor_total_geral' => $valorTotalGeral,
            ],
        );
    }

    /**
     * Gerar relatório de análise de preços
     * 
     * ⚠️ TODO: Migrar para Repository quando necessário
     * Por enquanto mantido como array para compatibilidade
     */
    public function relatorioAnalisePrecos(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): array {
        // ⚠️ Este método ainda usa Eloquent diretamente
        // Deve ser migrado para Repository quando necessário
        $analise = \App\Modules\Orcamento\Models\OrcamentoItem::whereHas('orcamento', function ($query) use ($empresaId, $filtros) {
            $query->where('empresa_id', $empresaId);
            if ($filtros->getDataInicio()) {
                $query->whereDate('created_at', '>=', $filtros->getDataInicio());
            }
            if ($filtros->getDataFim()) {
                $query->whereDate('created_at', '<=', $filtros->getDataFim());
            }
        })
        ->select(
            'processo_item_id',
            \Illuminate\Support\Facades\DB::raw('min(valor_unitario) as preco_minimo'),
            \Illuminate\Support\Facades\DB::raw('max(valor_unitario) as preco_maximo'),
            \Illuminate\Support\Facades\DB::raw('avg(valor_unitario) as preco_medio'),
            \Illuminate\Support\Facades\DB::raw('count(distinct orcamento_id) as total_cotacoes'),
            \Illuminate\Support\Facades\DB::raw('stddev(valor_unitario) as desvio_padrao')
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
            'variacao_media' => $this->calcularVariacaoMedia($analise->toArray()),
            'dados' => $analise->toArray()
        ];
    }

    /**
     * Gerar relatório executivo
     * 
     * ⚠️ TODO: Migrar para Repository quando necessário
     * Por enquanto mantido como array para compatibilidade
     */
    public function relatorioExecutivo(
        int $empresaId,
        FiltrosRelatorio $filtros
    ): array {
        // ⚠️ Este método ainda usa Eloquent diretamente
        // Deve ser migrado para Repository quando necessário
        $query = \App\Modules\Orcamento\Models\Orcamento::where('empresa_id', $empresaId);
        
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
            $query->where('fornecedor_escolhido', $filtros->getStatus() === 'escolhido');
        }

        $orcamentos = $query->with('itens')->get();

        $valor_total = $orcamentos->sum(fn($o) => $o->custo_total);
        $total_orcamentos = $orcamentos->count();
        $total_escolhidos = $orcamentos->where('fornecedor_escolhido', true)->count();
        $total_pendentes = $orcamentos->where('fornecedor_escolhido', false)->count();

        return [
            'titulo' => 'Relatório Executivo',
            'filtros' => $filtros->toArray(),
            'resumo' => [
                'total_orcamentos' => $total_orcamentos,
                'valor_total' => round($valor_total, 2),
                'valor_medio' => round($valor_total / max($total_orcamentos, 1), 2),
                'taxa_escolhidos' => round(($total_escolhidos / max($total_orcamentos, 1)) * 100, 2) . '%',
                'taxa_pendentes' => round(($total_pendentes / max($total_orcamentos, 1)) * 100, 2) . '%',
                'total_fornecedores' => $orcamentos->pluck('fornecedor_id')->unique()->count(),
                'total_processos' => $orcamentos->pluck('processo_id')->unique()->count()
            ],
            'tendencias' => $this->calcularTendencias($orcamentos),
            'top_fornecedores' => $this->topFornecedores($orcamentos, 5),
            'top_processos' => $this->topProcessos($orcamentos, 5)
        ];
    }

    // ====== MÉTODOS AUXILIARES (mantidos para compatibilidade com métodos legacy) ======

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
        $porMes = $orcamentos->filter(fn($o) => $o->created_at !== null)
            ->groupBy(fn($o) => $o->created_at->format('Y-m'));
        
        $tendencias = [];
        foreach ($porMes as $mes => $items) {
            $tendencias[$mes] = [
                'total' => $items->count(),
                'valor' => round($items->sum(fn($o) => $o->custo_total), 2)
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
                'valor' => round($items->sum(fn($o) => $o->custo_total), 2)
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
                'valor' => round($items->sum(fn($o) => $o->custo_total), 2)
            ])
            ->sortByDesc('valor')
            ->take($limite)
            ->toArray();
    }
}
