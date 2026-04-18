<?php

namespace App\Modules\Dashboard\Services;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Modules\Relatorio\Services\FinanceiroService;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
        private FinanceiroService $financeiroService,
    ) {}

    /**
     * Obter dados do dashboard
     */
    public function obterDadosDashboard(int $empresaId): array
    {
        // Usar repository para contar processos por status
        $processosParticipacao = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'participacao',
            'per_page' => 1,
        ])->total();

        $processosJulgamento = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'julgamento_habilitacao',
            'per_page' => 1,
        ])->total();

        $processosExecucao = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'execucao',
            'per_page' => 1,
        ])->total();

        $processosPagamento = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'pagamento',
            'per_page' => 1,
        ])->total();

        $processosEncerramento = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'encerramento',
            'per_page' => 1,
        ])->total();

        $processosPerdidos = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'perdido',
            'per_page' => 1,
        ])->total();

        $processosArquivados = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'arquivado',
            'per_page' => 1,
        ])->total();

        // Buscar próximas disputas usando repository
        $proximasDisputasPaginator = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => ['participacao', 'julgamento_habilitacao'],
            'data_hora_sessao_publica_inicio' => now(),
            'per_page' => 5,
        ]);

        $proximasDisputas = $proximasDisputasPaginator->getCollection()->map(function($processo) {
            return [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numeroModalidade,
                'data_hora_sessao_publica' => $processo->dataHoraSessaoPublica?->toDateTimeString(),
                'objeto_resumido' => $processo->objetoResumido,
            ];
        })->toArray();

        // Buscar documentos usando repository
        $documentosVencendoPaginator = $this->documentoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'data_validade_inicio' => now(),
            'data_validade_fim' => now()->addDays(30),
            'per_page' => 100, // Limite alto para pegar todos
        ]);

        $documentosVencendo = $documentosVencendoPaginator->getCollection()->map(function($documento) {
            return [
                'id' => $documento->id,
                'tipo' => $documento->tipo,
                'numero' => $documento->numero,
                'data_validade' => $documento->dataValidade?->toDateString(),
            ];
        })->toArray();

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

        $documentosUrgentesPaginator = $this->documentoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'data_validade_inicio' => now(),
            'data_validade_fim' => now()->addDays(7),
            'per_page' => 1,
        ]);

        $documentosUrgentes = $documentosUrgentesPaginator->total();

        // Calcular dados financeiros
        $dadosFinanceiros = $this->calcularDadosFinanceiros($empresaId);

        return [
            'processos' => [
                'participacao' => $processosParticipacao,
                'julgamento_habilitacao' => $processosJulgamento,
                'julgamento' => $processosJulgamento,
                'execucao' => $processosExecucao,
                'pagamento' => $processosPagamento,
                'encerramento' => $processosEncerramento,
                'perdido' => $processosPerdidos,
                'arquivado' => $processosArquivados,
            ],
            'proximas_disputas' => $proximasDisputas,
            'documentos_vencendo' => $documentosVencendo,
            'documentos_vencidos' => $documentosVencidos,
            'documentos_urgentes' => $documentosUrgentes,
            'financeiro' => $dadosFinanceiros,
        ];
    }

    /**
     * Calcular dados financeiros para o dashboard
     */
    private function calcularDadosFinanceiros(int $empresaId): array
    {
        try {
            // Buscar processos que tenham itens aceitos (vencidos/arrematados)
            if (method_exists($this->processoRepository, 'buscarModelosComFiltros')) {
                $processosModels = $this->processoRepository->buscarModelosComFiltros([
                    'empresa_id' => $empresaId,
                    'tem_item_aceito' => true,
                ]);
            } else {
                // Fallback: carregar IDs e buscar
                $processosModels = \App\Modules\Processo\Models\Processo::where('empresa_id', $empresaId)
                    ->whereHas('itens', function($q) {
                        $q->whereIn('status_item', ['aceito', 'aceito_habilitado']);
                    })
                    ->get();
            }

            $receitaTotalArrematada = 0;
            $receitaTotalEmpenhada = 0;
            $receitaTotalFaturada = 0;
            $receitaTotalPaga = 0;
            $custosDiretosPendentes = 0;
            $processosComDados = 0;

            foreach ($processosModels as $processo) {
                try {
                    $receita = $this->financeiroService->calcularReceita($processo);
                    $custos = $this->financeiroService->calcularCustosDiretos($processo);
                    
                    $receitaTotalArrematada += $receita['arrematado'] ?? 0;
                    $receitaTotalEmpenhada += $receita['empenhado'] ?? 0;
                    $receitaTotalFaturada += $receita['faturado'] ?? 0;
                    $receitaTotalPaga += $receita['pago'] ?? 0;
                    
                    $custosDiretosPendentes += $custos['custo_total'] ?? 0;
                    $processosComDados++;
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Calculo de "Pendente" no Dashboard
            // Receita pendente = Arrematado - Pago (ou Arrematado - Faturado?)
            // Vamos considerar como pendente tudo que foi arrematado mas ainda não foi pago.
            $receitaPendente = $receitaTotalArrematada - $receitaTotalPaga;
            $lucroPendente = $receitaPendente - $custosDiretosPendentes;
            $margemPendente = $receitaTotalArrematada > 0 
                ? ($lucroPendente / $receitaTotalArrematada) * 100 
                : 0;

            // Calcular dados do mês atual
            $mesAtual = Carbon::now();
            $dadosMesAtual = $this->financeiroService->calcularGestaoFinanceiraMensal($mesAtual, $empresaId);

            // Calcular dados dos últimos 6 meses para gráfico
            $evolucaoMensal = [];
            for ($i = 5; $i >= 0; $i--) {
                $mes = Carbon::now()->subMonths($i);
                $dadosMes = $this->financeiroService->calcularGestaoFinanceiraMensal($mes, $empresaId);
                
                $evolucaoMensal[] = [
                    'mes' => $mes->format('Y-m'),
                    'mes_label' => $mes->format('M/Y'),
                    'receita' => $dadosMes['resumo']['receita_total'] ?? 0,
                    'lucro_bruto' => $dadosMes['resumo']['lucro_bruto'] ?? 0,
                    'lucro_liquido' => $dadosMes['resumo']['lucro_liquido'] ?? 0,
                    'margem_bruta' => $dadosMes['resumo']['margem_bruta'] ?? 0,
                    'margem_liquida' => $dadosMes['resumo']['margem_liquida'] ?? 0,
                ];
            }

            return [
                'pendente' => [
                    'receita' => round($receitaPendente, 2),
                    'arrematado' => round($receitaTotalArrematada, 2),
                    'empenhado' => round($receitaTotalEmpenhada, 2),
                    'faturado' => round($receitaTotalFaturada, 2),
                    'pago' => round($receitaTotalPaga, 2),
                    'custos_diretos' => round($custosDiretosPendentes, 2),
                    'lucro_bruto' => round($lucroPendente, 2),
                    'margem_bruta' => round($margemPendente, 2),
                    'processos' => $processosComDados,
                ],
                'mes_atual' => [
                    'receita' => $dadosMesAtual['resumo']['receita_total'] ?? 0,
                    'custos_diretos' => $dadosMesAtual['resumo']['custos_diretos'] ?? 0,
                    'custos_indiretos' => $dadosMesAtual['resumo']['custos_indiretos'] ?? 0,
                    'lucro_bruto' => $dadosMesAtual['resumo']['lucro_bruto'] ?? 0,
                    'lucro_liquido' => $dadosMesAtual['resumo']['lucro_liquido'] ?? 0,
                    'margem_bruta' => $dadosMesAtual['resumo']['margem_bruta'] ?? 0,
                    'margem_liquida' => $dadosMesAtual['resumo']['margem_liquida'] ?? 0,
                    'processos' => $dadosMesAtual['quantidade_processos'] ?? 0,
                ],
                'evolucao_mensal' => $evolucaoMensal,
            ];
        } catch (\Exception $e) {
            \Log::error('Erro ao calcular dados financeiros do dashboard: ' . $e->getMessage());
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
    }
}

