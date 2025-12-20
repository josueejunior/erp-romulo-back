<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\OrcamentoResource;
use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use Illuminate\Http\Request;

class OrcamentoController extends BaseApiController
{
    public function index(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar se o processo pertence à empresa
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        // Verificar se o item pertence ao processo
        if ($item->processo_id !== $processo->id) {
            return response()->json([
                'message' => 'Item não pertence a este processo.',
                'item_processo_id' => $item->processo_id,
                'processo_id' => $processo->id,
            ], 404);
        }

        // Carregar orçamentos com relacionamentos
        $orcamentos = $item->orcamentos()
            ->with(['fornecedor', 'transportadora', 'formacaoPreco'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Log para debug (remover em produção se necessário)
        \Log::info('Orçamentos carregados', [
            'processo_id' => $processo->id,
            'item_id' => $item->id,
            'total_orcamentos' => $orcamentos->count(),
        ]);

        return OrcamentoResource::collection($orcamentos);
    }

    public function store(Request $request, Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar se o processo pertence à empresa
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        // Verificar permissão usando Policy
        $this->authorize('create', [$processo]);

        $validated = $request->validate([
            'fornecedor_id' => 'required|exists:fornecedores,id',
            'transportadora_id' => 'nullable|exists:transportadoras,id',
            'custo_produto' => 'required|numeric|min:0',
            'marca_modelo' => 'nullable|string|max:255',
            'ajustes_especificacao' => 'nullable|string',
            'frete' => 'nullable|numeric|min:0',
            'frete_incluido' => 'boolean',
            'observacoes' => 'nullable|string',
        ]);

        $validated['empresa_id'] = $empresa->id;
        $validated['processo_item_id'] = $item->id;
        $validated['frete'] = $validated['frete'] ?? 0;
        $validated['frete_incluido'] = $request->has('frete_incluido');
        $validated['fornecedor_escolhido'] = false;

        if ($request->has('fornecedor_escolhido')) {
            $item->orcamentos()->update(['fornecedor_escolhido' => false]);
            $validated['fornecedor_escolhido'] = true;
        }

        $orcamento = Orcamento::create($validated);
        $orcamento->load(['fornecedor', 'transportadora']);

        return new OrcamentoResource($orcamento);
    }

    public function show(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $orcamento->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Orçamento não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        if ($item->processo_id !== $processo->id || $orcamento->processo_item_id !== $item->id) {
            return response()->json(['message' => 'Orçamento não pertence a este item.'], 404);
        }

        $orcamento->load(['fornecedor', 'transportadora', 'formacaoPreco']);
        return new OrcamentoResource($orcamento);
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $orcamento->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Orçamento não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        // Verificar se o orçamento pertence ao item e processo
        // Pode ser vinculado ao processo OU ao item (compatibilidade)
        $isOrcamentoDoItem = $orcamento->processo_item_id === $item->id;
        $isOrcamentoDoProcesso = $orcamento->processo_id === $processo->id && $item->processo_id === $processo->id;
        
        if (!$isOrcamentoDoItem && !$isOrcamentoDoProcesso) {
            return response()->json(['message' => 'Orçamento não pertence a este item/processo.'], 404);
        }

        // Verificar permissão usando Policy
        $this->authorize('update', $orcamento);

        // Validação flexível: campos obrigatórios apenas se fornecidos
        $validated = $request->validate([
            'fornecedor_id' => 'sometimes|required|exists:fornecedores,id',
            'transportadora_id' => 'nullable|exists:transportadoras,id',
            'custo_produto' => 'sometimes|required|numeric|min:0',
            'marca_modelo' => 'nullable|string|max:255',
            'ajustes_especificacao' => 'nullable|string',
            'frete' => 'nullable|numeric|min:0',
            'frete_incluido' => 'sometimes|boolean',
            'fornecedor_escolhido' => 'sometimes|boolean',
            'observacoes' => 'nullable|string',
        ]);

        // Aplicar apenas campos fornecidos (update parcial)
        $updateData = [];
        
        if ($request->has('fornecedor_id')) {
            $updateData['fornecedor_id'] = $validated['fornecedor_id'];
        }
        
        if ($request->has('transportadora_id')) {
            $updateData['transportadora_id'] = $validated['transportadora_id'];
        }
        
        if ($request->has('custo_produto')) {
            $updateData['custo_produto'] = $validated['custo_produto'];
        }
        
        if ($request->has('marca_modelo')) {
            $updateData['marca_modelo'] = $validated['marca_modelo'];
        }
        
        if ($request->has('ajustes_especificacao')) {
            $updateData['ajustes_especificacao'] = $validated['ajustes_especificacao'];
        }
        
        if ($request->has('frete')) {
            $updateData['frete'] = $validated['frete'] ?? 0;
        }
        
        if ($request->has('frete_incluido')) {
            $updateData['frete_incluido'] = $request->boolean('frete_incluido');
        }
        
        if ($request->has('observacoes')) {
            $updateData['observacoes'] = $validated['observacoes'];
        }

        // Gerenciar fornecedor_escolhido
        if ($request->has('fornecedor_escolhido')) {
            $fornecedorEscolhido = $request->boolean('fornecedor_escolhido');
            
            if ($fornecedorEscolhido) {
                // Desmarcar outros orçamentos do mesmo item
                $item->orcamentos()->where('id', '!=', $orcamento->id)->update(['fornecedor_escolhido' => false]);
            }
            
            $updateData['fornecedor_escolhido'] = $fornecedorEscolhido;
        }

        // Atualizar apenas os campos fornecidos
        if (!empty($updateData)) {
            $orcamento->update($updateData);
        }
        
        $orcamento->refresh();
        $orcamento->load(['fornecedor', 'transportadora', 'formacaoPreco']);
        
        // Se o orçamento foi marcado como escolhido e tem formação de preço, atualizar valor mínimo no item
        if (isset($updateData['fornecedor_escolhido']) && $updateData['fornecedor_escolhido'] && $orcamento->formacaoPreco) {
            $item->valor_minimo_venda = $orcamento->formacaoPreco->preco_minimo;
            if (method_exists($item, 'calcularValorMinimoVenda')) {
                $item->calcularValorMinimoVenda();
            }
            $item->save();
        } elseif (isset($updateData['fornecedor_escolhido']) && !$updateData['fornecedor_escolhido']) {
            // Se foi desmarcado, limpar valor mínimo
            $item->valor_minimo_venda = null;
            $item->save();
        }

        return new OrcamentoResource($orcamento);
    }

    public function destroy(Processo $processo, ProcessoItem $item, Orcamento $orcamento)
    {
        if ($item->processo_id !== $processo->id || $orcamento->processo_item_id !== $item->id) {
            return response()->json(['message' => 'Orçamento não pertence a este item.'], 404);
        }

        // Verificar permissão usando Policy
        $this->authorize('delete', $orcamento);

        $orcamento->delete();

        return response()->json(null, 204);
    }

    /**
     * Cria um orçamento vinculado ao processo (pode ter múltiplos itens)
     */
    public function storeByProcesso(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar se o processo pertence à empresa
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        // Verificar permissão usando Policy
        $this->authorize('create', [$processo]);

        $validated = $request->validate([
            'fornecedor_id' => 'required|exists:fornecedores,id',
            'transportadora_id' => 'nullable|exists:transportadoras,id',
            'itens' => 'required|array|min:1',
            'itens.*.processo_item_id' => 'required|exists:processo_itens,id',
            'itens.*.custo_produto' => 'required|numeric|min:0',
            'itens.*.marca_modelo' => 'nullable|string|max:255',
            'itens.*.ajustes_especificacao' => 'nullable|string',
            'itens.*.frete' => 'nullable|numeric|min:0',
            'itens.*.frete_incluido' => 'boolean',
            'itens.*.fornecedor_escolhido' => 'boolean',
            'itens.*.observacoes' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        // Verificar se todos os itens pertencem ao processo
        $itensIds = collect($validated['itens'])->pluck('processo_item_id')->unique();
        $itensDoProcesso = ProcessoItem::where('processo_id', $processo->id)
            ->whereIn('id', $itensIds)
            ->pluck('id')
            ->toArray();

        if (count($itensIds) !== count($itensDoProcesso)) {
            return response()->json([
                'message' => 'Um ou mais itens não pertencem a este processo.'
            ], 422);
        }

        // Criar orçamento vinculado ao processo com transação
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar se o processo pertence à empresa
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $orcamento = \Illuminate\Support\Facades\DB::transaction(function () use ($processo, $validated, $empresa) {
            $orcamento = Orcamento::create([
                'empresa_id' => $empresa->id,
                'processo_id' => $processo->id,
                'fornecedor_id' => $validated['fornecedor_id'],
                'transportadora_id' => $validated['transportadora_id'] ?? null,
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            // Criar itens do orçamento
            foreach ($validated['itens'] as $itemData) {
                $orcamentoItem = OrcamentoItem::create([
                    'orcamento_id' => $orcamento->id,
                    'processo_item_id' => $itemData['processo_item_id'],
                    'custo_produto' => $itemData['custo_produto'],
                    'marca_modelo' => $itemData['marca_modelo'] ?? null,
                    'ajustes_especificacao' => $itemData['ajustes_especificacao'] ?? null,
                    'frete' => $itemData['frete'] ?? 0,
                    'frete_incluido' => $itemData['frete_incluido'] ?? false,
                    'fornecedor_escolhido' => $itemData['fornecedor_escolhido'] ?? false,
                    'observacoes' => $itemData['observacoes'] ?? null,
                ]);

                // Se marcado como escolhido, desmarcar outros orçamentos do mesmo item
                if ($orcamentoItem->fornecedor_escolhido) {
                    ProcessoItem::find($itemData['processo_item_id'])
                        ->orcamentos()
                        ->where('id', '!=', $orcamento->id)
                        ->update(['fornecedor_escolhido' => false]);
                    
                    // Também desmarcar em orcamento_itens
                    OrcamentoItem::where('processo_item_id', $itemData['processo_item_id'])
                        ->where('id', '!=', $orcamentoItem->id)
                        ->update(['fornecedor_escolhido' => false]);
                }
            }

            return $orcamento;
        });

        $orcamento->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);

        return new OrcamentoResource($orcamento);
    }

    /**
     * Lista orçamentos vinculados ao processo
     */
    public function indexByProcesso(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar se o processo pertence à empresa
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        $orcamentos = $processo->orcamentos()
            ->with(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco'])
            ->orderBy('created_at', 'desc')
            ->get();

        return OrcamentoResource::collection($orcamentos);
    }

    /**
     * Atualiza o fornecedor_escolhido de um orcamento_item específico
     */
    public function updateOrcamentoItem(Request $request, Processo $processo, Orcamento $orcamento, $orcamentoItemId)
    {
        if ($orcamento->processo_id !== $processo->id) {
            return response()->json(['message' => 'Orçamento não pertence a este processo.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível alterar seleção de orçamentos em processos em execução.'
            ], 403);
        }

        $orcamentoItem = \App\Models\OrcamentoItem::where('id', $orcamentoItemId)
            ->where('orcamento_id', $orcamento->id)
            ->first();

        if (!$orcamentoItem) {
            return response()->json(['message' => 'Item do orçamento não encontrado.'], 404);
        }

        $validated = $request->validate([
            'fornecedor_escolhido' => 'required|boolean',
        ]);

        // Se está marcando como escolhido, desmarcar todos os outros do mesmo item
        if ($validated['fornecedor_escolhido']) {
            \App\Models\OrcamentoItem::where('processo_item_id', $orcamentoItem->processo_item_id)
                ->where('id', '!=', $orcamentoItem->id)
                ->update(['fornecedor_escolhido' => false]);
        }

        $orcamentoItem->update(['fornecedor_escolhido' => $validated['fornecedor_escolhido']]);

        // Atualizar valor mínimo no item se tiver formação de preço
        if ($validated['fornecedor_escolhido'] && $orcamentoItem->formacaoPreco) {
            $processoItem = $orcamentoItem->processoItem;
            $processoItem->valor_minimo_venda = $orcamentoItem->formacaoPreco->preco_minimo;
            $processoItem->save();
        } elseif (!$validated['fornecedor_escolhido']) {
            $processoItem = $orcamentoItem->processoItem;
            $processoItem->valor_minimo_venda = null;
            $processoItem->save();
        }

        $orcamento->refresh();
        $orcamento->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);

        return new OrcamentoResource($orcamento);
    }
}







