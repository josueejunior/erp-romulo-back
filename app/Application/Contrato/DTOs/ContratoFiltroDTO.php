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
        return new self(
            busca: $data['busca'] ?? null,
            orgaoId: isset($data['orgao_id']) ? (int) $data['orgao_id'] : null,
            srp: isset($data['srp']) ? (bool) $data['srp'] : null,
            situacao: $data['situacao'] ?? null,
            vigente: isset($data['vigente']) ? (bool) $data['vigente'] : null,
            vencerEm: isset($data['vencer_em']) ? (int) $data['vencer_em'] : null,
            somenteAlerta: isset($data['somente_alerta']) ? (bool) $data['somente_alerta'] : false,
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

