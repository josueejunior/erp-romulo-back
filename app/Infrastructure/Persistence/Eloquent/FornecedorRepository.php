<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Fornecedor\Entities\Fornecedor;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use App\Modules\Fornecedor\Models\Fornecedor as FornecedorModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FornecedorRepository implements FornecedorRepositoryInterface
{
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
        $query = FornecedorModel::query();

        // CRÍTICO: Sempre filtrar por empresa_id
        // Se não tiver empresa_id nos filtros, retornar vazio para segurança
        if (!isset($filtros['empresa_id']) || empty($filtros['empresa_id'])) {
            \Log::warning('FornecedorRepository->buscarComFiltros() chamado sem empresa_id', [
                'filtros' => $filtros,
            ]);
            // Retornar paginator vazio ao invés de todos os fornecedores
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $filtros['per_page'] ?? 15,
                1
            );
        }

        // Filtrar por empresa_id (obrigatório)
        $query->where('empresa_id', $filtros['empresa_id']);

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

        \Log::debug('FornecedorRepository->buscarComFiltros() resultado', [
            'empresa_id' => $filtros['empresa_id'],
            'total' => $paginator->total(),
            'count' => $paginator->count(),
        ]);

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
}

