<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\NotaFiscal\Entities\NotaFiscal;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Modules\NotaFiscal\Models\NotaFiscal as NotaFiscalModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class NotaFiscalRepository implements NotaFiscalRepositoryInterface
{
    use HasModelRetrieval;
    private function toDomain(NotaFiscalModel $model): NotaFiscal
    {
        return new NotaFiscal(
            id: $model->id,
            empresaId: $model->empresa_id,
            processoId: $model->processo_id,
            empenhoId: $model->empenho_id,
            contratoId: $model->contrato_id,
            autorizacaoFornecimentoId: $model->autorizacao_fornecimento_id,
            tipo: $model->tipo,
            numero: $model->numero,
            serie: $model->serie,
            dataEmissao: $model->data_emissao ? Carbon::parse($model->data_emissao) : null,
            fornecedorId: $model->fornecedor_id,
            transportadora: $model->transportadora,
            numeroCte: $model->numero_cte,
            dataEntregaPrevista: $model->data_entrega_prevista ? Carbon::parse($model->data_entrega_prevista) : null,
            dataEntregaRealizada: $model->data_entrega_realizada ? Carbon::parse($model->data_entrega_realizada) : null,
            situacaoLogistica: $model->situacao_logistica,
            valor: (float) $model->valor,
            custoProduto: (float) $model->custo_produto,
            custoFrete: (float) $model->custo_frete,
            custoTotal: (float) $model->custo_total,
            comprovantePagamento: $model->comprovante_pagamento,
            arquivo: $model->arquivo,
            situacao: $model->situacao,
            dataPagamento: $model->data_pagamento ? Carbon::parse($model->data_pagamento) : null,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(NotaFiscal $notaFiscal): array
    {
        return [
            'empresa_id' => $notaFiscal->empresaId,
            'processo_id' => $notaFiscal->processoId,
            'empenho_id' => $notaFiscal->empenhoId,
            'contrato_id' => $notaFiscal->contratoId,
            'autorizacao_fornecimento_id' => $notaFiscal->autorizacaoFornecimentoId,
            'tipo' => $notaFiscal->tipo,
            'numero' => $notaFiscal->numero,
            'serie' => $notaFiscal->serie,
            'data_emissao' => $notaFiscal->dataEmissao?->toDateString(),
            'fornecedor_id' => $notaFiscal->fornecedorId,
            'transportadora' => $notaFiscal->transportadora,
            'numero_cte' => $notaFiscal->numeroCte,
            'data_entrega_prevista' => $notaFiscal->dataEntregaPrevista?->toDateString(),
            'data_entrega_realizada' => $notaFiscal->dataEntregaRealizada?->toDateString(),
            'situacao_logistica' => $notaFiscal->situacaoLogistica,
            'valor' => $notaFiscal->valor,
            'custo_produto' => $notaFiscal->custoProduto,
            'custo_frete' => $notaFiscal->custoFrete,
            'custo_total' => $notaFiscal->calcularCustoTotal(),
            'comprovante_pagamento' => $notaFiscal->comprovantePagamento,
            'arquivo' => $notaFiscal->arquivo,
            'situacao' => $notaFiscal->situacao,
            'data_pagamento' => $notaFiscal->dataPagamento?->toDateString(),
            'observacoes' => $notaFiscal->observacoes,
        ];
    }

    public function criar(NotaFiscal $notaFiscal): NotaFiscal
    {
        $model = NotaFiscalModel::create($this->toArray($notaFiscal));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?NotaFiscal
    {
        $model = NotaFiscalModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = NotaFiscalModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['empenho_id'])) {
            $query->where('empenho_id', $filtros['empenho_id']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(NotaFiscal $notaFiscal): NotaFiscal
    {
        $model = NotaFiscalModel::findOrFail($notaFiscal->id);
        $model->update($this->toArray($notaFiscal));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        NotaFiscalModel::findOrFail($id)->delete();
    }

    /**
     * Busca um modelo Eloquent por ID (para Resources do Laravel)
     * Mantém o Global Scope de Empresa ativo para segurança
     */
    public function buscarModeloPorId(int $id, array $with = []): ?NotaFiscalModel
    {
        return $this->buscarModeloPorIdInternal($id, $with, false);
    }

    /**
     * Busca notas fiscais por processo com filtros opcionais
     * Retorna array de entidades de domínio
     */
    public function buscarPorProcesso(int $processoId, array $filtros = []): array
    {
        $query = NotaFiscalModel::where('processo_id', $processoId);

        if (isset($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (isset($filtros['situacao'])) {
            $query->where('situacao', $filtros['situacao']);
        }

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        $models = $query->get();

        return $models->map(function ($model) {
            return $this->toDomain($model);
        })->toArray();
    }

    /**
     * Retorna a classe do modelo Eloquent
     */
    protected function getModelClass(): ?string
    {
        return NotaFiscalModel::class;
    }
}

