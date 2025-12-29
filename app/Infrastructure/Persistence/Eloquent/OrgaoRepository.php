<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Orgao\Entities\Orgao;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Models\Orgao as OrgaoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrgaoRepository implements OrgaoRepositoryInterface
{
    private function toDomain(OrgaoModel $model): Orgao
    {
        return new Orgao(
            id: $model->id,
            empresaId: $model->empresa_id,
            uasg: $model->uasg,
            razaoSocial: $model->razao_social,
            cnpj: $model->cnpj,
            cep: $model->cep,
            logradouro: $model->logradouro,
            numero: $model->numero,
            bairro: $model->bairro,
            complemento: $model->complemento,
            cidade: $model->cidade,
            estado: $model->estado,
            email: $model->email,
            telefone: $model->telefone,
            emails: $model->emails,
            telefones: $model->telefones,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(Orgao $orgao): array
    {
        return [
            'empresa_id' => $orgao->empresaId,
            'uasg' => $orgao->uasg,
            'razao_social' => $orgao->razaoSocial,
            'cnpj' => $orgao->cnpj,
            'cep' => $orgao->cep,
            'logradouro' => $orgao->logradouro,
            'numero' => $orgao->numero,
            'bairro' => $orgao->bairro,
            'complemento' => $orgao->complemento,
            'cidade' => $orgao->cidade,
            'estado' => $orgao->estado,
            'email' => $orgao->email,
            'telefone' => $orgao->telefone,
            'emails' => $orgao->emails,
            'telefones' => $orgao->telefones,
            'observacoes' => $orgao->observacoes,
        ];
    }

    public function criar(Orgao $orgao): Orgao
    {
        $model = OrgaoModel::create($this->toArray($orgao));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Orgao
    {
        $model = OrgaoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = OrgaoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('razao_social', 'ilike', "%{$search}%")
                  ->orWhere('uasg', 'ilike', "%{$search}%")
                  ->orWhere('cnpj', 'ilike', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Orgao $orgao): Orgao
    {
        $model = OrgaoModel::findOrFail($orgao->id);
        $model->update($this->toArray($orgao));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        OrgaoModel::findOrFail($id)->delete();
    }
}

