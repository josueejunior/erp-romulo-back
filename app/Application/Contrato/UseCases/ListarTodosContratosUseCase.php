<?php

namespace App\Application\Contrato\UseCases;

use App\Application\Contrato\DTOs\ContratoFiltroDTO;
use App\Domain\Contrato\Queries\ContratoQuery;
use App\Domain\Contrato\Queries\ContratoIndicadoresQuery;
use App\Domain\Orgao\Repositories\OrgaoRepositoryInterface;
use App\Domain\Exceptions\DomainException;

/**
 * Use Case para listar todos os contratos com filtros, indicadores e paginação
 * 
 * Responsabilidades:
 * - Aplicar filtros complexos
 * - Calcular indicadores
 * - Retornar estrutura padronizada
 */
class ListarTodosContratosUseCase
{
    public function __construct(
        private OrgaoRepositoryInterface $orgaoRepository,
    ) {}

    /**
     * Executa o use case
     * 
     * @param array $filtros Filtros de busca (array do request)
     * @param int $empresaId ID da empresa
     * @param string $ordenacao Campo de ordenação
     * @param string $direcao Direção da ordenação (asc/desc)
     * @param int $perPage Itens por página
     * @param mixed $tenantId ID do tenant (não usado - mantido para compatibilidade)
     * @return array Dados paginados com indicadores
     */
    public function executar(
        array $filtros,
        int $empresaId,
        string $ordenacao = 'data_fim',
        string $direcao = 'asc',
        int $perPage = 15,
        $tenantId = null
    ): array {
        // Converter array para DTO (tipagem forte)
        $filtroDTO = ContratoFiltroDTO::fromArray($filtros);

        // ✅ Validar órgão ANTES da query (não dentro da query)
        if ($filtroDTO->orgaoId) {
            $orgao = $this->orgaoRepository->buscarPorId($filtroDTO->orgaoId);
            if (!$orgao || $orgao->empresaId !== $empresaId) {
                throw new DomainException('Órgão não encontrado ou não pertence à empresa ativa.');
            }
        }

        // Criar query base com relacionamentos
        $query = ContratoQuery::criarQueryBase($empresaId);

        // Aplicar filtros complexos (agora com DTO tipado)
        $query = ContratoQuery::aplicarFiltros($query, $filtroDTO, $empresaId);

        // Clonar query para calcular indicadores (antes da paginação)
        $totalQuery = clone $query;

        // Ordenação
        $query->orderBy($ordenacao, $direcao);

        // Paginação
        $contratos = $query->paginate($perPage);

        // Calcular indicadores usando agregação SQL (não carrega dados em memória)
        $indicadores = ContratoIndicadoresQuery::calcular($totalQuery);
        
        // Calcular margem média (sobre TODOS os contratos filtrados, não apenas da página)
        // Buscar IDs de todos os contratos filtrados (sem paginação)
        $todosContratosIds = (clone $totalQuery)->pluck('id')->toArray();
        if (!empty($todosContratosIds)) {
            $indicadores['margem_media'] = ContratoIndicadoresQuery::calcularMargemMedia(
                $todosContratosIds,
                $empresaId
            );
        }

        // Mapear para arrays
        $items = $contratos->items();

        return [
            'data' => $items,
            'indicadores' => $indicadores,
            'pagination' => [
                'current_page' => $contratos->currentPage(),
                'last_page' => $contratos->lastPage(),
                'per_page' => $contratos->perPage(),
                'total' => $contratos->total(),
            ],
        ];
    }

    /**
     * Retorna indicadores vazios
     */
    private function getIndicadoresVazios(): array
    {
        return [
            'contratos_ativos' => 0,
            'contratos_a_vencer' => 0,
            'saldo_total_contratado' => 0,
            'saldo_ja_faturado' => 0,
            'saldo_restante' => 0,
            'margem_media' => 0,
        ];
    }
}

