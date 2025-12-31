<?php

namespace App\Application\Assinatura\DTOs;

use Carbon\Carbon;

/**
 * DTO para resposta de assinatura (transformação de entidade para resposta JSON)
 */
class AssinaturaResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $planoId,
        public readonly string $status,
        public readonly ?string $dataInicio = null,
        public readonly ?string $dataFim = null,
        public readonly ?float $valorPago = null,
        public readonly ?string $metodoPagamento = null,
        public readonly ?string $transacaoId = null,
        public readonly int $diasRestantes = 0,
        public readonly ?PlanoResponseDTO $plano = null,
    ) {}

    /**
     * Converter para array (para resposta JSON)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'plano_id' => $this->planoId,
            'status' => $this->status,
            'data_inicio' => $this->dataInicio,
            'data_fim' => $this->dataFim,
            'valor_pago' => $this->valorPago,
            'metodo_pagamento' => $this->metodoPagamento,
            'transacao_id' => $this->transacaoId,
            'dias_restantes' => $this->diasRestantes,
            'plano' => $this->plano?->toArray(),
        ];
    }
}

/**
 * DTO para resposta de plano
 */
class PlanoResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly ?string $descricao = null,
        public readonly ?float $precoMensal = null,
        public readonly ?float $precoAnual = null,
        public readonly ?int $limiteProcessos = null,
        public readonly ?int $limiteUsuarios = null,
        public readonly ?int $limiteArmazenamentoMb = null,
    ) {}

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'preco_mensal' => $this->precoMensal,
            'preco_anual' => $this->precoAnual,
            'limite_processos' => $this->limiteProcessos,
            'limite_usuarios' => $this->limiteUsuarios,
            'limite_armazenamento_mb' => $this->limiteArmazenamentoMb,
        ];
    }
}

