<?php

namespace App\Application\ProcessoItem\DTOs;

/**
 * DTO para atualização de item de processo
 */
class AtualizarProcessoItemDTO
{
    /**
     * ✅ CORRIGIDO: Armazena quais campos foram realmente enviados no request
     * Isso permite distinguir entre "campo não enviado" e "campo enviado como null/vazio"
     */
    public readonly array $camposEnviados;

    public function __construct(
        public readonly int $processoItemId,
        public readonly int $processoId,
        public readonly int $empresaId,
        public readonly ?int $fornecedorId = null,
        public readonly ?int $transportadoraId = null,
        public readonly ?int $numeroItem = null,
        public readonly ?string $codigoInterno = null,
        public readonly ?float $quantidade = null,
        public readonly ?string $unidade = null,
        public readonly ?string $especificacaoTecnica = null,
        public readonly ?string $marcaModeloReferencia = null,
        public readonly ?string $observacoesEdital = null,
        public readonly ?bool $exigeAtestado = null,
        public readonly ?float $quantidadeMinimaAtestado = null,
        public readonly ?float $quantidadeAtestadoCapTecnica = null,
        public readonly ?float $valorEstimado = null,
        public readonly ?string $observacoes = null,
        array $camposEnviados = [],
    ) {
        $this->camposEnviados = $camposEnviados;
    }

    /**
     * Verificar se um campo foi enviado no request
     */
    public function campoFoiEnviado(string $campo): bool
    {
        return in_array($campo, $this->camposEnviados);
    }

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data, int $processoItemId, int $processoId, int $empresaId): self
    {
        // ✅ CORRIGIDO: Rastrear quais campos foram realmente enviados no request
        $camposEnviados = array_keys($data);

        return new self(
            processoItemId: $processoItemId,
            processoId: $processoId,
            empresaId: $empresaId,
            fornecedorId: isset($data['fornecedor_id']) ? (int) $data['fornecedor_id'] : null,
            transportadoraId: isset($data['transportadora_id']) ? (int) $data['transportadora_id'] : null,
            numeroItem: isset($data['numero_item']) ? (int) $data['numero_item'] : null,
            codigoInterno: array_key_exists('codigo_interno', $data) ? ($data['codigo_interno'] ?: null) : null,
            quantidade: isset($data['quantidade']) ? (float) $data['quantidade'] : null,
            unidade: array_key_exists('unidade', $data) ? ($data['unidade'] ?: null) : null,
            // ✅ CRÍTICO: Preservar string vazia se foi explicitamente enviada (permite limpar campo)
            especificacaoTecnica: array_key_exists('especificacao_tecnica', $data) 
                ? (string) $data['especificacao_tecnica'] 
                : null,
            marcaModeloReferencia: array_key_exists('marca_modelo_referencia', $data) ? ($data['marca_modelo_referencia'] ?: null) : null,
            observacoesEdital: $data['observacoes_edital'] ?? null,
            exigeAtestado: isset($data['exige_atestado']) ? (bool) $data['exige_atestado'] : null,
            quantidadeMinimaAtestado: isset($data['quantidade_minima_atestado']) ? (float) $data['quantidade_minima_atestado'] : null,
            quantidadeAtestadoCapTecnica: isset($data['quantidade_atestado_cap_tecnica']) ? (float) $data['quantidade_atestado_cap_tecnica'] : null,
            valorEstimado: isset($data['valor_estimado']) ? (float) $data['valor_estimado'] : null,
            observacoes: $data['observacoes'] ?? null,
            camposEnviados: $camposEnviados,
        );
    }
}









