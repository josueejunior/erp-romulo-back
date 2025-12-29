<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use App\Modules\Fornecedor\Models\Fornecedor as FornecedorModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Infrastructure\Persistence\Eloquent\Traits\IsolamentoEmpresaTrait;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class FornecedorRepository implements FornecedorRepositoryInterface
{
    use IsolamentoEmpresaTrait, HasModelRetrieval;
    private function toDomain(FornecedorModel $model): Fornecedor
    {
        return new Fornecedor(
            id: $model->id,
            empresaId: $model->empresa_id,
            razaoSocial: $model->razao_social,
            cnpj: $model->cnpj,
            nomeFantasia: $model->nome_fantasia,
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
            contato: $model->contato,
            observacoes: $model->observacoes,
            isTransportadora: $model->is_transportadora ?? false,
        );
    }

    private function toArray(Fornecedor $fornecedor): array
    {
        return [
            'empresa_id' => $fornecedor->empresaId,
            'razao_social' => $fornecedor->razaoSocial,
            'cnpj' => $fornecedor->cnpj,
            'nome_fantasia' => $fornecedor->nomeFantasia,
            'cep' => $fornecedor->cep,
            'logradouro' => $fornecedor->logradouro,
            'numero' => $fornecedor->numero,
            'bairro' => $fornecedor->bairro,
            'complemento' => $fornecedor->complemento,
            'cidade' => $fornecedor->cidade,
            'estado' => $fornecedor->estado,
            'email' => $fornecedor->email,
            'telefone' => $fornecedor->telefone,
            'emails' => $fornecedor->emails,
            'telefones' => $fornecedor->telefones,
            'contato' => $fornecedor->contato,
            'observacoes' => $fornecedor->observacoes,
            'is_transportadora' => $fornecedor->isTransportadora,
        ];
    }

    public function criar(Fornecedor $fornecedor): Fornecedor
    {
        $model = FornecedorModel::create($this->toArray($fornecedor));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Fornecedor
    {
        $model = FornecedorModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        // Aplicar filtro de empresa_id com isolamento
        $query = $this->aplicarFiltroEmpresa(FornecedorModel::class, $filtros);

        // Filtro por transportadoras
        if (isset($filtros['apenas_transportadoras']) && $filtros['apenas_transportadoras']) {
            $query->where('is_transportadora', true);
        }

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('razao_social', 'ilike', "%{$search}%")
                  ->orWhere('cnpj', 'ilike', "%{$search}%")
                  ->orWhere('nome_fantasia', 'ilike', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        // Validar que todos os registros pertencem à empresa correta
        $this->validarEmpresaIds($paginator, $filtros['empresa_id']);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Fornecedor $fornecedor): Fornecedor
    {
        $model = FornecedorModel::findOrFail($fornecedor->id);
        $model->update($this->toArray($fornecedor));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        FornecedorModel::findOrFail($id)->delete();
    }

    /**
     * Busca um modelo Eloquent por ID (para Resources do Laravel)
     * Mantém o Global Scope de Empresa ativo para segurança
     */
    public function buscarModeloPorId(int $id, array $with = []): ?FornecedorModel
    {
        return $this->buscarModeloPorIdInternal($id, $with, false);
    }

    /**
     * Retorna a classe do modelo Eloquent
     */
    protected function getModelClass(): ?string
    {
        return FornecedorModel::class;
    }
}

