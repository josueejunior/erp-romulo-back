<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\FormacaoPreco\Entities\FormacaoPreco;
use App\Domain\FormacaoPreco\Repositories\FormacaoPrecoRepositoryInterface;
use App\Models\FormacaoPreco as FormacaoPrecoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class FormacaoPrecoRepository implements FormacaoPrecoRepositoryInterface
{
    use HasModelRetrieval;
    private function toDomain(FormacaoPrecoModel $model): FormacaoPreco
    {
        return new FormacaoPreco(
            id: $model->id,
            processoItemId: $model->processo_item_id,
            orcamentoId: $model->orcamento_id,
            orcamentoItemId: $model->orcamento_item_id,
            custoProduto: (float) $model->custo_produto,
            frete: (float) $model->frete,
            percentualImpostos: (float) $model->percentual_impostos,
            valorImpostos: (float) $model->valor_impostos,
            percentualMargem: (float) $model->percentual_margem,
            valorMargem: (float) $model->valor_margem,
            precoMinimo: (float) $model->preco_minimo,
            precoRecomendado: (float) $model->preco_recomendado,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(FormacaoPreco $formacao): array
    {
        return [
            'processo_item_id' => $formacao->processoItemId,
            'orcamento_id' => $formacao->orcamentoId,
            'orcamento_item_id' => $formacao->orcamentoItemId,
            'custo_produto' => $formacao->custoProduto,
            'frete' => $formacao->frete,
            'percentual_impostos' => $formacao->percentualImpostos,
            'valor_impostos' => $formacao->valorImpostos,
            'percentual_margem' => $formacao->percentualMargem,
            'valor_margem' => $formacao->valorMargem,
            'preco_minimo' => $formacao->precoMinimo,
            'preco_recomendado' => $formacao->precoRecomendado,
            'observacoes' => $formacao->observacoes,
        ];
    }

    public function criar(FormacaoPreco $formacao): FormacaoPreco
    {
        $model = FormacaoPrecoModel::create($this->toArray($formacao));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?FormacaoPreco
    {
        $model = FormacaoPrecoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = FormacaoPrecoModel::query();

        if (isset($filtros['processo_item_id'])) {
            $query->where('processo_item_id', $filtros['processo_item_id']);
        }

        if (isset($filtros['orcamento_id'])) {
            $query->where('orcamento_id', $filtros['orcamento_id']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(FormacaoPreco $formacao): FormacaoPreco
    {
        $model = FormacaoPrecoModel::findOrFail($formacao->id);
        $model->update($this->toArray($formacao));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        FormacaoPrecoModel::findOrFail($id)->delete();
    }

    /**
     * Busca um modelo Eloquent por ID (para Resources do Laravel)
     * Mantém o Global Scope de Empresa ativo para segurança
     */
    public function buscarModeloPorId(int $id, array $with = []): ?FormacaoPrecoModel
    {
        return $this->buscarModeloPorIdInternal($id, $with, false);
    }

    /**
     * Retorna a classe do modelo Eloquent
     */
    protected function getModelClass(): ?string
    {
        return FormacaoPrecoModel::class;
    }
}

