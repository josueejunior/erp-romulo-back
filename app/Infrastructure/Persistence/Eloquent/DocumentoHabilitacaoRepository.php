<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Models\DocumentoHabilitacao as DocumentoHabilitacaoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class DocumentoHabilitacaoRepository implements DocumentoHabilitacaoRepositoryInterface
{
    private function toDomain(DocumentoHabilitacaoModel $model): DocumentoHabilitacao
    {
        return new DocumentoHabilitacao(
            id: $model->id,
            empresaId: $model->empresa_id,
            tipo: $model->tipo,
            numero: $model->numero,
            identificacao: $model->identificacao,
            dataEmissao: $model->data_emissao ? Carbon::parse($model->data_emissao) : null,
            dataValidade: $model->data_validade ? Carbon::parse($model->data_validade) : null,
            arquivo: $model->arquivo,
            ativo: $model->ativo ?? true,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(DocumentoHabilitacao $documento): array
    {
        return [
            'empresa_id' => $documento->empresaId,
            'tipo' => $documento->tipo,
            'numero' => $documento->numero,
            'identificacao' => $documento->identificacao,
            'data_emissao' => $documento->dataEmissao?->toDateString(),
            'data_validade' => $documento->dataValidade?->toDateString(),
            'arquivo' => $documento->arquivo,
            'ativo' => $documento->ativo,
            'observacoes' => $documento->observacoes,
        ];
    }

    public function criar(DocumentoHabilitacao $documento): DocumentoHabilitacao
    {
        $model = DocumentoHabilitacaoModel::create($this->toArray($documento));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?DocumentoHabilitacao
    {
        $model = DocumentoHabilitacaoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = DocumentoHabilitacaoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(DocumentoHabilitacao $documento): DocumentoHabilitacao
    {
        $model = DocumentoHabilitacaoModel::findOrFail($documento->id);
        $model->update($this->toArray($documento));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        DocumentoHabilitacaoModel::findOrFail($id)->delete();
    }
}

