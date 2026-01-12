<?php

namespace App\Application\NotaFiscal\DTOs;

use Carbon\Carbon;

/**
 * DTO para atualização de nota fiscal
 */
class AtualizarNotaFiscalDTO
{
    public function __construct(
        public readonly int $notaFiscalId,
        public readonly ?int $processoId = null,
        public readonly ?int $processoItemId = null,
        public readonly ?int $empenhoId = null,
        public readonly ?int $contratoId = null,
        public readonly ?int $autorizacaoFornecimentoId = null,
        public readonly ?string $tipo = null,
        public readonly ?string $numero = null,
        public readonly ?string $serie = null,
        public readonly ?Carbon $dataEmissao = null,
        public readonly ?int $fornecedorId = null,
        public readonly ?string $transportadora = null,
        public readonly ?string $numeroCte = null,
        public readonly ?Carbon $dataEntregaPrevista = null,
        public readonly ?Carbon $dataEntregaRealizada = null,
        public readonly ?string $situacaoLogistica = null,
        public readonly ?float $valor = null,
        public readonly ?float $custoProduto = null,
        public readonly ?float $custoFrete = null,
        public readonly ?string $comprovantePagamento = null,
        public readonly ?string $arquivo = null,
        public readonly ?string $situacao = null,
        public readonly ?Carbon $dataPagamento = null,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data, int $notaFiscalId): self
    {
        return new self(
            notaFiscalId: $notaFiscalId,
            processoId: $data['processo_id'] ?? $data['processoId'] ?? null,
            processoItemId: $data['processo_item_id'] ?? $data['processoItemId'] ?? null,
            empenhoId: $data['empenho_id'] ?? $data['empenhoId'] ?? null,
            contratoId: $data['contrato_id'] ?? $data['contratoId'] ?? null,
            autorizacaoFornecimentoId: $data['autorizacao_fornecimento_id'] ?? $data['autorizacaoFornecimentoId'] ?? null,
            tipo: $data['tipo'] ?? null,
            numero: $data['numero'] ?? null,
            serie: $data['serie'] ?? null,
            dataEmissao: isset($data['data_emissao']) ? Carbon::parse($data['data_emissao']) : null,
            fornecedorId: $data['fornecedor_id'] ?? $data['fornecedorId'] ?? null,
            transportadora: $data['transportadora'] ?? null,
            numeroCte: $data['numero_cte'] ?? $data['numeroCte'] ?? null,
            dataEntregaPrevista: isset($data['data_entrega_prevista']) ? Carbon::parse($data['data_entrega_prevista']) : null,
            dataEntregaRealizada: isset($data['data_entrega_realizada']) ? Carbon::parse($data['data_entrega_realizada']) : null,
            situacaoLogistica: $data['situacao_logistica'] ?? $data['situacaoLogistica'] ?? null,
            valor: isset($data['valor']) ? (float) $data['valor'] : null,
            custoProduto: isset($data['custo_produto']) || isset($data['custoProduto']) ? (float) ($data['custo_produto'] ?? $data['custoProduto'] ?? 0) : null,
            custoFrete: isset($data['custo_frete']) || isset($data['custoFrete']) ? (float) ($data['custo_frete'] ?? $data['custoFrete'] ?? 0) : null,
            comprovantePagamento: $data['comprovante_pagamento'] ?? $data['comprovantePagamento'] ?? null,
            arquivo: $data['arquivo'] ?? null,
            situacao: $data['situacao'] ?? null,
            dataPagamento: isset($data['data_pagamento']) ? Carbon::parse($data['data_pagamento']) : null,
            observacoes: $data['observacoes'] ?? null,
        );
    }
}







