<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Orcamento\Entities\Orcamento;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Models\Orcamento as OrcamentoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class OrcamentoRepository implements OrcamentoRepositoryInterface
{
    use HasModelRetrieval;
    private function toDomain(OrcamentoModel $model): Orcamento
    {
        return new Orcamento(
            id: $model->id,
            empresaId: $model->empresa_id,
            processoId: $model->processo_id,
            processoItemId: $model->processo_item_id,
            fornecedorId: $model->fornecedor_id,
            transportadoraId: $model->transportadora_id,
            custoProduto: (float) $model->custo_produto,
            marcaModelo: $model->marca_modelo,
            ajustesEspecificacao: $model->ajustes_especificacao,
            frete: (float) $model->frete,
            freteIncluido: $model->frete_incluido ?? false,
            fornecedorEscolhido: $model->fornecedor_escolhido ?? false,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(Orcamento $orcamento): array
    {
        return [
            'empresa_id' => $orcamento->empresaId,
            'processo_id' => $orcamento->processoId,
            'processo_item_id' => $orcamento->processoItemId,
            'fornecedor_id' => $orcamento->fornecedorId,
            'transportadora_id' => $orcamento->transportadoraId,
            'custo_produto' => $orcamento->custoProduto,
            'marca_modelo' => $orcamento->marcaModelo,
            'ajustes_especificacao' => $orcamento->ajustesEspecificacao,
            'frete' => $orcamento->frete,
            'frete_incluido' => $orcamento->freteIncluido,
            'fornecedor_escolhido' => $orcamento->fornecedorEscolhido,
            'observacoes' => $orcamento->observacoes,
        ];
    }

    public function criar(Orcamento $orcamento): Orcamento
    {
        $model = OrcamentoModel::create($this->toArray($orcamento));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Orcamento
    {
        $model = OrcamentoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = OrcamentoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['processo_id'])) {
            $query->where('processo_id', $filtros['processo_id']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Orcamento $orcamento): Orcamento
    {
        $model = OrcamentoModel::findOrFail($orcamento->id);
        $model->update($this->toArray($orcamento));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        OrcamentoModel::findOrFail($id)->delete();
    }

    /**
     * Busca um modelo Eloquent por ID (para Resources do Laravel)
     * Mantém o Global Scope de Empresa ativo para segurança
     */
    public function buscarModeloPorId(int $id, array $with = []): ?OrcamentoModel
    {
        return $this->buscarModeloPorIdInternal($id, $with, false);
    }

    /**
     * Retorna a classe do modelo Eloquent
     */
    protected function getModelClass(): ?string
    {
        return OrcamentoModel::class;
    }
}

