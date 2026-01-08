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
        // Tratar strings vazias como null
        $busca = !empty($data['busca']) ? $data['busca'] : null;
        $orgaoId = !empty($data['orgao_id']) ? (int) $data['orgao_id'] : null;
        $srp = isset($data['srp']) && $data['srp'] !== '' ? filter_var($data['srp'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $situacao = !empty($data['situacao']) ? $data['situacao'] : null;
        $vigente = isset($data['vigente']) && $data['vigente'] !== '' ? filter_var($data['vigente'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $vencerEm = !empty($data['vencer_em']) ? (int) $data['vencer_em'] : null;
        $somenteAlerta = isset($data['somente_alerta']) ? filter_var($data['somente_alerta'], FILTER_VALIDATE_BOOLEAN) : false;

        return new self(
            busca: $busca,
            orgaoId: $orgaoId,
            srp: $srp,
            situacao: $situacao,
            vigente: $vigente,
            vencerEm: $vencerEm,
            somenteAlerta: $somenteAlerta,
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

