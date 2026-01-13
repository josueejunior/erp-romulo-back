<?php

namespace App\Application\Empenho\DTOs;

/**
 * DTO para listagem de empenhos
 * 
 * ✅ Encapsula todos os filtros e parâmetros de paginação
 * ✅ Validação explícita de empresaId (obrigatório)
 */
class ListarEmpenhosDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?int $processoId = null,
        public readonly ?int $contratoId = null,
        public readonly ?string $situacao = null,
        public readonly ?bool $concluido = null,
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
            processoId: isset($requestData['processo_id']) ? (int) $requestData['processo_id'] : null,
            contratoId: isset($requestData['contrato_id']) ? (int) $requestData['contrato_id'] : null,
            situacao: $requestData['situacao'] ?? null,
            concluido: isset($requestData['concluido']) 
                ? ($requestData['concluido'] === 'true' || $requestData['concluido'] === '1' || $requestData['concluido'] === true)
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
        
        if ($this->processoId !== null) {
            $filtros['processo_id'] = $this->processoId;
        }
        
        if ($this->contratoId !== null) {
            $filtros['contrato_id'] = $this->contratoId;
        }
        
        if ($this->situacao !== null) {
            $filtros['situacao'] = $this->situacao;
        }
        
        if ($this->concluido !== null) {
            $filtros['concluido'] = $this->concluido;
        }
        
        return $filtros;
    }
}








