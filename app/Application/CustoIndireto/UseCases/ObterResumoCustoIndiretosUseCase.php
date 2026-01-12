<?php

namespace App\Application\CustoIndireto\UseCases;

use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;

/**
 * Use Case: Obter Resumo de Custos Indiretos
 */
class ObterResumoCustoIndiretosUseCase
{
    public function __construct(
        private CustoIndiretoRepositoryInterface $custoIndiretoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param array $filtros Filtros opcionais (data_inicio, data_fim, empresa_id)
     * @return array Resumo com total, quantidade e por_categoria
     */
    public function executar(array $filtros): array
    {
        // Buscar todos os custos que atendem aos filtros (sem paginação)
        $filtros['per_page'] = 10000; // Buscar todos para cálculo
        $paginator = $this->custoIndiretoRepository->buscarComFiltros($filtros);
        
        $custos = $paginator->items();
        
        // Calcular total e quantidade
        $total = array_sum(array_map(fn($custo) => $custo->valor, $custos));
        $quantidade = count($custos);
        
        // Agrupar por categoria
        $porCategoria = [];
        foreach ($custos as $custo) {
            $categoria = $custo->categoria ?? 'Sem categoria';
            if (!isset($porCategoria[$categoria])) {
                $porCategoria[$categoria] = 0.0;
            }
            $porCategoria[$categoria] += $custo->valor;
        }
        
        // Converter para formato esperado
        $porCategoriaArray = array_map(function ($categoria, $total) {
            return [
                'categoria' => $categoria,
                'total' => round($total, 2),
            ];
        }, array_keys($porCategoria), $porCategoria);
        
        return [
            'total' => round($total, 2),
            'quantidade' => $quantidade,
            'por_categoria' => $porCategoriaArray,
        ];
    }
}





