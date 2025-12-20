<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Services\DisputaService;
use App\Services\ProcessoStatusService;
use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;

class DisputaController extends BaseApiController
{
    protected DisputaService $disputaService;
    protected ProcessoStatusService $statusService;

    public function __construct(DisputaService $disputaService, ProcessoStatusService $statusService)
    {
        $this->disputaService = $disputaService;
        $this->statusService = $statusService;
    }
    public function show(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível visualizar disputa de processos em execução.'
            ], 403);
        }

        $processo->load('itens');

        return response()->json([
            'processo' => [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numero_modalidade,
                'data_hora_sessao_publica' => $processo->data_hora_sessao_publica?->format('Y-m-d H:i:s'),
            ],
            'itens' => $processo->itens->map(function($item) {
                return [
                    'id' => $item->id,
                    'numero_item' => $item->numero_item,
                    'especificacao_tecnica' => $item->especificacao_tecnica,
                    'quantidade' => $item->quantidade,
                    'unidade' => $item->unidade,
                    'valor_estimado' => $item->valor_estimado,
                    'valor_final_sessao' => $item->valor_final_sessao,
                    'valor_arrematado' => $item->valor_arrematado,
                    'classificacao' => $item->classificacao,
                ];
            }),
        ]);
    }

    public function update(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        // Verificar permissão
        if (!PermissionHelper::canEditProcess()) {
            return response()->json([
                'message' => 'Você não tem permissão para registrar disputas.'
            ], 403);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar disputa de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:processo_itens,id',
            'itens.*.valor_final_sessao' => 'nullable|numeric|min:0',
            'itens.*.valor_arrematado' => 'nullable|numeric|min:0',
            'itens.*.classificacao' => 'nullable|integer|min:1',
            'itens.*.observacoes' => 'nullable|string',
        ]);

        // Registrar resultados usando o serviço
        $processo = $this->disputaService->registrarResultados($processo, $validated['itens']);

        // Verificar se deve sugerir mudança de status
        $sugerirJulgamento = $this->statusService->deveSugerirJulgamento($processo);

        $processo->load('itens');

        return response()->json([
            'message' => 'Disputa registrada com sucesso!',
            'processo' => $processo,
            'sugerir_julgamento' => $sugerirJulgamento,
        ]);
    }
}






