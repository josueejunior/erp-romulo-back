<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Setor\Entities\Setor;
use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use App\Models\Setor as SetorModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SetorRepository implements SetorRepositoryInterface
{
    private function toDomain(SetorModel $model): Setor
    {
        return new Setor(
            id: $model->id,
            empresaId: $model->empresa_id,
            orgaoId: $model->orgao_id,
            nome: $model->nome,
            email: $model->email,
            telefone: $model->telefone,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(Setor $setor): array
    {
        return [
            'empresa_id' => $setor->empresaId,
            'orgao_id' => $setor->orgaoId,
            'nome' => $setor->nome,
            'email' => $setor->email,
            'telefone' => $setor->telefone,
            'observacoes' => $setor->observacoes,
        ];
    }

    public function criar(Setor $setor): Setor
    {
        $model = SetorModel::create($this->toArray($setor));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Setor
    {
        $model = SetorModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = SetorModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['orgao_id'])) {
            $query->where('orgao_id', $filtros['orgao_id']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Setor $setor): Setor
    {
        $model = SetorModel::findOrFail($setor->id);
        $model->update($this->toArray($setor));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        SetorModel::findOrFail($id)->delete();
    }
}

