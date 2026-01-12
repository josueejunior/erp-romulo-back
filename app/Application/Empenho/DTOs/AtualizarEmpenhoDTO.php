<?php

namespace App\Application\Empenho\DTOs;

use Carbon\Carbon;

/**
 * DTO para atualização de empenho
 */
class AtualizarEmpenhoDTO
{
    public function __construct(
        public readonly int $empenhoId,
        public readonly ?int $processoId = null,
        public readonly ?int $contratoId = null,
        public readonly ?int $autorizacaoFornecimentoId = null,
        public readonly ?string $numero = null,
        public readonly ?Carbon $data = null,
        public readonly ?Carbon $dataRecebimento = null,
        public readonly ?Carbon $prazoEntregaCalculado = null,
        public readonly ?float $valor = null,
        public readonly ?string $situacao = null,
        public readonly ?string $observacoes = null,
        public readonly ?string $numeroCte = null,
        public readonly ?Carbon $dataEntrega = null,
    ) {}

    public static function fromArray(array $data, int $empenhoId): self
    {
        return new self(
            empenhoId: $empenhoId,
            processoId: $data['processo_id'] ?? $data['processoId'] ?? null,
            contratoId: $data['contrato_id'] ?? $data['contratoId'] ?? null,
            autorizacaoFornecimentoId: $data['autorizacao_fornecimento_id'] ?? $data['autorizacaoFornecimentoId'] ?? null,
            numero: $data['numero'] ?? null,
            data: isset($data['data']) ? Carbon::parse($data['data']) : null,
            dataRecebimento: isset($data['data_recebimento']) ? Carbon::parse($data['data_recebimento']) : null,
            prazoEntregaCalculado: isset($data['prazo_entrega_calculado']) ? Carbon::parse($data['prazo_entrega_calculado']) : null,
            valor: isset($data['valor']) ? (float) $data['valor'] : null,
            situacao: $data['situacao'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            numeroCte: $data['numero_cte'] ?? $data['numeroCte'] ?? null,
            dataEntrega: isset($data['data_entrega']) ? Carbon::parse($data['data_entrega']) : null,
        );
    }
}







