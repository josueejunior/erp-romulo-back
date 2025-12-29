<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Contrato\Entities\Contrato;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Models\Contrato as ContratoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ContratoRepository implements ContratoRepositoryInterface
{
    private function toDomain(ContratoModel $model): Contrato
    {
        return new Contrato(
            id: $model->id,
            empresaId: $model->empresa_id,
            processoId: $model->processo_id,
            numero: $model->numero,
            dataInicio: $model->data_inicio ? Carbon::parse($model->data_inicio) : null,
            dataFim: $model->data_fim ? Carbon::parse($model->data_fim) : null,
            dataAssinatura: $model->data_assinatura ? Carbon::parse($model->data_assinatura) : null,
            valorTotal: (float) $model->valor_total,
            saldo: (float) $model->saldo,
            valorEmpenhado: (float) $model->valor_empenhado,
            condicoesComerciais: $model->condicoes_comerciais,
            condicoesTecnicas: $model->condicoes_tecnicas,
            locaisEntrega: $model->locais_entrega,
            prazosContrato: $model->prazos_contrato,
            regrasContrato: $model->regras_contrato,
            situacao: $model->situacao,
            vigente: $model->vigente ?? true,
            observacoes: $model->observacoes,
            arquivoContrato: $model->arquivo_contrato,
            numeroCte: $model->numero_cte,
        );
    }

    private function toArray(Contrato $contrato): array
    {
        return [
            'empresa_id' => $contrato->empresaId,
            'processo_id' => $contrato->processoId,
            'numero' => $contrato->numero,
            'data_inicio' => $contrato->dataInicio?->toDateString(),
            'data_fim' => $contrato->dataFim?->toDateString(),
            'data_assinatura' => $contrato->dataAssinatura?->toDateString(),
            'valor_total' => $contrato->valorTotal,
            'saldo' => $contrato->saldo,
            'valor_empenhado' => $contrato->valorEmpenhado,
            'condicoes_comerciais' => $contrato->condicoesComerciais,
            'condicoes_tecnicas' => $contrato->condicoesTecnicas,
            'locais_entrega' => $contrato->locaisEntrega,
            'prazos_contrato' => $contrato->prazosContrato,
            'regras_contrato' => $contrato->regrasContrato,
            'situacao' => $contrato->situacao,
            'vigente' => $contrato->vigente,
            'observacoes' => $contrato->observacoes,
            'arquivo_contrato' => $contrato->arquivoContrato,
            'numero_cte' => $contrato->numeroCte,
        ];
    }

    public function criar(Contrato $contrato): Contrato
    {
        $model = ContratoModel::create($this->toArray($contrato));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Contrato
    {
        $model = ContratoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = ContratoModel::query();

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

    public function atualizar(Contrato $contrato): Contrato
    {
        $model = ContratoModel::findOrFail($contrato->id);
        $model->update($this->toArray($contrato));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        ContratoModel::findOrFail($id)->delete();
    }
}

