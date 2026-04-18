<?php

namespace App\Modules\Processo\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Orgao\Resources\OrgaoResource;
use App\Http\Resources\SetorResource;
use Carbon\Carbon;

class ProcessoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Usar ProcessoListResource para calcular stats, valor e alerta
        // Reutilizar a lógica do ProcessoListResource
        $listResource = new ProcessoListResource($this->resource);
        
        // Obter dados base do ProcessoListResource
        $listData = $listResource->toArray($request);
        
        // Formatar data_hora_sessao_publica para o formato esperado pelo frontend (datetime-local)
        $dataHoraFormatada = $this->data_hora_sessao_publica 
            ? $this->data_hora_sessao_publica->format('Y-m-d\TH:i') 
            : null;
        
        // Extrair horário da data_hora_sessao_publica se não tiver horario_sessao_publica separado
        $horarioFormatado = $this->horario_sessao_publica;
        if (!$horarioFormatado && $this->data_hora_sessao_publica) {
            $horarioFormatado = $this->data_hora_sessao_publica->format('H:i');
        } elseif ($horarioFormatado instanceof \DateTime || $horarioFormatado instanceof \Carbon\Carbon) {
            $horarioFormatado = $horarioFormatado->format('H:i');
        }
        
        // Adicionar campos adicionais do ProcessoResource
        return array_merge($listData, [
            'orgao' => new OrgaoResource($this->whenLoaded('orgao')),
            'setor' => new SetorResource($this->whenLoaded('setor')),
            'itens' => ProcessoItemResource::collection($this->whenLoaded('itens')),
            'documentos' => $this->whenLoaded('documentos'),
            // Incluir todos os campos do modelo
            'empresa_id' => $this->empresa_id,
            'setor_id' => $this->setor_id,
            'numero_processo_administrativo' => $this->numero_processo_administrativo,
            'link_edital' => $this->link_edital,
            'portal' => $this->portal,
            'numero_edital' => $this->numero_edital,
            'srp' => $this->srp,
            // Garantir que data_hora_sessao_publica está no formato correto (sobrescreve data_sessao_publica do ProcessoListResource)
            'data_hora_sessao_publica' => $dataHoraFormatada,
            'horario_sessao_publica' => $horarioFormatado,
            'endereco_entrega' => $this->endereco_entrega,
            'local_entrega_detalhado' => $this->local_entrega_detalhado,
            'forma_entrega' => $this->forma_entrega,
            'prazo_entrega' => $this->prazo_entrega,
            'forma_prazo_entrega' => $this->forma_prazo_entrega,
            'prazos_detalhados' => $this->prazos_detalhados,
            'prazo_pagamento' => $this->prazo_pagamento,
            'validade_proposta' => $this->validade_proposta,
            'validade_proposta_inicio' => $this->validade_proposta_inicio?->format('Y-m-d'),
            'validade_proposta_fim' => $this->validade_proposta_fim?->format('Y-m-d'),
            'tipo_selecao_fornecedor' => $this->tipo_selecao_fornecedor,
            'tipo_disputa' => $this->tipo_disputa,
            'status_participacao' => $this->status_participacao,
            'data_recebimento_pagamento' => $this->data_recebimento_pagamento?->format('Y-m-d'),
            'data_arquivamento' => $this->data_arquivamento?->format('Y-m-d H:i:s'),
            'observacoes' => $this->observacoes,
            'motivo_perda' => $this->motivo_perda,
            'criado_em' => $this->criado_em?->format('Y-m-d H:i:s'),
            'atualizado_em' => $this->atualizado_em?->format('Y-m-d H:i:s'),
            'excluido_em' => $this->excluido_em?->format('Y-m-d H:i:s'),
            'identificador' => $this->identificador,
            'nome_empresa' => $this->nome_empresa,
            'validade_proposta_calculada' => $this->validade_proposta_calculada,
        ]);
    }
}
