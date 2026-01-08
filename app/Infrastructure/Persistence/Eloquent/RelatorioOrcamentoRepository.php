<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Orcamento\Repositories\RelatorioOrcamentoRepositoryInterface;
use App\Modules\Orcamento\Domain\ValueObjects\FiltrosRelatorio;
use App\Modules\Orcamento\Models\Orcamento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository Eloquent para relatórios de orçamentos
 * 
 * ✅ DDD: Implementação de infraestrutura - apenas queries
 */
class RelatorioOrcamentoRepository implements RelatorioOrcamentoRepositoryInterface
{
    public function buscarOrcamentosPorPeriodo(int $empresaId, FiltrosRelatorio $filtros): Collection
    {
        $query = Orcamento::where('empresa_id', $empresaId);

        $this->aplicarFiltros($query, $filtros);

        return $query->with([
                'fornecedor' => fn($q) => $q->withoutGlobalScopes(),
                'processo' => fn($q) => $q->withoutGlobalScopes(),
                'processoItem.processo' => fn($q) => $q->withoutGlobalScopes(),
                'itens'
            ])
            ->get()
            ->map(function ($o) {
                // Tentar obter o processo de múltiplas formas
                $processoNumero = 'N/A';
                if ($o->processo) {
                    $processoNumero = $o->processo->numero_modalidade ?? $o->processo->identificador ?? 'N/A';
                } elseif ($o->processoItem && $o->processoItem->processo) {
                    $processoNumero = $o->processoItem->processo->numero_modalidade ?? $o->processoItem->processo->identificador ?? 'N/A';
                }
                
                return [
                    'id' => $o->id,
                    'data' => $o->criado_em?->format('d/m/Y') ?? $o->created_at?->format('d/m/Y') ?? 'N/A',
                    'fornecedor' => $o->fornecedor?->nome_fantasia ?? $o->fornecedor?->razao_social ?? 'N/A',
                    'processo' => $processoNumero,
                    'processo_id' => $o->processo_id ?? $o->processoItem?->processo_id ?? null,
                    'valor_total' => $o->custo_total,
                    'status' => $o->fornecedor_escolhido ? 'escolhido' : 'pendente',
                    'total_itens' => $o->itens->count()
                ];
            });
    }

    public function buscarOrcamentosPorFornecedor(int $empresaId, FiltrosRelatorio $filtros): Collection
    {
        $query = Orcamento::where('empresa_id', $empresaId);
        $this->aplicarFiltros($query, $filtros);

        return $query->select(
            'fornecedor_id',
            DB::raw('count(*) as total_orcamentos'),
            DB::raw('sum(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor_total'),
            DB::raw('avg(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor_medio'),
            DB::raw('count(case when fornecedor_escolhido = true then 1 end) as escolhidos'),
            DB::raw('count(case when fornecedor_escolhido = false then 1 end) as pendentes')
        )
        ->with(['fornecedor' => fn($q) => $q->withoutGlobalScopes()])
        ->groupBy('fornecedor_id')
        ->get()
        ->map(function ($item) {
            $total = $item->total_orcamentos ?? 1;
            return [
                'fornecedor' => $item->fornecedor?->nome_fantasia ?? $item->fornecedor?->razao_social ?? 'N/A',
                'total_orcamentos' => $item->total_orcamentos,
                'valor_total' => round($item->valor_total ?? 0, 2),
                'valor_medio' => round($item->valor_medio ?? 0, 2),
                'taxa_escolhidos' => round(($item->escolhidos / $total * 100), 2) . '%',
                'taxa_pendentes' => round(($item->pendentes / $total * 100), 2) . '%'
            ];
        });
    }

    public function buscarOrcamentosPorStatus(int $empresaId, FiltrosRelatorio $filtros): Collection
    {
        $query = Orcamento::where('empresa_id', $empresaId);
        $this->aplicarFiltros($query, $filtros);

        return $query->select(
            'fornecedor_escolhido',
            DB::raw('count(*) as total'),
            DB::raw('sum(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor_total'),
            DB::raw('avg(coalesce(custo_produto, 0) + coalesce(frete, 0)) as valor_medio')
        )
        ->groupBy('fornecedor_escolhido')
        ->get()
        ->map(function ($item) {
            return [
                'status' => $item->fornecedor_escolhido ? 'escolhido' : 'pendente',
                'total' => $item->total,
                'valor_total' => round($item->valor_total ?? 0, 2),
                'valor_medio' => round($item->valor_medio ?? 0, 2)
            ];
        });
    }

    private function aplicarFiltros($query, FiltrosRelatorio $filtros): void
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
            $query->where('fornecedor_escolhido', $filtros->getStatus() === 'escolhido');
        }
    }
}

