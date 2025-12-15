<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\DocumentoHabilitacao;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $processosParticipacao = Processo::where('status', 'participacao')->count();
        $processosJulgamento = Processo::where('status', 'julgamento_habilitacao')->count();
        $processosExecucao = Processo::where('status', 'execucao')->count();

        $proximasDisputas = Processo::whereIn('status', ['participacao', 'julgamento_habilitacao'])
            ->where('data_hora_sessao_publica', '>=', now())
            ->orderBy('data_hora_sessao_publica', 'asc')
            ->limit(5)
            ->get(['id', 'numero_modalidade', 'data_hora_sessao_publica', 'objeto_resumido']);

        $documentosVencendo = DocumentoHabilitacao::whereNotNull('data_validade')
            ->where('data_validade', '>=', now())
            ->where('data_validade', '<=', now()->addDays(30))
            ->orderBy('data_validade', 'asc')
            ->get(['id', 'tipo', 'numero', 'data_validade']);

        $documentosVencidos = DocumentoHabilitacao::whereNotNull('data_validade')
            ->where('data_validade', '<', now())
            ->orderBy('data_validade', 'desc')
            ->limit(5)
            ->get(['id', 'tipo', 'numero', 'data_validade']);

        $documentosUrgentes = DocumentoHabilitacao::whereNotNull('data_validade')
            ->where('data_validade', '>=', now())
            ->where('data_validade', '<=', now()->addDays(7))
            ->count();

        return response()->json([
            'processos' => [
                'participacao' => $processosParticipacao,
                'julgamento' => $processosJulgamento,
                'execucao' => $processosExecucao,
            ],
            'proximas_disputas' => $proximasDisputas,
            'documentos_vencendo' => $documentosVencendo,
            'documentos_vencidos' => $documentosVencidos,
            'documentos_urgentes' => $documentosUrgentes,
        ]);
    }
}




