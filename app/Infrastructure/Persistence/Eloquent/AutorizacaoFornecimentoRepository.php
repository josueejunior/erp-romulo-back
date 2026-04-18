<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\AutorizacaoFornecimento\Entities\AutorizacaoFornecimento;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento as AutorizacaoFornecimentoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Infrastructure\Persistence\Eloquent\Traits\HasModelRetrieval;

class AutorizacaoFornecimentoRepository implements AutorizacaoFornecimentoRepositoryInterface
{
    use HasModelRetrieval;
    private function toDomain(AutorizacaoFornecimentoModel $model): AutorizacaoFornecimento
    {
        return new AutorizacaoFornecimento(
            id: $model->id,
            empresaId: $model->empresa_id,
            processoId: $model->processo_id,
            contratoId: $model->contrato_id,
            numero: $model->numero,
            data: $model->data ? Carbon::parse($model->data) : null,
            dataAdjudicacao: $model->data_adjudicacao ? Carbon::parse($model->data_adjudicacao) : null,
            dataHomologacao: $model->data_homologacao ? Carbon::parse($model->data_homologacao) : null,
            dataFimVigencia: $model->data_fim_vigencia ? Carbon::parse($model->data_fim_vigencia) : null,
            condicoesAf: $model->condicoes_af,
            itensArrematados: $model->itens_arrematados,
            valor: (float) $model->valor,
            saldo: (float) $model->saldo,
            valorEmpenhado: (float) $model->valor_empenhado,
            situacao: $model->situacao,
            situacaoDetalhada: $model->situacao_detalhada,
            vigente: $model->vigente ?? true,
            observacoes: $model->observacoes,
            numeroCte: $model->numero_cte,
        );
    }

    private function toArray(AutorizacaoFornecimento $autorizacao): array
    {
        return [
            'empresa_id' => $autorizacao->empresaId,
            'processo_id' => $autorizacao->processoId,
            'contrato_id' => $autorizacao->contratoId,
            'numero' => $autorizacao->numero,
            'data' => $autorizacao->data?->toDateString(),
            'data_adjudicacao' => $autorizacao->dataAdjudicacao?->toDateString(),
            'data_homologacao' => $autorizacao->dataHomologacao?->toDateString(),
            'data_fim_vigencia' => $autorizacao->dataFimVigencia?->toDateString(),
            'condicoes_af' => $autorizacao->condicoesAf,
            'itens_arrematados' => $autorizacao->itensArrematados,
            'valor' => $autorizacao->valor,
            'saldo' => $autorizacao->saldo,
            'valor_empenhado' => $autorizacao->valorEmpenhado,
            'situacao' => $autorizacao->situacao,
            'situacao_detalhada' => $autorizacao->situacaoDetalhada,
            'vigente' => $autorizacao->vigente,
            'observacoes' => $autorizacao->observacoes,
            'numero_cte' => $autorizacao->numeroCte,
        ];
    }

    public function criar(AutorizacaoFornecimento $autorizacao): AutorizacaoFornecimento
    {
        $model = AutorizacaoFornecimentoModel::create($this->toArray($autorizacao));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?AutorizacaoFornecimento
    {
        $model = AutorizacaoFornecimentoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = AutorizacaoFornecimentoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['processo_id'])) {
            $query->where('processo_id', $filtros['processo_id']);
        }

        if (isset($filtros['contrato_id'])) {
            $query->where('contrato_id', $filtros['contrato_id']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(AutorizacaoFornecimento $autorizacao): AutorizacaoFornecimento
    {
        $model = AutorizacaoFornecimentoModel::findOrFail($autorizacao->id);
        $model->update($this->toArray($autorizacao));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $model = AutorizacaoFornecimentoModel::findOrFail($id);
            // Deletar vínculos de itens associados a esta AF
            \App\Modules\Processo\Models\ProcessoItemVinculo::where('autorizacao_fornecimento_id', $id)->delete();
            $model->delete();
        });
    }

    /**
     * Busca um modelo Eloquent por ID (para Resources do Laravel)
     * Mantém o Global Scope de Empresa ativo para segurança
     */
    public function buscarModeloPorId(int $id, array $with = []): ?AutorizacaoFornecimentoModel
    {
        return $this->buscarModeloPorIdInternal($id, $with, false);
    }

    /**
     * Retorna a classe do modelo Eloquent
     */
    protected function getModelClass(): ?string
    {
        return AutorizacaoFornecimentoModel::class;
    }
}




