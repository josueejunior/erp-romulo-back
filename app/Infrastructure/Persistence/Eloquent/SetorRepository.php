<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Setor\Entities\Setor;
use App\Domain\Setor\Repositories\SetorRepositoryInterface;
use App\Modules\Orgao\Models\Setor as SetorModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Infrastructure\Persistence\Eloquent\Traits\IsolamentoEmpresaTrait;

class SetorRepository implements SetorRepositoryInterface
{
    use IsolamentoEmpresaTrait;
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
        // Aplicar filtro de empresa_id com isolamento
        $query = $this->aplicarFiltroEmpresa(SetorModel::class, $filtros);

        if (isset($filtros['orgao_id'])) {
            $query->where('orgao_id', $filtros['orgao_id']);
        }

        // Busca livre
        if (isset($filtros['search']) && $filtros['search']) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('nome', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('nome', 'asc')->paginate($perPage);

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


