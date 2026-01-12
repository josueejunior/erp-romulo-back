<?php

namespace App\Application\ProcessoItem\DTOs;

/**
 * DTO para criação de item de processo
 */
class CriarProcessoItemDTO
{
    public function __construct(
        public readonly int $processoId,
        public readonly int $empresaId,
        public readonly ?int $fornecedorId = null,
        public readonly ?int $transportadoraId = null,
        public readonly ?int $numeroItem = null,
        public readonly ?string $codigoInterno = null,
        public readonly float $quantidade = 0.0,
        public readonly ?string $unidade = null,
        public readonly ?string $especificacaoTecnica = null,
        public readonly ?string $marcaModeloReferencia = null,
        public readonly ?string $observacoesEdital = null,
        public readonly bool $exigeAtestado = false,
        public readonly ?float $quantidadeMinimaAtestado = null,
        public readonly ?float $quantidadeAtestadoCapTecnica = null,
        public readonly float $valorEstimado = 0.0,
        public readonly ?string $observacoes = null,
    ) {}

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data, int $processoId, int $empresaId): self
    {
        return new self(
            processoId: $processoId,
            empresaId: $empresaId,
            fornecedorId: isset($data['fornecedor_id']) ? (int) $data['fornecedor_id'] : null,
            transportadoraId: isset($data['transportadora_id']) ? (int) $data['transportadora_id'] : null,
            numeroItem: isset($data['numero_item']) ? (int) $data['numero_item'] : null,
            codigoInterno: $data['codigo_interno'] ?? null,
            quantidade: (float) ($data['quantidade'] ?? 0),
            unidade: $data['unidade'] ?? null,
            especificacaoTecnica: $data['especificacao_tecnica'] ?? null,
            marcaModeloReferencia: $data['marca_modelo_referencia'] ?? null,
            observacoesEdital: $data['observacoes_edital'] ?? null,
            exigeAtestado: isset($data['exige_atestado']) && ($data['exige_atestado'] === true || $data['exige_atestado'] === '1' || $data['exige_atestado'] === 1),
            quantidadeMinimaAtestado: isset($data['quantidade_minima_atestado']) ? (float) $data['quantidade_minima_atestado'] : null,
            quantidadeAtestadoCapTecnica: isset($data['quantidade_atestado_cap_tecnica']) ? (float) $data['quantidade_atestado_cap_tecnica'] : null,
            valorEstimado: (float) ($data['valor_estimado'] ?? 0),
            observacoes: $data['observacoes'] ?? null,
        );
    }
}







