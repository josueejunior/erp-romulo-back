<?php

namespace App\Application\Processo\DTOs;

use Carbon\Carbon;

/**
 * DTO para atualização de processo
 */
class AtualizarProcessoDTO
{
    public function __construct(
        public readonly int $processoId,
        public readonly int $empresaId,
        public readonly ?int $orgaoId = null,
        public readonly ?int $setorId = null,
        public readonly ?string $modalidade = null,
        public readonly ?string $numeroModalidade = null,
        public readonly ?string $numeroProcessoAdministrativo = null,
        public readonly ?string $linkEdital = null,
        public readonly ?string $portal = null,
        public readonly ?string $numeroEdital = null,
        public readonly ?bool $srp = null,
        public readonly ?string $objetoResumido = null,
        public readonly ?Carbon $dataHoraSessaoPublica = null,
        public readonly ?Carbon $horarioSessaoPublica = null,
        public readonly ?string $enderecoEntrega = null,
        public readonly ?string $localEntregaDetalhado = null,
        public readonly ?string $formaEntrega = null,
        public readonly ?string $prazoEntrega = null,
        public readonly ?string $formaPrazoEntrega = null,
        public readonly ?string $prazosDetalhados = null,
        public readonly ?string $prazoPagamento = null,
        public readonly ?string $validadeProposta = null,
        public readonly ?Carbon $validadePropostaInicio = null,
        public readonly ?Carbon $validadePropostaFim = null,
        public readonly ?string $tipoSelecaoFornecedor = null,
        public readonly ?string $tipoDisputa = null,
        public readonly ?string $status = null,
        public readonly ?string $statusParticipacao = null,
        public readonly ?Carbon $dataRecebimentoPagamento = null,
        public readonly ?string $observacoes = null,
    ) {}

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data, int $processoId, int $empresaId): self
    {
        // Processar tipo_selecao_fornecedor e tipo_disputa se vierem como objetos
        $tipoSelecaoFornecedor = $data['tipo_selecao_fornecedor'] ?? $data['tipoSelecaoFornecedor'] ?? null;
        if (is_array($tipoSelecaoFornecedor)) {
            $tipoSelecaoFornecedor = $tipoSelecaoFornecedor['value'] ?? $tipoSelecaoFornecedor['id'] ?? (is_string($tipoSelecaoFornecedor) ? $tipoSelecaoFornecedor : null);
        }

        $tipoDisputa = $data['tipo_disputa'] ?? $data['tipoDisputa'] ?? null;
        if (is_array($tipoDisputa)) {
            $tipoDisputa = $tipoDisputa['value'] ?? $tipoDisputa['id'] ?? (is_string($tipoDisputa) ? $tipoDisputa : null);
        }
        
        return new self(
            processoId: $processoId,
            empresaId: $empresaId,
            orgaoId: $data['orgao_id'] ?? $data['orgaoId'] ?? null,
            setorId: $data['setor_id'] ?? $data['setorId'] ?? null,
            modalidade: $data['modalidade'] ?? null,
            numeroModalidade: $data['numero_modalidade'] ?? $data['numeroModalidade'] ?? null,
            numeroProcessoAdministrativo: $data['numero_processo_administrativo'] ?? $data['numeroProcessoAdministrativo'] ?? null,
            linkEdital: $data['link_edital'] ?? $data['linkEdital'] ?? null,
            portal: $data['portal'] ?? null,
            numeroEdital: $data['numero_edital'] ?? $data['numeroEdital'] ?? null,
            srp: isset($data['srp']) ? (bool) $data['srp'] : null,
            objetoResumido: $data['objeto_resumido'] ?? $data['objetoResumido'] ?? null,
            dataHoraSessaoPublica: isset($data['data_hora_sessao_publica']) && $data['data_hora_sessao_publica'] ? Carbon::parse($data['data_hora_sessao_publica']) : null,
            horarioSessaoPublica: isset($data['horario_sessao_publica']) && $data['horario_sessao_publica'] ? Carbon::parse($data['horario_sessao_publica']) : null,
            enderecoEntrega: $data['endereco_entrega'] ?? $data['enderecoEntrega'] ?? null,
            localEntregaDetalhado: $data['local_entrega_detalhado'] ?? $data['localEntregaDetalhado'] ?? null,
            formaEntrega: $data['forma_entrega'] ?? $data['formaEntrega'] ?? null,
            prazoEntrega: $data['prazo_entrega'] ?? $data['prazoEntrega'] ?? null,
            formaPrazoEntrega: $data['forma_prazo_entrega'] ?? $data['formaPrazoEntrega'] ?? null,
            prazosDetalhados: $data['prazos_detalhados'] ?? $data['prazosDetalhados'] ?? null,
            prazoPagamento: $data['prazo_pagamento'] ?? $data['prazoPagamento'] ?? null,
            validadeProposta: $data['validade_proposta'] ?? $data['validadeProposta'] ?? null,
            validadePropostaInicio: isset($data['validade_proposta_inicio']) && $data['validade_proposta_inicio'] ? Carbon::parse($data['validade_proposta_inicio']) : null,
            validadePropostaFim: isset($data['validade_proposta_fim']) && $data['validade_proposta_fim'] ? Carbon::parse($data['validade_proposta_fim']) : null,
            tipoSelecaoFornecedor: $tipoSelecaoFornecedor,
            tipoDisputa: $tipoDisputa,
            status: $data['status'] ?? null,
            statusParticipacao: $data['status_participacao'] ?? $data['statusParticipacao'] ?? null,
            dataRecebimentoPagamento: isset($data['data_recebimento_pagamento']) && $data['data_recebimento_pagamento'] ? Carbon::parse($data['data_recebimento_pagamento']) : null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

