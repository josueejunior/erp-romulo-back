<?php

namespace App\Application\NotaFiscal\DTOs;

/**
 * DTO para filtros de listagem de notas fiscais
 * Encapsula todos os parâmetros de busca/paginação
 */
class FiltroNotaFiscalDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?int $processoId = null,
        public readonly ?int $empenhoId = null,
        public readonly ?int $fornecedorId = null,
        public readonly ?string $situacao = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(array $requestData, int $empresaId): self
    {
        return new self(
            empresaId: $empresaId,
            processoId: isset($requestData['processo_id']) && $requestData['processo_id'] 
                ? (int) $requestData['processo_id'] 
                : null,
            empenhoId: isset($requestData['empenho_id']) && $requestData['empenho_id'] 
                ? (int) $requestData['empenho_id'] 
                : null,
            fornecedorId: isset($requestData['fornecedor_id']) && $requestData['fornecedor_id'] 
                ? (int) $requestData['fornecedor_id'] 
                : null,
            situacao: $requestData['situacao'] ?? null,
            perPage: isset($requestData['per_page']) ? (int) $requestData['per_page'] : 15,
            page: isset($requestData['page']) ? (int) $requestData['page'] : 1,
        );
    }

    /**
     * Converte para array de filtros do repository
     */
    public function toRepositoryFilters(): array
    {
        $filtros = [
            'empresa_id' => $this->empresaId,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ];

        if ($this->processoId) {
            $filtros['processo_id'] = $this->processoId;
        }

        if ($this->empenhoId) {
            $filtros['empenho_id'] = $this->empenhoId;
        }

        if ($this->fornecedorId) {
            $filtros['fornecedor_id'] = $this->fornecedorId;
        }

        if ($this->situacao) {
            $filtros['situacao'] = $this->situacao;
        }

        return $filtros;
    }
}







