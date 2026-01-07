<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;

/**
 * Use Case para obter resumo de processos
 * 
 * Responsabilidades:
 * - Agregar contadores por status
 * - Retornar estrutura padronizada
 */
class ObterResumoProcessosUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executa o use case
     * 
     * @param array $filtros Filtros opcionais
     * @return array Resumo com contadores por status
     */
    public function executar(array $filtros = []): array
    {
        $empresaId = $filtros['empresa_id'] ?? null;
        if (!$empresaId) {
            throw new \InvalidArgumentException('empresa_id é obrigatório');
        }

        // Status possíveis
        $statuses = [
            'participacao',
            'julgamento_habilitacao',
            'execucao',
            'pagamento',
            'encerramento',
            'perdido',
            'arquivado',
        ];

        $resumo = [];
        foreach ($statuses as $status) {
            $filtrosStatus = array_merge($filtros, [
                'status' => $status,
                'per_page' => 1, // Apenas para contar
            ]);
            
            $paginator = $this->processoRepository->buscarComFiltros($filtrosStatus);
            $resumo[$status] = $paginator->total();
        }

        // Adicionar alias 'julgamento' para compatibilidade
        $resumo['julgamento'] = $resumo['julgamento_habilitacao'];

        return $resumo;
    }
}

