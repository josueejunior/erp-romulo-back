<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\DocumentoHabilitacao;
use App\Services\RedisService;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function index()
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        // Tentar obter do cache primeiro (com empresa_id no cache key)
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = "dashboard_{$tenantId}_{$empresa->id}";
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        $processosParticipacao = Processo::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->where('status', 'participacao')->count();
        $processosJulgamento = Processo::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->where('status', 'julgamento_habilitacao')->count();
        $processosExecucao = Processo::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->where('status', 'execucao')->count();
        $processosPagamento = Processo::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->where('status', 'pagamento')->count();
        $processosEncerramento = Processo::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->where('status', 'encerramento')->count();
        $processosPerdidos = Processo::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->where('status', 'perdido')->count();
        $processosArquivados = Processo::where('empresa_id', $empresa->id)->whereNotNull('empresa_id')->where('status', 'arquivado')->count();

        $proximasDisputas = Processo::where('empresa_id', $empresa->id)
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

        $documentosVencendo = DocumentoHabilitacao::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->whereNotNull('data_validade')
            ->where('data_validade', '>=', now())
            ->where('data_validade', '<=', now()->addDays(30))
            ->orderBy('data_validade', 'asc')
            ->get(['id', 'tipo', 'numero', 'data_validade']);

        $documentosVencidos = DocumentoHabilitacao::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->whereNotNull('data_validade')
            ->where('data_validade', '<', now())
            ->orderBy('data_validade', 'desc')
            ->limit(5)
            ->get(['id', 'tipo', 'numero', 'data_validade']);

        $documentosUrgentes = DocumentoHabilitacao::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->whereNotNull('data_validade')
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

        // Salvar no cache Redis se disponível (com empresa_id no cache key)
        if ($tenantId && RedisService::isAvailable()) {
            $cacheKey = "dashboard_{$tenantId}_{$empresa->id}";
            RedisService::set($cacheKey, $data, 300); // Cache por 5 minutos
        }

        return response()->json($data);
    }
}






