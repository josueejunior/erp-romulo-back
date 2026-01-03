<?php

namespace App\Application\Empenho\DTOs;

use Carbon\Carbon;

/**
 * DTO para criação de empenho
 */
class CriarEmpenhoDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?int $processoId = null,
        public readonly ?int $contratoId = null,
        public readonly ?int $autorizacaoFornecimentoId = null,
        public readonly ?string $numero = null,
        public readonly ?Carbon $data = null,
        public readonly ?Carbon $dataRecebimento = null,
        public readonly ?Carbon $prazoEntregaCalculado = null,
        public readonly float $valor = 0.0,
        public readonly ?string $situacao = null,
        public readonly ?string $observacoes = null,
        public readonly ?string $numeroCte = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            processoId: $data['processo_id'] ?? $data['processoId'] ?? null,
            contratoId: $data['contrato_id'] ?? $data['contratoId'] ?? null,
            autorizacaoFornecimentoId: $data['autorizacao_fornecimento_id'] ?? $data['autorizacaoFornecimentoId'] ?? null,
            numero: $data['numero'] ?? null,
            data: isset($data['data']) ? Carbon::parse($data['data']) : null,
            dataRecebimento: isset($data['data_recebimento']) ? Carbon::parse($data['data_recebimento']) : null,
            prazoEntregaCalculado: isset($data['prazo_entrega_calculado']) ? Carbon::parse($data['prazo_entrega_calculado']) : null,
            valor: (float) ($data['valor'] ?? 0),
            situacao: $data['situacao'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            numeroCte: $data['numero_cte'] ?? $data['numeroCte'] ?? null,
        );
    }
}



