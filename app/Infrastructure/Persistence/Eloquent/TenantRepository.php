<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\Tenant as TenantModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Implementação do Repository de Tenant usando Eloquent
 * Esta é a única camada que conhece Eloquent/banco de dados
 */
class TenantRepository implements TenantRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(TenantModel $model): Tenant
    {
        return new Tenant(
            id: $model->id,
            razaoSocial: $model->razao_social,
            cnpj: $model->cnpj,
            email: $model->email,
            status: $model->status ?? 'ativa',
            endereco: $model->endereco,
            cidade: $model->cidade,
            estado: $model->estado,
            cep: $model->cep,
            telefones: $model->telefones,
            emailsAdicionais: $model->emails_adicionais,
            banco: $model->banco,
            agencia: $model->agencia,
            conta: $model->conta,
            tipoConta: $model->tipo_conta,
            pix: $model->pix,
            representanteLegalNome: $model->representante_legal_nome,
            representanteLegalCpf: $model->representante_legal_cpf,
            representanteLegalCargo: $model->representante_legal_cargo,
            logo: $model->logo,
        );
    }

    /**
     * Converter entidade do domínio para array do Eloquent
     */
    private function toArray(Tenant $tenant): array
    {
        return [
            'razao_social' => $tenant->razaoSocial,
            'cnpj' => $tenant->cnpj,
            'email' => $tenant->email,
            'status' => $tenant->status,
            'endereco' => $tenant->endereco,
            'cidade' => $tenant->cidade,
            'estado' => $tenant->estado,
            'cep' => $tenant->cep,
            'telefones' => $tenant->telefones,
            'emails_adicionais' => $tenant->emailsAdicionais,
            'banco' => $tenant->banco,
            'agencia' => $tenant->agencia,
            'conta' => $tenant->conta,
            'tipo_conta' => $tenant->tipoConta,
            'pix' => $tenant->pix,
            'representante_legal_nome' => $tenant->representanteLegalNome,
            'representante_legal_cpf' => $tenant->representanteLegalCpf,
            'representante_legal_cargo' => $tenant->representanteLegalCargo,
            'logo' => $tenant->logo,
        ];
    }

    public function criar(Tenant $tenant): Tenant
    {
        $model = TenantModel::create($this->toArray($tenant));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Tenant
    {
        $model = TenantModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorCnpj(string $cnpj): ?Tenant
    {
        $model = TenantModel::where('cnpj', $cnpj)->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = TenantModel::query();

        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('razao_social', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy(\App\Database\Schema\Blueprint::CREATED_AT, 'desc')->paginate($perPage);

        // Converter cada item para entidade do domínio
        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Tenant $tenant): Tenant
    {
        $model = TenantModel::findOrFail($tenant->id);
        $model->update($this->toArray($tenant));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        TenantModel::findOrFail($id)->delete();
    }
}

