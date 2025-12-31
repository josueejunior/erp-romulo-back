<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Models\Processo as ProcessoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Infrastructure\Persistence\Eloquent\Traits\IsolamentoEmpresaTrait;

/**
 * Implementação do Repository de Processo usando Eloquent
 */
class ProcessoRepository implements ProcessoRepositoryInterface
{
    use IsolamentoEmpresaTrait;
    /**
     * Mapear status antigos do banco para status válidos do domínio
     * 
     * Status antigos (banco): participacao, julgamento_habilitacao, vencido, perdido, execucao, pagamento, encerramento, arquivado
     * Status novos (domínio): rascunho, publicado, em_disputa, julgamento, execucao, vencido, arquivado
     */
    private function mapearStatus(string $statusAntigo): string
    {
        $mapeamento = [
            'participacao' => 'em_disputa',
            'julgamento_habilitacao' => 'julgamento',
            'vencido' => 'vencido',
            'perdido' => 'vencido', // Processo perdido = vencido
            'execucao' => 'execucao',
            'pagamento' => 'execucao', // Pagamento é parte da execução
            'encerramento' => 'execucao', // Encerramento é parte da execução
            'arquivado' => 'arquivado',
            // Status novos (já válidos)
            'rascunho' => 'rascunho',
            'publicado' => 'publicado',
            'em_disputa' => 'em_disputa',
            'julgamento' => 'julgamento',
        ];

        return $mapeamento[$statusAntigo] ?? 'rascunho';
    }

    /**
     * Mapear status do domínio de volta para status do banco
     * 
     * Quando salvamos, precisamos converter status novos para status antigos que o banco aceita
     */
    private function mapearStatusReverso(string $statusDominio): string
    {
        $mapeamento = [
            // Status novos do domínio → status antigos do banco
            'rascunho' => 'participacao', // Rascunho vira participacao (será publicado depois)
            'publicado' => 'participacao', // Publicado vira participacao
            'em_disputa' => 'participacao',
            'julgamento' => 'julgamento_habilitacao',
            'execucao' => 'execucao',
            'vencido' => 'vencido',
            'arquivado' => 'arquivado',
            // Status antigos (mantém como estão)
            'participacao' => 'participacao',
            'julgamento_habilitacao' => 'julgamento_habilitacao',
            'perdido' => 'perdido',
            'pagamento' => 'pagamento',
            'encerramento' => 'encerramento',
        ];

        return $mapeamento[$statusDominio] ?? 'participacao';
    }

    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(ProcessoModel $model): Processo
    {
        // Mapear status antigo para status válido do domínio
        $statusMapeado = $this->mapearStatus($model->status ?? 'rascunho');

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
            status: $statusMapeado,
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
            'status' => $this->mapearStatusReverso($processo->status), // Mapear status do domínio para status do banco
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
        // Aplicar filtro de empresa_id com isolamento
        $query = $this->aplicarFiltroEmpresa(ProcessoModel::class, $filtros);

        if (isset($filtros['status'])) {
            if (is_array($filtros['status'])) {
                $query->whereIn('status', $filtros['status']);
            } else {
                $query->where('status', $filtros['status']);
            }
        }

        if (isset($filtros['data_hora_sessao_publica_inicio'])) {
            $query->where('data_hora_sessao_publica', '>=', $filtros['data_hora_sessao_publica_inicio']);
        }

        if (isset($filtros['data_hora_sessao_publica_fim'])) {
            $query->where('data_hora_sessao_publica', '<=', $filtros['data_hora_sessao_publica_fim']);
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
        
        // Ordenar por data_hora_sessao_publica se for para próximas disputas
        if (isset($filtros['data_hora_sessao_publica_inicio'])) {
            $query->orderBy('data_hora_sessao_publica', 'asc');
        } else {
            $query->orderBy('criado_em', 'desc');
        }
        
        $paginator = $query->paginate($perPage);

        // Validar que todos os registros pertencem à empresa correta
        $this->validarEmpresaIds($paginator, $filtros['empresa_id']);

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
        // Aplicar filtro de empresa_id com isolamento
        $query = $this->aplicarFiltroEmpresa(ProcessoModel::class, $filtros);

        return [
            'total' => $query->count(),
            'por_status' => $query->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
        ];
    }

    /**
     * Buscar modelo Eloquent por ID com relacionamentos (para casos especiais)
     * Use apenas quando realmente necessário (ex: CalendarioService que precisa de relacionamentos)
     */
    public function buscarModeloPorId(int $id, array $with = []): ?ProcessoModel
    {
        $query = ProcessoModel::withoutGlobalScope('empresa');
        
        if (!empty($with)) {
            $query->with($with);
        }
        
        return $query->find($id);
    }

    /**
     * Buscar modelos Eloquent com relacionamentos (para casos especiais)
     * Use apenas quando realmente necessário (ex: CalendarioService)
     */
    public function buscarModelosComFiltros(array $filtros = [], array $with = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->aplicarFiltroEmpresa(ProcessoModel::class, $filtros);

        if (isset($filtros['status'])) {
            if (is_array($filtros['status'])) {
                $query->whereIn('status', $filtros['status']);
            } else {
                $query->where('status', $filtros['status']);
            }
        }

        if (isset($filtros['data_hora_sessao_publica_inicio'])) {
            $query->where('data_hora_sessao_publica', '>=', $filtros['data_hora_sessao_publica_inicio']);
        }

        if (isset($filtros['data_hora_sessao_publica_fim'])) {
            $query->where('data_hora_sessao_publica', '<=', $filtros['data_hora_sessao_publica_fim']);
        }

        if (isset($filtros['status_participacao'])) {
            if (is_array($filtros['status_participacao'])) {
                $query->whereIn('status_participacao', $filtros['status_participacao']);
            } else {
                $query->where('status_participacao', $filtros['status_participacao']);
            }
        }

        // Filtro para processos encerrados (com data de recebimento)
        if (isset($filtros['data_recebimento_pagamento_inicio']) && isset($filtros['data_recebimento_pagamento_fim'])) {
            $query->whereNotNull('data_recebimento_pagamento')
                  ->whereBetween('data_recebimento_pagamento', [
                      $filtros['data_recebimento_pagamento_inicio'],
                      $filtros['data_recebimento_pagamento_fim']
                  ]);
        } elseif (isset($filtros['data_recebimento_pagamento_inicio'])) {
            $query->whereNotNull('data_recebimento_pagamento')
                  ->where('data_recebimento_pagamento', '>=', $filtros['data_recebimento_pagamento_inicio']);
        } elseif (isset($filtros['data_recebimento_pagamento_fim'])) {
            $query->whereNotNull('data_recebimento_pagamento')
                  ->where('data_recebimento_pagamento', '<=', $filtros['data_recebimento_pagamento_fim']);
        }

        // Filtro para processos com itens aceitos
        if (isset($filtros['tem_item_aceito']) && $filtros['tem_item_aceito']) {
            $query->whereHas('itens', function ($q) {
                $q->whereIn('status_item', ['aceito', 'aceito_habilitado']);
            });
        }

        if (!empty($with)) {
            $query->with($with);
        }

        if (isset($filtros['data_hora_sessao_publica_inicio'])) {
            $query->orderBy('data_hora_sessao_publica', 'asc');
        } else {
            $query->orderBy('criado_em', 'desc');
        }

        if (isset($filtros['limit'])) {
            $query->limit($filtros['limit']);
        }

        return $query->get();
    }
}

