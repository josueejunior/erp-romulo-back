<?php

namespace App\Application\AutorizacaoFornecimento\DTOs;

use Carbon\Carbon;

/**
 * DTO para criação de autorização de fornecimento
 */
class CriarAutorizacaoFornecimentoDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?int $processoId = null,
        public readonly ?int $contratoId = null,
        public readonly ?string $numero = null,
        public readonly ?Carbon $data = null,
        public readonly ?Carbon $dataAdjudicacao = null,
        public readonly ?Carbon $dataHomologacao = null,
        public readonly ?Carbon $dataFimVigencia = null,
        public readonly ?string $condicoesAf = null,
        public readonly ?string $itensArrematados = null,
        public readonly float $valor = 0.0,
        public readonly ?string $situacao = null,
        public readonly ?string $situacaoDetalhada = null,
        public readonly bool $vigente = true,
        public readonly ?string $observacoes = null,
        public readonly ?string $numeroCte = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            processoId: $data['processo_id'] ?? $data['processoId'] ?? null,
            contratoId: $data['contrato_id'] ?? $data['contratoId'] ?? null,
            numero: $data['numero'] ?? null,
            data: isset($data['data']) ? Carbon::parse($data['data']) : null,
            dataAdjudicacao: isset($data['data_adjudicacao']) ? Carbon::parse($data['data_adjudicacao']) : null,
            dataHomologacao: isset($data['data_homologacao']) ? Carbon::parse($data['data_homologacao']) : null,
            dataFimVigencia: isset($data['data_fim_vigencia']) ? Carbon::parse($data['data_fim_vigencia']) : null,
            condicoesAf: $data['condicoes_af'] ?? $data['condicoesAf'] ?? null,
            itensArrematados: $data['itens_arrematados'] ?? $data['itensArrematados'] ?? null,
            valor: (float) ($data['valor'] ?? 0),
            situacao: $data['situacao'] ?? null,
            situacaoDetalhada: $data['situacao_detalhada'] ?? $data['situacaoDetalhada'] ?? null,
            vigente: $data['vigente'] ?? true,
            observacoes: $data['observacoes'] ?? null,
            numeroCte: $data['numero_cte'] ?? $data['numeroCte'] ?? null,
        );
    }
}


