<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Empenho\Entities\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Modules\Empenho\Models\Empenho as EmpenhoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        $query = EmpenhoModel::query();
        
        if (!empty($with)) {
            $query->with($with);
        }
        
        return $query->find($id);
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = EmpenhoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['processo_id'])) {
            $query->where('processo_id', $filtros['processo_id']);
        }

        if (isset($filtros['contrato_id'])) {
            $query->where('contrato_id', $filtros['contrato_id']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

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


