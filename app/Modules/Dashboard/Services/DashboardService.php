<?php

namespace App\Modules\Dashboard\Services;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
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
        ];
    }
}

