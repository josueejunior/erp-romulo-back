<?php

namespace App\Modules\Orcamento\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Orcamento\Models\FormacaoPreco;
use App\Modules\Orcamento\Models\Orcamento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FormacaoPrecoService
{
    /**
     * Validar dados para criação/atualização
     */
    public function validateData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'custo_produto' => 'required|numeric|min:0',
            'frete' => 'required|numeric|min:0',
            'percentual_impostos' => 'required|numeric|min:0|max:100',
            'percentual_margem' => 'required|numeric|min:0|max:99',
        ]);
    }

    /**
     * Criar ou atualizar formação de preço para um item
     */
    public function salvar(
        Processo $processo,
        ProcessoItem $item,
        array $data,
        int $empresaId,
        ?Orcamento $orcamento = null
    ): FormacaoPreco {
        $validator = $this->validateData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();

        // Procurar formação existente
        $formacao = FormacaoPreco::where('processo_id', $processo->id)
            ->where('processo_item_id', $item->id)
            ->first();

        if (!$formacao) {
            $formacao = new FormacaoPreco();
            $formacao->empresa_id = $empresaId;
            $formacao->processo_id = $processo->id;
            $formacao->processo_item_id = $item->id;
        }

        // Atualizar dados
        $formacao->custo_produto = $validated['custo_produto'];
        $formacao->frete = $validated['frete'];
        $formacao->percentual_impostos = $validated['percentual_impostos'];
        $formacao->percentual_margem = $validated['percentual_margem'];
        
        // Se houver orcamento vinculado
        if ($orcamento) {
            $formacao->orcamento_id = $orcamento->id;
        }

        // O método boot do model vai calcular preco_minimo automaticamente
        $formacao->save();

        return $formacao;
    }

    /**
     * Obter formação de preço para um item
     */
    public function obter(int $processoId, int $itemId): ?FormacaoPreco
    {
        return FormacaoPreco::where('processo_id', $processoId)
            ->where('processo_item_id', $itemId)
            ->first();
    }

    /**
     * Listar formações de preço por processo
     */
    public function listarPorProcesso(int $processoId): \Illuminate\Database\Eloquent\Collection
    {
        return FormacaoPreco::where('processo_id', $processoId)
            ->with(['processoItem', 'orcamento'])
            ->get();
    }

    /**
     * Calcular preço mínimo (fórmula: (custo+frete) * (1 + impostos) / (1 - margem))
     */
    public function calcularMinimo(float $custoProduto, float $frete, float $impostos, float $margem): float
    {
        $custo = $custoProduto + $frete;
        $impostoDecimal = $impostos / 100;
        $margemDecimal = $margem / 100;
        
        $comImposto = $custo * (1 + $impostoDecimal);
        
        if ($margemDecimal >= 1) {
            return 0; // Margem inválida
        }
        
        return round($comImposto / (1 - $margemDecimal), 2);
    }

    /**
     * Deletar formação de preço
     */
    public function deletar(int $processoId, int $itemId): bool
    {
        return FormacaoPreco::where('processo_id', $processoId)
            ->where('processo_item_id', $itemId)
            ->delete() > 0;
    }
}
