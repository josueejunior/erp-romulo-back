<?php

namespace App\Application\Assinatura\DTOs;

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


