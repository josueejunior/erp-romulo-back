<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Services\DisputaService;
use App\Services\ProcessoStatusService;
use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;

class JulgamentoController extends BaseApiController
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
                'message' => 'Não é possível visualizar julgamento de processos em execução.'
            ], 403);
        }

        $processo->load('itens');

        return response()->json([
            'processo' => [
                'id' => $processo->id,
                'numero_modalidade' => $processo->numero_modalidade,
            ],
            'itens' => $processo->itens->map(function($item) {
                return [
                    'id' => $item->id,
                    'numero_item' => $item->numero_item,
                    'especificacao_tecnica' => $item->especificacao_tecnica,
                    'status_item' => $item->status_item,
                    'valor_negociado' => $item->valor_negociado,
                    'chance_arremate' => $item->chance_arremate,
                    'chance_percentual' => $item->chance_percentual,
                    'lembretes' => $item->lembretes,
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
                'message' => 'Você não tem permissão para editar julgamentos.'
            ], 403);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar julgamento de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:processo_itens,id',
            'itens.*.status_item' => 'required|in:pendente,aceito,aceito_habilitado,desclassificado,inabilitado',
            'itens.*.valor_negociado' => 'nullable|numeric|min:0',
            'itens.*.chance_arremate' => 'nullable|in:baixa,media,alta',
            'itens.*.chance_percentual' => 'nullable|integer|min:0|max:100',
            'itens.*.tem_chance' => 'nullable|boolean',
            'itens.*.lembretes' => 'nullable|string',
            'itens.*.observacoes' => 'nullable|string',
        ]);

        // Atualizar julgamento usando o serviço
        foreach ($validated['itens'] as $itemData) {
            $item = ProcessoItem::find($itemData['id']);
            if ($item && $item->processo_id === $processo->id) {
                $this->disputaService->registrarJulgamento(
                    $item,
                    $itemData['status_item'],
                    $itemData['classificacao'] ?? null,
                    $itemData['chance_arremate'] ?? null,
                    $itemData['chance_percentual'] ?? null,
                    $itemData['tem_chance'] ?? null,
                    $itemData['valor_negociado'] ?? null,
                    $itemData['lembretes'] ?? null,
                    $itemData['observacoes'] ?? null
                );
            }
        }

        $processo->load('itens');
        $processo->refresh();

        // Verificar se deve sugerir mudança de status
        $sugerirPerdido = $this->statusService->deveSugerirPerdido($processo);

        return response()->json([
            'message' => 'Julgamento atualizado com sucesso!',
            'processo' => $processo,
            'sugerir_perdido' => $sugerirPerdido,
        ]);
    }
}






