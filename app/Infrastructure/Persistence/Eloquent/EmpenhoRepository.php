<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Modules\Empenho\Models\Empenho as EmpenhoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmpenhoRepository implements EmpenhoRepositoryInterface
{
    private function toDomain(EmpenhoModel $model): Empenho
    {
        return new Empenho(
            id: $model->id,
            empresaId: $model->empresa_id,
            processoId: $model->processo_id,
            contratoId: $model->contrato_id,
            autorizacaoFornecimentoId: $model->autorizacao_fornecimento_id,
            numero: $model->numero,
            data: $model->data ? Carbon::parse($model->data) : null,
            dataRecebimento: $model->data_recebimento ? Carbon::parse($model->data_recebimento) : null,
            prazoEntregaCalculado: $model->prazo_entrega_calculado ? Carbon::parse($model->prazo_entrega_calculado) : null,
            valor: (float) $model->valor,
            concluido: $model->concluido ?? false,
            situacao: $model->situacao,
            dataEntrega: $model->data_entrega ? Carbon::parse($model->data_entrega) : null,
            observacoes: $model->observacoes,
            numeroCte: $model->numero_cte,
        );
    }

    private function toArray(Empenho $empenho): array
    {
        return [
            'empresa_id' => $empenho->empresaId,
            'processo_id' => $empenho->processoId,
            'contrato_id' => $empenho->contratoId,
            'autorizacao_fornecimento_id' => $empenho->autorizacaoFornecimentoId,
            'numero' => $empenho->numero,
            'data' => $empenho->data?->toDateString(),
            'data_recebimento' => $empenho->dataRecebimento?->toDateString(),
            'prazo_entrega_calculado' => $empenho->prazoEntregaCalculado?->toDateString(),
            'valor' => $empenho->valor,
            'concluido' => $empenho->concluido,
            'situacao' => $empenho->situacao,
            'data_entrega' => $empenho->dataEntrega?->toDateString(),
            'observacoes' => $empenho->observacoes,
            'numero_cte' => $empenho->numeroCte,
        ];
    }

    public function criar(Empenho $empenho): Empenho
    {
        $model = EmpenhoModel::create($this->toArray($empenho));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Empenho
    {
        $model = EmpenhoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarModeloPorId(int $id, array $with = []): ?EmpenhoModel
    {
        // Remover Global Scope para garantir controle explícito
        $query = EmpenhoModel::withoutGlobalScope('empresa');
        
        if (!empty($with)) {
            // Usar withoutGlobalScopes nos relacionamentos para evitar filtros indesejados
            $eagerLoad = [];
            foreach ($with as $relation) {
                $eagerLoad[$relation] = function ($q) {
                    $q->withoutGlobalScopes();
                };
            }
            $query->with($eagerLoad);
        }
        
        return $query->find($id);
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        // Remover Global Scope para garantir controle explícito do filtro de empresa
        $query = EmpenhoModel::withoutGlobalScope('empresa');

        // Sempre filtrar por empresa_id se fornecido (obrigatório para segurança)
        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        } else {
            // Se não fornecido, o Global Scope pode estar aplicando, mas é melhor ser explícito
            \Log::warning('EmpenhoRepository::buscarComFiltros() chamado sem empresa_id', [
                'filtros' => $filtros,
            ]);
        }

        // Filtrar por processo_id se fornecido
        if (isset($filtros['processo_id'])) {
            $query->where('processo_id', $filtros['processo_id']);
        }

        if (isset($filtros['contrato_id'])) {
            $query->where('contrato_id', $filtros['contrato_id']);
        }

        if (isset($filtros['situacao'])) {
            $query->where('situacao', $filtros['situacao']);
        }

        if (isset($filtros['concluido'])) {
            $query->where('concluido', $filtros['concluido']);
        }

        // Carregar relacionamentos necessários
        // Usar withoutGlobalScopes nos relacionamentos para evitar filtros indesejados
        $query->with([
            'processo' => function ($q) {
                $q->withoutGlobalScopes();
            },
            'contrato' => function ($q) {
                $q->withoutGlobalScopes();
            },
            'autorizacaoFornecimento' => function ($q) {
                $q->withoutGlobalScopes();
            },
        ]);

        // Verificações diretas no banco (para debug)
        $directCount = DB::table('empenhos')
            ->where('empresa_id', $filtros['empresa_id'] ?? 0)
            ->where('processo_id', $filtros['processo_id'] ?? 0)
            ->whereNull('excluido_em')
            ->count();
        
        // Verificar se há empenhos para esse processo (sem filtro de empresa)
        $countPorProcesso = DB::table('empenhos')
            ->where('processo_id', $filtros['processo_id'] ?? 0)
            ->whereNull('excluido_em')
            ->count();
        
        // Verificar se há empenhos para essa empresa (sem filtro de processo)
        $countPorEmpresa = DB::table('empenhos')
            ->where('empresa_id', $filtros['empresa_id'] ?? 0)
            ->whereNull('excluido_em')
            ->count();
        
        // Verificar total de empenhos (sem filtros)
        $totalEmpenhos = DB::table('empenhos')
            ->whereNull('excluido_em')
            ->count();
        
        // Listar alguns empenhos para debug
        $sampleEmpenhos = DB::table('empenhos')
            ->whereNull('excluido_em')
            ->select('id', 'empresa_id', 'processo_id', 'numero', 'excluido_em')
            ->limit(10)
            ->get();
        
        // Verificar quantos registros existem antes de paginar (para debug)
        $countBeforePaginate = $query->count();
        \Log::debug('EmpenhoRepository::buscarComFiltros() - Count antes de paginar', [
            'count' => $countBeforePaginate,
            'direct_db_count' => $directCount,
            'count_por_processo' => $countPorProcesso,
            'count_por_empresa' => $countPorEmpresa,
            'total_empenhos' => $totalEmpenhos,
            'sample_empenhos' => $sampleEmpenhos->toArray(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'filtros' => $filtros,
        ]);

        $perPage = $filtros['per_page'] ?? 15;
        $page = $filtros['page'] ?? 1;
        
        // Usar paginate com page explícito
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Log do resultado
        \Log::debug('EmpenhoRepository::buscarComFiltros() - Resultado da paginação', [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'count_before_paginate' => $countBeforePaginate,
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'items_ids' => $paginator->getCollection()->pluck('id')->toArray(),
        ]);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Empenho $empenho): Empenho
    {
        $model = EmpenhoModel::findOrFail($empenho->id);
        $model->update($this->toArray($empenho));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        EmpenhoModel::findOrFail($id)->delete();
    }
}


