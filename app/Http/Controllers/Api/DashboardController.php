<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Processo;
use App\Models\DocumentoHabilitacao;
use App\Services\RedisService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $tenantId = tenancy()->tenant?->id;
        
        // Tentar obter do cache primeiro
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::getDashboard($tenantId);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        $processosParticipacao = Processo::where('status', 'participacao')->count();
        $processosJulgamento = Processo::where('status', 'julgamento_habilitacao')->count();
        $processosExecucao = Processo::where('status', 'execucao')->count();
        $processosPagamento = Processo::where('status', 'pagamento')->count();
        $processosEncerramento = Processo::where('status', 'encerramento')->count();
        $processosPerdidos = Processo::where('status', 'perdido')->count();
        $processosArquivados = Processo::where('status', 'arquivado')->count();

        $proximasDisputas = Processo::whereIn('status', ['participacao', 'julgamento_habilitacao'])
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

        $data = [
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

        // Salvar no cache Redis se disponível
        if ($tenantId && RedisService::isAvailable()) {
            RedisService::cacheDashboard($tenantId, $data, 300); // Cache por 5 minutos
        }

        return response()->json($data);
    }
}




