<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Processo\Models\Processo;
use App\Models\DocumentoHabilitacao;

class DashboardService
{
    /**
     * Obter dados do dashboard
     */
    public function obterDadosDashboard(int $empresaId): array
    {
        $processosParticipacao = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->where('status', 'participacao')
            ->count();

        $processosJulgamento = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->where('status', 'julgamento_habilitacao')
            ->count();

        $processosExecucao = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->where('status', 'execucao')
            ->count();

        $processosPagamento = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->where('status', 'pagamento')
            ->count();

        $processosEncerramento = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->where('status', 'encerramento')
            ->count();

        $processosPerdidos = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->where('status', 'perdido')
            ->count();

        $processosArquivados = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->where('status', 'arquivado')
            ->count();

        $proximasDisputas = Processo::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->whereIn('status', ['participacao', 'julgamento_habilitacao'])
            ->where('data_hora_sessao_publica', '>=', now())
            ->orderBy('data_hora_sessao_publica', 'asc')
            ->limit(5)
            ->select(['id', 'numero_modalidade', 'data_hora_sessao_publica', 'objeto_resumido'])
            ->get()
            ->map(function($processo) {
                return [
                    'id' => $processo->id,
                    'numero_modalidade' => $processo->numero_modalidade,
                    'data_hora_sessao_publica' => $processo->data_hora_sessao_publica,
                    'objeto_resumido' => $processo->objeto_resumido,
                ];
            });

        $documentosVencendo = DocumentoHabilitacao::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->whereNotNull('data_validade')
            ->where('data_validade', '>=', now())
            ->where('data_validade', '<=', now()->addDays(30))
            ->orderBy('data_validade', 'asc')
            ->get(['id', 'tipo', 'numero', 'data_validade']);

        $documentosVencidos = DocumentoHabilitacao::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->whereNotNull('data_validade')
            ->where('data_validade', '<', now())
            ->orderBy('data_validade', 'desc')
            ->limit(5)
            ->get(['id', 'tipo', 'numero', 'data_validade']);

        $documentosUrgentes = DocumentoHabilitacao::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->whereNotNull('data_validade')
            ->where('data_validade', '>=', now())
            ->where('data_validade', '<=', now()->addDays(7))
            ->count();

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

