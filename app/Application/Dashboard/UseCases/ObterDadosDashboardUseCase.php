<?php

namespace App\Application\Dashboard\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Modules\Relatorio\Services\FinanceiroService;
use App\Services\RedisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para obter dados do dashboard
 * 
 * Responsabilidades:
 * - Agregar dados de processos, documentos e financeiro
 * - Gerenciar cache (5 minutos)
 * - Retornar estrutura padronizada de dados
 */
class ObterDadosDashboardUseCase
{
    private const CACHE_TTL = 300; // 5 minutos

    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
        private FinanceiroService $financeiroService,
    ) {}

    /**
     * Executa o use case
     * 
     * @param int $empresaId ID da empresa
     * @param mixed $tenantId ID do tenant (para cache)
     * @return array Dados do dashboard
     */
    public function executar(int $empresaId, $tenantId = null): array
    {
        // Tentar obter do cache primeiro
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = $this->getCacheKey($tenantId, $empresaId);
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                Log::debug('Dashboard: dados obtidos do cache', [
                    'empresa_id' => $empresaId,
                    'tenant_id' => $tenantId,
                ]);
                return $cached;
            }
        }

        // Obter dados do dashboard
        $data = $this->obterDados($empresaId);

        // Salvar no cache
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = $this->getCacheKey($tenantId, $empresaId);
            RedisService::set($cacheKey, $data, self::CACHE_TTL);
            Log::debug('Dashboard: dados salvos no cache', [
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
            ]);
        }

        return $data;
    }

    /**
     * Obtém os dados do dashboard (sem cache)
     */
    private function obterDados(int $empresaId): array
    {
        // Contar processos por status
        $processos = $this->contarProcessosPorStatus($empresaId);

        // Buscar próximas disputas
        $proximasDisputas = $this->buscarProximasDisputas($empresaId);

        // Buscar documentos
        $documentos = $this->buscarDocumentos($empresaId);

        // Calcular dados financeiros
        $dadosFinanceiros = $this->calcularDadosFinanceiros($empresaId);

        return [
            'processos' => $processos,
            'proximas_disputas' => $proximasDisputas,
            'documentos_vencendo' => $documentos['vencendo'],
            'documentos_vencidos' => $documentos['vencidos'],
            'documentos_urgentes' => $documentos['urgentes'],
            'financeiro' => $dadosFinanceiros,
        ];
    }

    /**
     * Conta processos por status
     */
    private function contarProcessosPorStatus(int $empresaId): array
    {
        $statuses = [
            'participacao',
            'julgamento_habilitacao',
            'execucao',
            'pagamento',
            'encerramento',
            'perdido',
            'arquivado',
        ];

        $contadores = [];
        foreach ($statuses as $status) {
            $contadores[$status] = $this->processoRepository->buscarComFiltros([
                'empresa_id' => $empresaId,
                'status' => $status,
                'per_page' => 1,
            ])->total();
        }

        // Adicionar alias 'julgamento' para compatibilidade
        $contadores['julgamento'] = $contadores['julgamento_habilitacao'];

        return $contadores;
    }

    /**
     * Busca próximas disputas (processos com sessão pública próxima)
     */
    private function buscarProximasDisputas(int $empresaId): array
    {
        $paginator = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => ['participacao', 'julgamento_habilitacao'],
            'data_hora_sessao_publica_inicio' => now(),
            'per_page' => 5,
        ]);

        return $paginator->getCollection()->map(function($processo) {
            return [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numeroModalidade,
                'data_hora_sessao_publica' => $processo->dataHoraSessaoPublica?->toDateTimeString(),
                'objeto_resumido' => $processo->objetoResumido,
            ];
        })->toArray();
    }

    /**
     * Busca documentos (vencendo, vencidos, urgentes)
     */
    private function buscarDocumentos(int $empresaId): array
    {
        // Documentos vencendo (próximos 30 dias)
        $documentosVencendoPaginator = $this->documentoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'data_validade_inicio' => now(),
            'data_validade_fim' => now()->addDays(30),
            'per_page' => 100,
        ]);

        $documentosVencendo = $documentosVencendoPaginator->getCollection()->map(function($documento) {
            return [
                'id' => $documento->id,
                'tipo' => $documento->tipo,
                'numero' => $documento->numero,
                'data_validade' => $documento->dataValidade?->toDateString(),
            ];
        })->toArray();

        // Documentos vencidos
        $documentosVencidosPaginator = $this->documentoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'data_validade_fim' => now(),
            'per_page' => 5,
        ]);

        $documentosVencidos = $documentosVencidosPaginator->getCollection()->map(function($documento) {
            return [
                'id' => $documento->id,
                'tipo' => $documento->tipo,
                'numero' => $documento->numero,
                'data_validade' => $documento->dataValidade?->toDateString(),
            ];
        })->toArray();

        // Documentos urgentes (próximos 7 dias)
        $documentosUrgentes = $this->documentoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'data_validade_inicio' => now(),
            'data_validade_fim' => now()->addDays(7),
            'per_page' => 1,
        ])->total();

        return [
            'vencendo' => $documentosVencendo,
            'vencidos' => $documentosVencidos,
            'urgentes' => $documentosUrgentes,
        ];
    }

    /**
     * Calcula dados financeiros
     */
    private function calcularDadosFinanceiros(int $empresaId): array
    {
        try {
            // Buscar processos em execução
            $processosExecucao = $this->processoRepository->buscarComFiltros([
                'empresa_id' => $empresaId,
                'status' => 'execucao',
                'per_page' => 1000,
            ]);

            $receitaPendente = 0;
            $custosDiretosPendentes = 0;
            $processosComDados = 0;

            // Buscar modelos para cálculo financeiro
            $processosIds = $processosExecucao->getCollection()->pluck('id')->toArray();
            if (!empty($processosIds)) {
                $processosModels = \App\Modules\Processo\Models\Processo::whereIn('id', $processosIds)
                    ->where('empresa_id', $empresaId)
                    ->get();

                foreach ($processosModels as $processo) {
                    try {
                        $receita = $this->financeiroService->calcularReceita($processo);
                        $custos = $this->financeiroService->calcularCustosDiretos($processo);
                        
                        $receitaPendente += $receita['receita_total'] ?? 0;
                        $custosDiretosPendentes += $custos['custo_total'] ?? 0;
                        $processosComDados++;
                    } catch (\Exception $e) {
                        Log::warning('Erro ao calcular dados financeiros do processo', [
                            'processo_id' => $processo->id,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }
            }

            // Calcular dados do mês atual
            $mesAtual = Carbon::now();
            $dadosMesAtual = $this->financeiroService->calcularGestaoFinanceiraMensal($mesAtual, $empresaId);

            // Calcular evolução mensal (últimos 6 meses)
            $evolucaoMensal = [];
            for ($i = 5; $i >= 0; $i--) {
                $mes = Carbon::now()->subMonths($i);
                $dadosMes = $this->financeiroService->calcularGestaoFinanceiraMensal($mes, $empresaId);
                
                $evolucaoMensal[] = [
                    'mes' => $mes->format('Y-m'),
                    'mes_label' => $mes->format('M/Y'),
                    'receita' => $dadosMes['receita_total'] ?? 0,
                    'lucro_bruto' => $dadosMes['lucro_bruto'] ?? 0,
                    'lucro_liquido' => $dadosMes['lucro_liquido'] ?? 0,
                    'margem_bruta' => $dadosMes['margem_bruta'] ?? 0,
                    'margem_liquida' => $dadosMes['margem_liquida'] ?? 0,
                ];
            }

            $lucroPendente = $receitaPendente - $custosDiretosPendentes;
            $margemPendente = $receitaPendente > 0 
                ? ($lucroPendente / $receitaPendente) * 100 
                : 0;

            return [
                'pendente' => [
                    'receita' => round($receitaPendente, 2),
                    'custos_diretos' => round($custosDiretosPendentes, 2),
                    'lucro_bruto' => round($lucroPendente, 2),
                    'margem_bruta' => round($margemPendente, 2),
                    'processos' => $processosComDados,
                ],
                'mes_atual' => [
                    'receita' => $dadosMesAtual['receita_total'] ?? 0,
                    'custos_diretos' => $dadosMesAtual['custos_diretos'] ?? 0,
                    'custos_indiretos' => $dadosMesAtual['custos_indiretos'] ?? 0,
                    'lucro_bruto' => $dadosMesAtual['lucro_bruto'] ?? 0,
                    'lucro_liquido' => $dadosMesAtual['lucro_liquido'] ?? 0,
                    'margem_bruta' => $dadosMesAtual['margem_bruta'] ?? 0,
                    'margem_liquida' => $dadosMesAtual['margem_liquida'] ?? 0,
                    'processos' => $dadosMesAtual['quantidade_processos'] ?? 0,
                ],
                'evolucao_mensal' => $evolucaoMensal,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao calcular dados financeiros do dashboard', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Retornar estrutura vazia em caso de erro
            return $this->getEstruturaFinanceiraVazia();
        }
    }

    /**
     * Retorna estrutura financeira vazia
     */
    private function getEstruturaFinanceiraVazia(): array
    {
        return [
            'pendente' => [
                'receita' => 0,
                'custos_diretos' => 0,
                'lucro_bruto' => 0,
                'margem_bruta' => 0,
                'processos' => 0,
            ],
            'mes_atual' => [
                'receita' => 0,
                'custos_diretos' => 0,
                'custos_indiretos' => 0,
                'lucro_bruto' => 0,
                'lucro_liquido' => 0,
                'margem_bruta' => 0,
                'margem_liquida' => 0,
                'processos' => 0,
            ],
            'evolucao_mensal' => [],
        ];
    }

    /**
     * Gera chave de cache
     */
    private function getCacheKey($tenantId, int $empresaId): string
    {
        return "dashboard_{$tenantId}_{$empresaId}";
    }
}

