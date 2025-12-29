<?php

namespace App\Domain\Factories;

use App\Domain\Processo\Entities\Processo;
use Carbon\Carbon;

/**
 * Factory para criar entidades Processo
 */
class ProcessoFactory
{
    /**
     * Criar Processo a partir de array de dados
     */
    public static function criar(array $dados): Processo
    {
        return new Processo(
            id: $dados['id'] ?? null,
            empresaId: $dados['empresa_id'] ?? 1,
            orgaoId: $dados['orgao_id'] ?? null,
            setorId: $dados['setor_id'] ?? null,
            modalidade: $dados['modalidade'] ?? null,
            numeroModalidade: $dados['numero_modalidade'] ?? null,
            numeroProcessoAdministrativo: $dados['numero_processo_administrativo'] ?? null,
            linkEdital: $dados['link_edital'] ?? null,
            portal: $dados['portal'] ?? null,
            numeroEdital: $dados['numero_edital'] ?? null,
            srp: $dados['srp'] ?? false,
            objetoResumido: $dados['objeto_resumido'] ?? null,
            dataHoraSessaoPublica: isset($dados['data_hora_sessao_publica']) ? Carbon::parse($dados['data_hora_sessao_publica']) : null,
            horarioSessaoPublica: isset($dados['horario_sessao_publica']) ? Carbon::parse($dados['horario_sessao_publica']) : null,
            enderecoEntrega: $dados['endereco_entrega'] ?? null,
            localEntregaDetalhado: $dados['local_entrega_detalhado'] ?? null,
            formaEntrega: $dados['forma_entrega'] ?? null,
            prazoEntrega: $dados['prazo_entrega'] ?? null,
            formaPrazoEntrega: $dados['forma_prazo_entrega'] ?? null,
            prazosDetalhados: $dados['prazos_detalhados'] ?? null,
            prazoPagamento: $dados['prazo_pagamento'] ?? null,
            validadeProposta: $dados['validade_proposta'] ?? null,
            validadePropostaInicio: isset($dados['validade_proposta_inicio']) ? Carbon::parse($dados['validade_proposta_inicio']) : null,
            validadePropostaFim: isset($dados['validade_proposta_fim']) ? Carbon::parse($dados['validade_proposta_fim']) : null,
            tipoSelecaoFornecedor: $dados['tipo_selecao_fornecedor'] ?? null,
            tipoDisputa: $dados['tipo_disputa'] ?? null,
            status: $dados['status'] ?? 'rascunho',
            statusParticipacao: $dados['status_participacao'] ?? null,
            dataRecebimentoPagamento: isset($dados['data_recebimento_pagamento']) ? Carbon::parse($dados['data_recebimento_pagamento']) : null,
            observacoes: $dados['observacoes'] ?? null,
            dataArquivamento: isset($dados['data_arquivamento']) ? Carbon::parse($dados['data_arquivamento']) : null,
        );
    }
    
    /**
     * Criar Processo para testes
     */
    public static function criarParaTeste(array $dados = []): Processo
    {
        $dadosPadrao = [
            'modalidade' => 'pregão',
            'objeto_resumido' => 'Objeto de teste',
            'status' => 'rascunho',
            'orgao_id' => 1,
            'setor_id' => 1,
            'empresa_id' => 1,
        ];
        
        return self::criar(array_merge($dadosPadrao, $dados));
    }
}

