<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\OrgaoResponsavel\Entities\OrgaoResponsavel;
use App\Domain\OrgaoResponsavel\Repositories\OrgaoResponsavelRepositoryInterface;
use App\Modules\Orgao\Models\OrgaoResponsavel as OrgaoResponsavelModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class OrgaoResponsavelRepository implements OrgaoResponsavelRepositoryInterface
{
    use HasModelRetrieval;

    private function toDomain(OrgaoResponsavelModel $model): OrgaoResponsavel
    {
        return new OrgaoResponsavel(
            id: $model->id,
            empresaId: $model->empresa_id,
            orgaoId: $model->orgao_id,
            nome: $model->nome,
            cargo: $model->cargo,
            emails: $model->emails,
            telefones: $model->telefones,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(OrgaoResponsavel $responsavel): array
    {
        return [
            'empresa_id' => $responsavel->empresaId,
            'orgao_id' => $responsavel->orgaoId,
            'nome' => $responsavel->nome,
            'cargo' => $responsavel->cargo,
            'emails' => $responsavel->emails,
            'telefones' => $responsavel->telefones,
            'observacoes' => $responsavel->observacoes,
        ];
    }

    public function criar(OrgaoResponsavel $responsavel): OrgaoResponsavel
    {
        $model = OrgaoResponsavelModel::create($this->toArray($responsavel));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?OrgaoResponsavel
    {
        $model = OrgaoResponsavelModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarModeloPorId(int $id, array $with = []): ?OrgaoResponsavelModel
    {
        return $this->getModel($id, $with);
    }

    public function buscarPorOrgao(int $orgaoId): array
    {
        $models = OrgaoResponsavelModel::where('orgao_id', $orgaoId)->get();
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = OrgaoResponsavelModel::query();

        if (isset($filtros['orgao_id'])) {
            $query->where('orgao_id', $filtros['orgao_id']);
        }

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['nome'])) {
            $query->where('nome', 'ilike', '%' . $filtros['nome'] . '%');
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('nome', 'asc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(OrgaoResponsavel $responsavel): OrgaoResponsavel
    {
        $model = OrgaoResponsavelModel::findOrFail($responsavel->id);
        $model->update($this->toArray($responsavel));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        OrgaoResponsavelModel::findOrFail($id)->delete();
    }

    protected function getModelClass(): ?string
    {
        return OrgaoResponsavelModel::class;
    }
}

