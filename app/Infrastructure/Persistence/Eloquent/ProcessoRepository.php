<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Models\Processo as ProcessoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Implementação do Repository de Processo usando Eloquent
 */
class ProcessoRepository implements ProcessoRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(ProcessoModel $model): Processo
    {
        return new Processo(
            id: $model->id,
            empresaId: $model->empresa_id,
            orgaoId: $model->orgao_id,
            setorId: $model->setor_id,
            modalidade: $model->modalidade,
            numeroModalidade: $model->numero_modalidade,
            numeroProcessoAdministrativo: $model->numero_processo_administrativo,
            linkEdital: $model->link_edital,
            portal: $model->portal,
            numeroEdital: $model->numero_edital,
            srp: $model->srp ?? false,
            objetoResumido: $model->objeto_resumido,
            dataHoraSessaoPublica: $model->data_hora_sessao_publica ? Carbon::parse($model->data_hora_sessao_publica) : null,
            horarioSessaoPublica: $model->horario_sessao_publica ? Carbon::parse($model->horario_sessao_publica) : null,
            enderecoEntrega: $model->endereco_entrega,
            localEntregaDetalhado: $model->local_entrega_detalhado,
            formaEntrega: $model->forma_entrega,
            prazoEntrega: $model->prazo_entrega,
            formaPrazoEntrega: $model->forma_prazo_entrega,
            prazosDetalhados: $model->prazos_detalhados,
            prazoPagamento: $model->prazo_pagamento,
            validadeProposta: $model->validade_proposta,
            validadePropostaInicio: $model->validade_proposta_inicio ? Carbon::parse($model->validade_proposta_inicio) : null,
            validadePropostaFim: $model->validade_proposta_fim ? Carbon::parse($model->validade_proposta_fim) : null,
            tipoSelecaoFornecedor: $model->tipo_selecao_fornecedor,
            tipoDisputa: $model->tipo_disputa,
            status: $model->status ?? 'rascunho',
            statusParticipacao: $model->status_participacao,
            dataRecebimentoPagamento: $model->data_recebimento_pagamento ? Carbon::parse($model->data_recebimento_pagamento) : null,
            observacoes: $model->observacoes,
            dataArquivamento: $model->data_arquivamento ? Carbon::parse($model->data_arquivamento) : null,
        );
    }

    /**
     * Converter entidade do domínio para array do Eloquent
     */
    private function toArray(Processo $processo): array
    {
        return [
            'empresa_id' => $processo->empresaId,
            'orgao_id' => $processo->orgaoId,
            'setor_id' => $processo->setorId,
            'modalidade' => $processo->modalidade,
            'numero_modalidade' => $processo->numeroModalidade,
            'numero_processo_administrativo' => $processo->numeroProcessoAdministrativo,
            'link_edital' => $processo->linkEdital,
            'portal' => $processo->portal,
            'numero_edital' => $processo->numeroEdital,
            'srp' => $processo->srp,
            'objeto_resumido' => $processo->objetoResumido,
            'data_hora_sessao_publica' => $processo->dataHoraSessaoPublica?->toDateTimeString(),
            'horario_sessao_publica' => $processo->horarioSessaoPublica?->toDateTimeString(),
            'endereco_entrega' => $processo->enderecoEntrega,
            'local_entrega_detalhado' => $processo->localEntregaDetalhado,
            'forma_entrega' => $processo->formaEntrega,
            'prazo_entrega' => $processo->prazoEntrega,
            'forma_prazo_entrega' => $processo->formaPrazoEntrega,
            'prazos_detalhados' => $processo->prazosDetalhados,
            'prazo_pagamento' => $processo->prazoPagamento,
            'validade_proposta' => $processo->validadeProposta,
            'validade_proposta_inicio' => $processo->validadePropostaInicio?->toDateString(),
            'validade_proposta_fim' => $processo->validadePropostaFim?->toDateString(),
            'tipo_selecao_fornecedor' => $processo->tipoSelecaoFornecedor,
            'tipo_disputa' => $processo->tipoDisputa,
            'status' => $processo->status,
            'status_participacao' => $processo->statusParticipacao,
            'data_recebimento_pagamento' => $processo->dataRecebimentoPagamento?->toDateString(),
            'observacoes' => $processo->observacoes,
            'data_arquivamento' => $processo->dataArquivamento?->toDateTimeString(),
        ];
    }

    public function criar(Processo $processo): Processo
    {
        $model = ProcessoModel::create($this->toArray($processo));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?Processo
    {
        $model = ProcessoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = ProcessoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('numero_modalidade', 'ilike', "%{$search}%")
                  ->orWhere('objeto_resumido', 'ilike', "%{$search}%")
                  ->orWhere('numero_processo_administrativo', 'ilike', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        // Converter cada item para entidade do domínio
        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Processo $processo): Processo
    {
        $model = ProcessoModel::findOrFail($processo->id);
        $model->update($this->toArray($processo));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        ProcessoModel::findOrFail($id)->delete();
    }

    public function obterResumo(array $filtros = []): array
    {
        $query = ProcessoModel::query();

        if (isset($filtros['empresa_id'])) {
            $query->where('empresa_id', $filtros['empresa_id']);
        }

        return [
            'total' => $query->count(),
            'por_status' => $query->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
        ];
    }
}

