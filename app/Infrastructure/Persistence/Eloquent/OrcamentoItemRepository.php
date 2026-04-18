<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\OrcamentoItem\Entities\OrcamentoItem;
use App\Domain\OrcamentoItem\Repositories\OrcamentoItemRepositoryInterface;
use App\Modules\Orcamento\Models\OrcamentoItem as OrcamentoItemModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class OrcamentoItemRepository implements OrcamentoItemRepositoryInterface
{
    use HasModelRetrieval;
    private function toDomain(OrcamentoItemModel $model): OrcamentoItem
    {
        return new OrcamentoItem(
            id: $model->id,
            empresaId: $model->empresa_id,
            orcamentoId: $model->orcamento_id,
            processoItemId: $model->processo_item_id,
            custoProduto: (float) $model->custo_produto,
            marcaModelo: $model->marca_modelo,
            ajustesEspecificacao: $model->ajustes_especificacao,
            frete: (float) $model->frete,
            freteIncluido: (bool) $model->frete_incluido,
            fornecedorEscolhido: (bool) $model->fornecedor_escolhido,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(OrcamentoItem $orcamentoItem): array
    {
        return [
            'empresa_id' => $orcamentoItem->empresaId,
            'orcamento_id' => $orcamentoItem->orcamentoId,
            'processo_item_id' => $orcamentoItem->processoItemId,
            'custo_produto' => $orcamentoItem->custoProduto,
            'marca_modelo' => $orcamentoItem->marcaModelo,
            'ajustes_especificacao' => $orcamentoItem->ajustesEspecificacao,
            'frete' => $orcamentoItem->frete,
            'frete_incluido' => $orcamentoItem->freteIncluido,
            'fornecedor_escolhido' => $orcamentoItem->fornecedorEscolhido,
            'observacoes' => $orcamentoItem->observacoes,
        ];
    }

    public function criar(OrcamentoItem $orcamentoItem): OrcamentoItem
    {
        $model = OrcamentoItemModel::create($this->toArray($orcamentoItem));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?OrcamentoItem
    {
        $model = OrcamentoItemModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarModeloPorId(int $id, array $with = []): ?OrcamentoItemModel
    {
        return $this->getModel($id, $with);
    }

    public function buscarPorOrcamento(int $orcamentoId): array
    {
        $models = OrcamentoItemModel::where('orcamento_id', $orcamentoId)->get();
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }

    public function buscarPorProcessoItem(int $processoItemId): array
    {
        $models = OrcamentoItemModel::where('processo_item_id', $processoItemId)->get();
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = OrcamentoItemModel::query();

        if (isset($filtros['orcamento_id'])) {
            $query->where('orcamento_id', $filtros['orcamento_id']);
        }

        if (isset($filtros['processo_item_id'])) {
            $query->where('processo_item_id', $filtros['processo_item_id']);
        }

        if (isset($filtros['fornecedor_escolhido'])) {
            $query->where('fornecedor_escolhido', $filtros['fornecedor_escolhido']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(OrcamentoItem $orcamentoItem): OrcamentoItem
    {
        $model = OrcamentoItemModel::findOrFail($orcamentoItem->id);
        $model->update($this->toArray($orcamentoItem));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        OrcamentoItemModel::findOrFail($id)->delete();
    }

    public function marcarComoEscolhido(int $id): OrcamentoItem
    {
        $model = OrcamentoItemModel::findOrFail($id);
        
        // Desmarcar outros itens do mesmo processo_item
        $this->desmarcarEscolhido($model->orcamento_id, $model->processo_item_id);
        
        // Marcar este como escolhido
        $model->update(['fornecedor_escolhido' => true]);
        
        return $this->toDomain($model->fresh());
    }

    public function desmarcarEscolhido(int $orcamentoId, int $processoItemId): void
    {
        OrcamentoItemModel::where('processo_item_id', $processoItemId)
            ->where('id', '!=', $orcamentoId)
            ->update(['fornecedor_escolhido' => false]);
    }

    /**
     * Retorna a classe do modelo Eloquent (requerido pelo trait HasModelRetrieval)
     */
    protected function getModelClass(): ?string
    {
        return OrcamentoItemModel::class;
    }
}


