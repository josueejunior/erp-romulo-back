<?php

namespace App\Application\Contrato\DTOs;

use Carbon\Carbon;

/**
 * DTO para criação de contrato
 */
class CriarContratoDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?int $processoId = null,
        public readonly ?string $numero = null,
        public readonly ?Carbon $dataInicio = null,
        public readonly ?Carbon $dataFim = null,
        public readonly ?Carbon $dataAssinatura = null,
        public readonly float $valorTotal = 0.0,
        public readonly ?string $condicoesComerciais = null,
        public readonly ?string $condicoesTecnicas = null,
        public readonly ?string $locaisEntrega = null,
        public readonly ?string $prazosContrato = null,
        public readonly ?string $regrasContrato = null,
        public readonly ?string $situacao = null,
        public readonly bool $vigente = true,
        public readonly ?string $observacoes = null,
        public readonly ?string $arquivoContrato = null,
        public readonly ?string $numeroCte = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            empresaId: $data['empresa_id'] ?? $data['empresaId'] ?? 0,
            processoId: $data['processo_id'] ?? $data['processoId'] ?? null,
            numero: $data['numero'] ?? null,
            dataInicio: isset($data['data_inicio']) ? Carbon::parse($data['data_inicio']) : null,
            dataFim: isset($data['data_fim']) ? Carbon::parse($data['data_fim']) : null,
            dataAssinatura: isset($data['data_assinatura']) ? Carbon::parse($data['data_assinatura']) : null,
            valorTotal: (float) ($data['valor_total'] ?? $data['valorTotal'] ?? 0),
            condicoesComerciais: $data['condicoes_comerciais'] ?? $data['condicoesComerciais'] ?? null,
            condicoesTecnicas: $data['condicoes_tecnicas'] ?? $data['condicoesTecnicas'] ?? null,
            locaisEntrega: $data['locais_entrega'] ?? $data['locaisEntrega'] ?? null,
            prazosContrato: $data['prazos_contrato'] ?? $data['prazosContrato'] ?? null,
            regrasContrato: $data['regras_contrato'] ?? $data['regrasContrato'] ?? null,
            situacao: $data['situacao'] ?? null,
            vigente: $data['vigente'] ?? true,
            observacoes: $data['observacoes'] ?? null,
            arquivoContrato: $data['arquivo_contrato'] ?? $data['arquivoContrato'] ?? null,
            numeroCte: $data['numero_cte'] ?? $data['numeroCte'] ?? null,
        );
    }
}


