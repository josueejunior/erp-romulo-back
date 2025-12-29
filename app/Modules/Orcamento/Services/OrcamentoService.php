<?php

namespace App\Modules\Orcamento\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrcamentoService
{
    /**
     * Validar processo pertence à empresa
     */
    public function validarProcessoEmpresa(Processo $processo, int $empresaId): void
    {
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado ou não pertence à empresa ativa.');
        }
    }

    /**
     * Validar item pertence ao processo
     */
    public function validarItemPertenceProcesso(ProcessoItem $item, Processo $processo): void
    {
        if ($item->processo_id !== $processo->id) {
            throw new \Exception('Item não pertence a este processo.');
        }
    }

    /**
     * Validar orçamento pertence à empresa
     */
    public function validarOrcamentoEmpresa(Orcamento $orcamento, int $empresaId): void
    {
        if ($orcamento->empresa_id !== $empresaId) {
            throw new \Exception('Orçamento não encontrado ou não pertence à empresa ativa.');
        }
    }

    /**
     * Validar orçamento pertence ao item/processo
     */
    public function validarOrcamentoPertenceItem(Orcamento $orcamento, ProcessoItem $item, Processo $processo): void
    {
        $isOrcamentoDoItem = $orcamento->processo_item_id === $item->id;
        $isOrcamentoDoProcesso = $orcamento->processo_id === $processo->id && $item->processo_id === $processo->id;
        
        if (!$isOrcamentoDoItem && !$isOrcamentoDoProcesso) {
            throw new \Exception('Orçamento não pertence a este item/processo.');
        }
    }

    /**
     * Validar dados para criação de orçamento
     */
    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'fornecedor_id' => 'required|exists:fornecedores,id',
            'transportadora_id' => 'nullable|exists:transportadoras,id',
            'custo_produto' => 'required|numeric|min:0',
            'marca_modelo' => 'nullable|string|max:255',
            'ajustes_especificacao' => 'nullable|string',
            'frete' => 'nullable|numeric|min:0',
            'frete_incluido' => 'boolean',
            'observacoes' => 'nullable|string',
        ]);
    }

    /**
     * Criar orçamento vinculado a item
     */
    public function store(Processo $processo, ProcessoItem $item, array $data, int $empresaId): Orcamento
    {
        $this->validarProcessoEmpresa($processo, $empresaId);
        $this->validarItemPertenceProcesso($item, $processo);

        $validator = $this->validateStoreData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['empresa_id'] = $empresaId;
        $validated['processo_item_id'] = $item->id;
        $validated['frete'] = $validated['frete'] ?? 0;
        $validated['frete_incluido'] = isset($data['frete_incluido']) && $data['frete_incluido'];
        $validated['fornecedor_escolhido'] = false;

        // Se marcar como escolhido, desmarcar outros
        if (isset($data['fornecedor_escolhido']) && $data['fornecedor_escolhido']) {
            $item->orcamentos()->update(['fornecedor_escolhido' => false]);
            $validated['fornecedor_escolhido'] = true;
        }

        $orcamento = Orcamento::create($validated);
        $orcamento->load(['fornecedor', 'transportadora']);

        return $orcamento;
    }

    /**
     * Validar dados para atualização de orçamento
     */
    public function validateUpdateData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
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
    }

    /**
     * Atualizar orçamento
     */
    public function update(Processo $processo, ProcessoItem $item, Orcamento $orcamento, array $data, int $empresaId): Orcamento
    {
        $this->validarProcessoEmpresa($processo, $empresaId);
        $this->validarOrcamentoEmpresa($orcamento, $empresaId);
        $this->validarOrcamentoPertenceItem($orcamento, $item, $processo);

        $validator = $this->validateUpdateData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $updateData = [];
        
        foreach (['fornecedor_id', 'transportadora_id', 'custo_produto', 'marca_modelo', 'ajustes_especificacao', 'observacoes'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['frete'])) {
            $updateData['frete'] = $data['frete'] ?? 0;
        }

        if (isset($data['frete_incluido'])) {
            $updateData['frete_incluido'] = isset($data['frete_incluido']) && $data['frete_incluido'];
        }

        // Gerenciar fornecedor_escolhido
        if (isset($data['fornecedor_escolhido'])) {
            $fornecedorEscolhido = isset($data['fornecedor_escolhido']) && $data['fornecedor_escolhido'];
            
            if ($fornecedorEscolhido) {
                $item->orcamentos()->where('id', '!=', $orcamento->id)->update(['fornecedor_escolhido' => false]);
            }
            
            $updateData['fornecedor_escolhido'] = $fornecedorEscolhido;
        }

        if (!empty($updateData)) {
            $orcamento->update($updateData);
        }

        $orcamento->refresh();
        $orcamento->load(['fornecedor', 'transportadora', 'formacaoPreco']);

        // Atualizar valor mínimo no item se necessário
        if (isset($updateData['fornecedor_escolhido']) && $updateData['fornecedor_escolhido'] && $orcamento->formacaoPreco) {
            $item->valor_minimo_venda = $orcamento->formacaoPreco->preco_minimo;
            if (method_exists($item, 'calcularValorMinimoVenda')) {
                $item->calcularValorMinimoVenda();
            }
            $item->save();
        } elseif (isset($updateData['fornecedor_escolhido']) && !$updateData['fornecedor_escolhido']) {
            $item->valor_minimo_venda = null;
            $item->save();
        }

        return $orcamento;
    }

    /**
     * Validar dados para criação de orçamento por processo
     */
    public function validateStoreByProcessoData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
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
    }

    /**
     * Criar orçamento vinculado ao processo (múltiplos itens)
     */
    public function storeByProcesso(Processo $processo, array $data, int $empresaId): Orcamento
    {
        $this->validarProcessoEmpresa($processo, $empresaId);

        $validator = $this->validateStoreByProcessoData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();

        // Verificar se todos os itens pertencem ao processo
        $itensIds = collect($validated['itens'])->pluck('processo_item_id')->unique();
        $itensDoProcesso = ProcessoItem::where('processo_id', $processo->id)
            ->whereIn('id', $itensIds)
            ->pluck('id')
            ->toArray();

        if (count($itensIds) !== count($itensDoProcesso)) {
            throw new \Exception('Um ou mais itens não pertencem a este processo.');
        }

        return DB::transaction(function () use ($processo, $validated, $empresaId) {
            $orcamento = Orcamento::create([
                'empresa_id' => $empresaId,
                'processo_id' => $processo->id,
                'fornecedor_id' => $validated['fornecedor_id'],
                'transportadora_id' => $validated['transportadora_id'] ?? null,
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

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

                // Se marcado como escolhido, desmarcar outros
                if ($orcamentoItem->fornecedor_escolhido) {
                    ProcessoItem::find($itemData['processo_item_id'])
                        ->orcamentos()
                        ->where('id', '!=', $orcamento->id)
                        ->update(['fornecedor_escolhido' => false]);
                    
                    OrcamentoItem::where('processo_item_id', $itemData['processo_item_id'])
                        ->where('id', '!=', $orcamentoItem->id)
                        ->update(['fornecedor_escolhido' => false]);
                }
            }

            return $orcamento;
        });
    }

    /**
     * Atualizar fornecedor_escolhido de um orcamento_item
     */
    public function updateOrcamentoItem(Processo $processo, Orcamento $orcamento, int $orcamentoItemId, bool $fornecedorEscolhido, int $empresaId): Orcamento
    {
        $this->validarProcessoEmpresa($processo, $empresaId);

        if ($orcamento->processo_id !== $processo->id) {
            throw new \Exception('Orçamento não pertence a este processo.');
        }

        if ($processo->isEmExecucao()) {
            throw new \Exception('Não é possível alterar seleção de orçamentos em processos em execução.');
        }

        $orcamentoItem = OrcamentoItem::where('id', $orcamentoItemId)
            ->where('orcamento_id', $orcamento->id)
            ->first();

        if (!$orcamentoItem) {
            throw new \Exception('Item do orçamento não encontrado.');
        }

        // Se está marcando como escolhido, desmarcar todos os outros do mesmo item
        if ($fornecedorEscolhido) {
            OrcamentoItem::where('processo_item_id', $orcamentoItem->processo_item_id)
                ->where('id', '!=', $orcamentoItem->id)
                ->update(['fornecedor_escolhido' => false]);
        }

        $orcamentoItem->update(['fornecedor_escolhido' => $fornecedorEscolhido]);

        // Atualizar valor mínimo no item se tiver formação de preço
        if ($fornecedorEscolhido && $orcamentoItem->formacaoPreco) {
            $processoItem = $orcamentoItem->processoItem;
            $processoItem->valor_minimo_venda = $orcamentoItem->formacaoPreco->preco_minimo;
            $processoItem->save();
        } elseif (!$fornecedorEscolhido) {
            $processoItem = $orcamentoItem->processoItem;
            $processoItem->valor_minimo_venda = null;
            $processoItem->save();
        }

        $orcamento->refresh();
        $orcamento->load(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco']);

        return $orcamento;
    }

    /**
     * Listar orçamentos de um item
     */
    public function listByItem(ProcessoItem $item): \Illuminate\Database\Eloquent\Collection
    {
        return $item->orcamentos()
            ->with(['fornecedor', 'transportadora', 'formacaoPreco'])
            ->orderBy(\App\Database\Schema\Blueprint::CREATED_AT, 'desc')
            ->get();
    }

    /**
     * Listar orçamentos de um processo
     */
    public function listByProcesso(Processo $processo): \Illuminate\Database\Eloquent\Collection
    {
        return $processo->orcamentos()
            ->with(['fornecedor', 'transportadora', 'itens.processoItem', 'itens.formacaoPreco'])
            ->orderBy(\App\Database\Schema\Blueprint::CREATED_AT, 'desc')
            ->get();
    }

    /**
     * Buscar orçamento
     */
    public function find(Processo $processo, ProcessoItem $item, Orcamento $orcamento, int $empresaId): Orcamento
    {
        $this->validarProcessoEmpresa($processo, $empresaId);
        $this->validarOrcamentoEmpresa($orcamento, $empresaId);
        $this->validarOrcamentoPertenceItem($orcamento, $item, $processo);

        $orcamento->load(['fornecedor', 'transportadora', 'formacaoPreco']);
        return $orcamento;
    }

    /**
     * Excluir orçamento
     */
    public function delete(Processo $processo, ProcessoItem $item, Orcamento $orcamento): void
    {
        $this->validarItemPertenceProcesso($item, $processo);
        
        if ($orcamento->processo_item_id !== $item->id) {
            throw new \Exception('Orçamento não pertence a este item.');
        }

        $orcamento->delete();
    }
}




