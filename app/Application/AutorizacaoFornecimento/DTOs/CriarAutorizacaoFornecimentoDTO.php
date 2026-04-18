<?php

namespace App\Application\AutorizacaoFornecimento\DTOs;

use App\Domain\AutorizacaoFornecimento\Enums\SituacaoAutorizacaoFornecimento;
use App\Domain\AutorizacaoFornecimento\Enums\SituacaoDetalhadaAutorizacaoFornecimento;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * DTO para criação de autorização de fornecimento
 * 
 * 🔥 ARQUITETURA LIMPA:
 * - Fail-fast: rejeita dados inválidos imediatamente
 * - Tipos fortes: usa Enums para valores fechados
 * - Parse seguro: datas com tratamento de erro
 * - Valores default explícitos: não aceita estados impossíveis
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
        public readonly ?SituacaoAutorizacaoFornecimento $situacao = null,
        public readonly ?SituacaoDetalhadaAutorizacaoFornecimento $situacaoDetalhada = null,
        public readonly bool $vigente = true,
        public readonly ?string $observacoes = null,
        public readonly ?string $numeroCte = null,
    ) {}

    /**
     * Criar DTO a partir de array
     * 
     * 🔥 FAIL-FAST: Rejeita dados inválidos imediatamente
     * 
     * @param array $data Dados do request
     * @return self
     * @throws InvalidArgumentException Se dados obrigatórios estiverem ausentes ou inválidos
     */
    public static function fromArray(array $data): self
    {
        // 🔥 FAIL-FAST: Validar empresaId obrigatório
        $empresaId = $data['empresa_id'] ?? $data['empresaId'] ?? null;
        if (empty($empresaId) || !is_numeric($empresaId) || (int) $empresaId <= 0) {
            throw new InvalidArgumentException('empresa_id é obrigatório e deve ser um número positivo.');
        }
        $empresaId = (int) $empresaId;

        // Validar campos obrigatórios
        if (empty($data['numero'])) {
            throw new InvalidArgumentException('numero é obrigatório.');
        }
        if (empty($data['data'])) {
            throw new InvalidArgumentException('data é obrigatória.');
        }

        // Parse seguro de datas
        $dataObj = self::parseDate($data['data'] ?? null);
        $dataAdjudicacao = self::parseDate($data['data_adjudicacao'] ?? $data['dataAdjudicacao'] ?? null);
        $dataHomologacao = self::parseDate($data['data_homologacao'] ?? $data['dataHomologacao'] ?? null);
        $dataFimVigencia = self::parseDate($data['data_fim_vigencia'] ?? $data['dataFimVigencia'] ?? null);

        // Validar e converter situacao (enum)
        $situacao = null;
        if (isset($data['situacao']) && $data['situacao'] !== null) {
            $situacaoStr = (string) $data['situacao'];
            try {
                $situacao = SituacaoAutorizacaoFornecimento::from($situacaoStr);
            } catch (\ValueError $e) {
                throw new InvalidArgumentException(
                    "situacao inválida: '{$situacaoStr}'. Valores permitidos: " . 
                    implode(', ', array_map(fn($case) => $case->value, SituacaoAutorizacaoFornecimento::cases()))
                );
            }
        }

        // Validar e converter situacao_detalhada (enum)
        $situacaoDetalhada = null;
        if (isset($data['situacao_detalhada']) || isset($data['situacaoDetalhada'])) {
            $situacaoDetalhadaStr = (string) ($data['situacao_detalhada'] ?? $data['situacaoDetalhada']);
            try {
                $situacaoDetalhada = SituacaoDetalhadaAutorizacaoFornecimento::from($situacaoDetalhadaStr);
            } catch (\ValueError $e) {
                throw new InvalidArgumentException(
                    "situacao_detalhada inválida: '{$situacaoDetalhadaStr}'. Valores permitidos: " . 
                    implode(', ', array_map(fn($case) => $case->value, SituacaoDetalhadaAutorizacaoFornecimento::cases()))
                );
            }
        }

        // Valor default explícito para vigente
        $vigente = true; // Default
        if (array_key_exists('vigente', $data)) {
            $vigente = filter_var($data['vigente'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($vigente === null) {
                throw new InvalidArgumentException('vigente deve ser um valor booleano.');
            }
        }

        // Validar valor não negativo
        $valor = (float) ($data['valor'] ?? 0);
        if ($valor < 0) {
            throw new InvalidArgumentException('valor não pode ser negativo.');
        }

        return new self(
            empresaId: $empresaId,
            processoId: self::parseIntOrNull($data['processo_id'] ?? $data['processoId'] ?? null),
            contratoId: self::parseIntOrNull($data['contrato_id'] ?? $data['contratoId'] ?? null),
            numero: $data['numero'] ?? null,
            data: $dataObj,
            dataAdjudicacao: $dataAdjudicacao,
            dataHomologacao: $dataHomologacao,
            dataFimVigencia: $dataFimVigencia,
            condicoesAf: $data['condicoes_af'] ?? $data['condicoesAf'] ?? null,
            itensArrematados: $data['itens_arrematados'] ?? $data['itensArrematados'] ?? null,
            valor: $valor,
            situacao: $situacao,
            situacaoDetalhada: $situacaoDetalhada,
            vigente: $vigente,
            observacoes: $data['observacoes'] ?? null,
            numeroCte: $data['numero_cte'] ?? $data['numeroCte'] ?? null,
        );
    }

    /**
     * Parse seguro de data
     * 
     * @param mixed $value Valor a ser parseado
     * @return Carbon|null
     * @throws InvalidArgumentException Se a data for inválida
     */
    private static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Data inválida: '{$value}'. Erro: " . $e->getMessage());
        }
    }

    /**
     * Parse seguro de inteiro ou null
     * 
     * Aceita apenas valores numéricos positivos (IDs válidos)
     * 
     * @param mixed $value Valor a ser parseado
     * @return int|null
     * @throws InvalidArgumentException Se o valor não for numérico válido ou for <= 0
     */
    private static function parseIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("ID deve ser numérico, recebido: " . gettype($value) . " ({$value})");
        }

        $intValue = (int) $value;
        if ($intValue <= 0) {
            throw new InvalidArgumentException("ID deve ser um número positivo maior que zero, recebido: {$value}");
        }

        return $intValue;
    }
}




