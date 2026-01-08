<?php

namespace App\Application\Contrato\DTOs;

/**
 * DTO para filtros de busca de contratos
 * 
 * Benefícios:
 * - Tipagem forte
 * - Autocomplete
 * - Validação centralizada
 * - Menos bugs
 */
class ContratoFiltroDTO
{
    public function __construct(
        public readonly ?string $busca = null,
        public readonly ?int $orgaoId = null,
        public readonly ?bool $srp = null,
        public readonly ?string $situacao = null,
        public readonly ?bool $vigente = null,
        public readonly ?int $vencerEm = null,
        public readonly bool $somenteAlerta = false,
    ) {}

    /**
     * Cria DTO a partir de array (request)
     */
    public static function fromArray(array $data): self
    {
        // Booleanos já chegam corretos do controller (usando $request->filled)
        // Converter apenas se necessário para manter compatibilidade
        $srp = $data['srp'] ?? null;
        if ($srp !== null && !is_bool($srp)) {
            $srp = filter_var($srp, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        $vigente = $data['vigente'] ?? null;
        if ($vigente !== null && !is_bool($vigente)) {
            $vigente = filter_var($vigente, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return new self(
            busca: $data['busca'] ?? null,
            orgaoId: isset($data['orgao_id']) && $data['orgao_id'] !== null ? (int) $data['orgao_id'] : null,
            srp: $srp,
            situacao: $data['situacao'] ?? null,
            vigente: $vigente,
            vencerEm: isset($data['vencer_em']) && $data['vencer_em'] !== null ? (int) $data['vencer_em'] : null,
            somenteAlerta: $data['somente_alerta'] ?? false,
        );
    }

    /**
     * Converte para array (para cache key)
     */
    public function toArray(): array
    {
        return [
            'busca' => $this->busca,
            'orgao_id' => $this->orgaoId,
            'srp' => $this->srp,
            'situacao' => $this->situacao,
            'vigente' => $this->vigente,
            'vencer_em' => $this->vencerEm,
            'somente_alerta' => $this->somenteAlerta,
        ];
    }
}

