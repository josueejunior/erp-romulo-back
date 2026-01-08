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
        // Log para debug
        \Log::debug('ContratoFiltroDTO::fromArray - dados recebidos', [
            'data' => $data,
            'srp_raw' => $data['srp'] ?? 'NOT_SET',
            'srp_type' => isset($data['srp']) ? gettype($data['srp']) : 'NOT_SET',
            'vigente_raw' => $data['vigente'] ?? 'NOT_SET',
            'vigente_type' => isset($data['vigente']) ? gettype($data['vigente']) : 'NOT_SET',
        ]);
        
        // Tratar strings vazias como null - verificação mais robusta
        $busca = (isset($data['busca']) && $data['busca'] !== '' && $data['busca'] !== null) ? $data['busca'] : null;
        $orgaoId = (isset($data['orgao_id']) && $data['orgao_id'] !== '' && $data['orgao_id'] !== null) ? (int) $data['orgao_id'] : null;
        
        // Para booleanos: string vazia, null, ou não definido = null (ignorar filtro)
        $srp = null;
        if (isset($data['srp']) && $data['srp'] !== '' && $data['srp'] !== null) {
            $srp = filter_var($data['srp'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        $situacao = (isset($data['situacao']) && $data['situacao'] !== '' && $data['situacao'] !== null) ? $data['situacao'] : null;
        
        $vigente = null;
        if (isset($data['vigente']) && $data['vigente'] !== '' && $data['vigente'] !== null) {
            $vigente = filter_var($data['vigente'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        $vencerEm = (isset($data['vencer_em']) && $data['vencer_em'] !== '' && $data['vencer_em'] !== null) ? (int) $data['vencer_em'] : null;
        $somenteAlerta = isset($data['somente_alerta']) && $data['somente_alerta'] !== '' ? filter_var($data['somente_alerta'], FILTER_VALIDATE_BOOLEAN) : false;

        \Log::debug('ContratoFiltroDTO::fromArray - valores processados', [
            'srp' => $srp,
            'vigente' => $vigente,
            'orgaoId' => $orgaoId,
        ]);

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

