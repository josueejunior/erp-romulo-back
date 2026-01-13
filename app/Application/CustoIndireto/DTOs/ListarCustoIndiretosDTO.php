<?php

namespace App\Application\CustoIndireto\DTOs;

/**
 * DTO para listagem de custos indiretos
 */
class ListarCustoIndiretosDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?string $search = null,
        public readonly ?string $dataInicio = null,
        public readonly ?string $dataFim = null,
        public readonly ?string $categoria = null,
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
            search: $requestData['search'] ?? null,
            dataInicio: $requestData['data_inicio'] ?? null,
            dataFim: $requestData['data_fim'] ?? null,
            categoria: $requestData['categoria'] ?? null,
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

        if ($this->search !== null) {
            $filtros['search'] = $this->search;
        }

        if ($this->dataInicio !== null) {
            $filtros['data_inicio'] = $this->dataInicio;
        }

        if ($this->dataFim !== null) {
            $filtros['data_fim'] = $this->dataFim;
        }

        if ($this->categoria !== null) {
            $filtros['categoria'] = $this->categoria;
        }

        return $filtros;
    }
}








