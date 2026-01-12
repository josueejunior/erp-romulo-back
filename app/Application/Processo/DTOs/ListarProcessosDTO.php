<?php

namespace App\Application\Processo\DTOs;

/**
 * DTO para listagem de processos
 */
class ListarProcessosDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?string $status = null,
        public readonly ?string $modalidade = null,
        public readonly ?int $orgaoId = null,
        public readonly ?string $search = null,
        public readonly ?bool $somenteComOrcamento = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('empresaId é obrigatório e deve ser maior que zero.');
        }
    }

    /**
     * Criar DTO a partir de Request e empresaId
     */
    public static function fromRequest(array $requestData, int $empresaId): self
    {
        return new self(
            empresaId: $empresaId,
            status: $requestData['status'] ?? null,
            modalidade: $requestData['modalidade'] ?? null,
            orgaoId: isset($requestData['orgao_id']) ? (int) $requestData['orgao_id'] : null,
            search: $requestData['search'] ?? null,
            somenteComOrcamento: isset($requestData['somente_com_orcamento']) 
                ? ($requestData['somente_com_orcamento'] === 'true' || $requestData['somente_com_orcamento'] === '1' || $requestData['somente_com_orcamento'] === true)
                : null,
            perPage: isset($requestData['per_page']) ? (int) $requestData['per_page'] : 15,
            page: isset($requestData['page']) ? (int) $requestData['page'] : 1,
        );
    }

    /**
     * Converter para array de filtros do Repository
     */
    public function toRepositoryFilters(): array
    {
        $filtros = [
            'empresa_id' => $this->empresaId,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ];

        if ($this->status !== null) {
            $filtros['status'] = $this->status;
        }

        if ($this->modalidade !== null) {
            $filtros['modalidade'] = $this->modalidade;
        }

        if ($this->orgaoId !== null) {
            $filtros['orgao_id'] = $this->orgaoId;
        }

        if ($this->search !== null) {
            $filtros['search'] = $this->search;
        }

        if ($this->somenteComOrcamento !== null) {
            $filtros['somente_com_orcamento'] = $this->somenteComOrcamento;
        }

        return $filtros;
    }
}






