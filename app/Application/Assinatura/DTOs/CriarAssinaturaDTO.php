<?php

namespace App\Application\Assinatura\DTOs;

use Carbon\Carbon;

/**
 * DTO para criação de assinatura
 */
class CriarAssinaturaDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $planoId,
        public readonly string $status = 'ativa',
        public readonly ?Carbon $dataInicio = null,
        public readonly ?Carbon $dataFim = null,
        public readonly ?float $valorPago = null,
        public readonly ?string $metodoPagamento = null,
        public readonly ?string $transacaoId = null,
        public readonly int $diasGracePeriod = 7,
        public readonly ?string $observacoes = null,
    ) {}

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'] ?? $data['tenantId'] ?? 0,
            planoId: $data['plano_id'] ?? $data['planoId'] ?? 0,
            status: $data['status'] ?? 'ativa',
            dataInicio: isset($data['data_inicio']) ? Carbon::parse($data['data_inicio']) : (isset($data['dataInicio']) ? Carbon::parse($data['dataInicio']) : now()),
            dataFim: isset($data['data_fim']) ? Carbon::parse($data['data_fim']) : (isset($data['dataFim']) ? Carbon::parse($data['dataFim']) : null),
            valorPago: isset($data['valor_pago']) ? (float) $data['valor_pago'] : (isset($data['valorPago']) ? (float) $data['valorPago'] : 0),
            metodoPagamento: $data['metodo_pagamento'] ?? $data['metodoPagamento'] ?? 'gratuito',
            transacaoId: $data['transacao_id'] ?? $data['transacaoId'] ?? null,
            diasGracePeriod: $data['dias_grace_period'] ?? $data['diasGracePeriod'] ?? 7,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

