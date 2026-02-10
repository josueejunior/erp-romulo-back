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

        // ✅ Mapear filtros da URL para o formato esperado pelo repository
        $filtrosMapeados = $this->mapearFiltros($filtros);

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
            try {
                $filtrosStatus = array_merge($filtrosMapeados, [
                    'status' => $status,
                    'per_page' => 1, // Apenas para contar
                ]);
                
                \Log::debug('ObterResumoProcessosUseCase - Buscando processos por status', [
                    'status' => $status,
                    'empresa_id' => $filtrosMapeados['empresa_id'] ?? null,
                    'filtros_status' => $filtrosStatus,
                ]);
                
                // 🔥 CORREÇÃO: Usar buscarComFiltros e pegar total() do paginator
                // O total() do paginator retorna o total de registros, não apenas da página atual
                $paginator = $this->processoRepository->buscarComFiltros($filtrosStatus);
                $total = $paginator->total();
                
                // 🔥 DEBUG: Verificar se o total está correto
                \Log::debug('ObterResumoProcessosUseCase - Resultado do paginator', [
                    'status' => $status,
                    'total' => $total,
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                ]);
                
                $resumo[$status] = $total;
                
                \Log::debug('ObterResumoProcessosUseCase - Resultado para status', [
                    'status' => $status,
                    'total' => $total,
                ]);
            } catch (\Exception $e) {
                // Log do erro e continuar com os outros status
                \Log::error('Erro ao calcular resumo para status', [
                    'status' => $status,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $resumo[$status] = 0; // Valor padrão em caso de erro
            }
        }

        // Adicionar alias 'julgamento' para compatibilidade
        $resumo['julgamento'] = $resumo['julgamento_habilitacao'] ?? 0;
        
        // 🔥 DEBUG: Log do resumo final
        \Log::debug('ObterResumoProcessosUseCase - Resumo final', [
            'empresa_id' => $filtrosMapeados['empresa_id'] ?? null,
            'resumo' => $resumo,
        ]);

        // Contagem especial: Standby
        $resumo['em_standby'] = $this->processoRepository->buscarComFiltros(array_merge($filtrosMapeados, [
            'somente_standby' => true,
            'per_page' => 1
        ]))->total();

        // Contagem especial: Alertas
        $resumo['com_alerta'] = $this->processoRepository->buscarComFiltros(array_merge($filtrosMapeados, [
            'somente_alerta' => true,
            'per_page' => 1
        ]))->total();

        // Valores financeiros reais
        $financeiro = $this->processoRepository->obterTotaisFinanceiros($filtrosMapeados);
        $resumo['valor_total_execucao'] = $financeiro['valor_total_execucao'];
        $resumo['valor_total_standby'] = $financeiro['valor_total_standby'];
        $resumo['lucro_estimado'] = $financeiro['lucro_estimado'];

        return $resumo;
    }

    /**
     * Mapeia filtros da URL para o formato esperado pelo repository
     * 
     * @param array $filtros Filtros da requisição
     * @return array Filtros mapeados
     */
    private function mapearFiltros(array $filtros): array
    {
        $mapeados = $filtros;

        // Mapear periodo_sessao_inicio/fim para data_hora_sessao_publica_inicio/fim
        if (isset($filtros['periodo_sessao_inicio'])) {
            $mapeados['data_hora_sessao_publica_inicio'] = $filtros['periodo_sessao_inicio'];
            unset($mapeados['periodo_sessao_inicio']);
        }

        if (isset($filtros['periodo_sessao_fim'])) {
            $mapeados['data_hora_sessao_publica_fim'] = $filtros['periodo_sessao_fim'];
            unset($mapeados['periodo_sessao_fim']);
        }

        // 🔥 CORREÇÃO: Converter string "false" para boolean false
        if (isset($filtros['somente_alerta'])) {
            if ($filtros['somente_alerta'] === 'false' || $filtros['somente_alerta'] === false || $filtros['somente_alerta'] === '0' || $filtros['somente_alerta'] === 0) {
                unset($mapeados['somente_alerta']); // Remover se for false
            } else {
                $mapeados['somente_alerta'] = true; // Converter para boolean true
            }
        }

        // Remover filtros vazios que podem causar problemas
        $mapeados = array_filter($mapeados, function($value) {
            return $value !== '' && $value !== null;
        });

        return $mapeados;
    }
}

