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
            id: null,
            numeroProcesso: $dados['numero_processo'] ?? '',
            modalidade: $dados['modalidade'] ?? null,
            objeto: $dados['objeto'] ?? null,
            dataAbertura: isset($dados['data_abertura']) ? Carbon::parse($dados['data_abertura']) : null,
            dataEncerramento: isset($dados['data_encerramento']) ? Carbon::parse($dados['data_encerramento']) : null,
            dataHoraSessaoPublica: isset($dados['data_hora_sessao_publica']) ? Carbon::parse($dados['data_hora_sessao_publica']) : null,
            status: $dados['status'] ?? 'rascunho',
            orgaoId: $dados['orgao_id'] ?? null,
            setorId: $dados['setor_id'] ?? null,
            empresaId: $dados['empresa_id'] ?? null,
            formaEntrega: $dados['forma_entrega'] ?? null,
            prazoEntrega: $dados['prazo_entrega'] ?? null,
            tipoPrazoEntrega: $dados['tipo_prazo_entrega'] ?? null,
            observacoes: $dados['observacoes'] ?? null,
        );
    }
    
    /**
     * Criar Processo para testes
     */
    public static function criarParaTeste(array $dados = []): Processo
    {
        $dadosPadrao = [
            'numero_processo' => 'PROC-' . time(),
            'modalidade' => 'pregão',
            'objeto' => 'Objeto de teste',
            'status' => 'rascunho',
            'orgao_id' => 1,
            'setor_id' => 1,
            'empresa_id' => 1,
        ];
        
        return self::criar(array_merge($dadosPadrao, $dados));
    }
}

