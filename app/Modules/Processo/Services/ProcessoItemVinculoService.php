<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Models\ProcessoItemVinculo;
use App\Modules\Processo\Models\Processo;
use App\Models\Contrato;
use App\Models\AutorizacaoFornecimento;
use App\Models\Empenho;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcessoItemVinculoService
{
    /**
     * Valida dados para criar/atualizar vínculo
     */
    public function validateVinculoData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'processo_item_id' => 'required|exists:processo_itens,id',
            'contrato_id' => 'nullable|exists:contratos,id',
            'autorizacao_fornecimento_id' => 'nullable|exists:autorizacoes_fornecimento,id',
            'empenho_id' => 'nullable|exists:empenhos,id',
            'quantidade' => 'required|numeric|min:0.01',
            'valor_unitario' => 'required|numeric|min:0',
            'valor_total' => 'required|numeric|min:0',
            'observacoes' => 'nullable|string|max:1000',
        ], [
            'processo_item_id.required' => 'O item do processo é obrigatório.',
            'processo_item_id.exists' => 'O item do processo não foi encontrado.',
            'contrato_id.exists' => 'O contrato não foi encontrado.',
            'autorizacao_fornecimento_id.exists' => 'A autorização de fornecimento não foi encontrada.',
            'empenho_id.exists' => 'O empenho não foi encontrado.',
            'quantidade.required' => 'A quantidade é obrigatória.',
            'quantidade.min' => 'A quantidade deve ser maior que zero.',
            'valor_unitario.required' => 'O valor unitário é obrigatório.',
            'valor_unitario.min' => 'O valor unitário deve ser maior ou igual a zero.',
            'valor_total.required' => 'O valor total é obrigatório.',
            'valor_total.min' => 'O valor total deve ser maior ou igual a zero.',
        ]);
    }

    /**
     * Valida se pelo menos um vínculo foi informado
     */
    public function validateVinculoExists(array $data): void
    {
        if (empty($data['contrato_id']) && empty($data['autorizacao_fornecimento_id']) && empty($data['empenho_id'])) {
            throw new \Exception('É necessário informar pelo menos um vínculo (Contrato, AF ou Empenho).');
        }
    }

    /**
     * Valida se a quantidade não excede a disponível
     */
    public function validateQuantidade(ProcessoItem $item, float $quantidade, ?ProcessoItemVinculo $vinculoExcluir = null): void
    {
        $quantidadeVinculada = $item->vinculos()
            ->when($vinculoExcluir, function ($query) use ($vinculoExcluir) {
                return $query->where('id', '!=', $vinculoExcluir->id);
            })
            ->sum('quantidade');

        $quantidadeDisponivel = $item->quantidade - $quantidadeVinculada;

        if ($quantidade > $quantidadeDisponivel) {
            throw new \Exception(
                "Quantidade solicitada ({$quantidade}) excede a quantidade disponível ({$quantidadeDisponivel}). " .
                "Quantidade total do item: {$item->quantidade}, já vinculada: {$quantidadeVinculada}."
            );
        }
    }

    /**
     * Valida se o item pertence ao processo
     */
    public function validateItemProcesso(ProcessoItem $item, Processo $processo): void
    {
        if ($item->processo_id !== $processo->id) {
            throw new \Exception('O item não pertence a este processo.');
        }
    }

    /**
     * Valida se o documento (Contrato/AF/Empenho) pertence ao processo
     */
    public function validateDocumentoProcesso(array $data, Processo $processo): void
    {
        if (!empty($data['contrato_id'])) {
            $contrato = Contrato::find($data['contrato_id']);
            if (!$contrato || $contrato->processo_id !== $processo->id) {
                throw new \Exception('O contrato não pertence a este processo.');
            }
        }

        if (!empty($data['autorizacao_fornecimento_id'])) {
            $af = AutorizacaoFornecimento::find($data['autorizacao_fornecimento_id']);
            if (!$af || $af->processo_id !== $processo->id) {
                throw new \Exception('A autorização de fornecimento não pertence a este processo.');
            }
        }

        if (!empty($data['empenho_id'])) {
            $empenho = Empenho::find($data['empenho_id']);
            if (!$empenho || $empenho->processo_id !== $processo->id) {
                throw new \Exception('O empenho não pertence a este processo.');
            }
        }
    }

    /**
     * Valida empresa
     */
    public function validateEmpresa(ProcessoItem $item, int $empresaId): void
    {
        if ($item->empresa_id !== $empresaId) {
            throw new \Exception('O item não pertence à empresa ativa.');
        }
    }

    /**
     * Lista vínculos de um item
     */
    public function listByItem(ProcessoItem $item): array
    {
        $vinculos = $item->vinculos()
            ->with(['contrato', 'autorizacaoFornecimento', 'empenho'])
            ->get();

        return $vinculos->map(function ($vinculo) {
            return [
                'id' => $vinculo->id,
                'processo_item_id' => $vinculo->processo_item_id,
                'contrato_id' => $vinculo->contrato_id,
                'contrato' => $vinculo->contrato ? [
                    'id' => $vinculo->contrato->id,
                    'numero' => $vinculo->contrato->numero,
                ] : null,
                'autorizacao_fornecimento_id' => $vinculo->autorizacao_fornecimento_id,
                'autorizacao_fornecimento' => $vinculo->autorizacaoFornecimento ? [
                    'id' => $vinculo->autorizacaoFornecimento->id,
                    'numero' => $vinculo->autorizacaoFornecimento->numero,
                ] : null,
                'empenho_id' => $vinculo->empenho_id,
                'empenho' => $vinculo->empenho ? [
                    'id' => $vinculo->empenho->id,
                    'numero' => $vinculo->empenho->numero,
                ] : null,
                'quantidade' => (float) $vinculo->quantidade,
                'valor_unitario' => (float) $vinculo->valor_unitario,
                'valor_total' => (float) $vinculo->valor_total,
                'observacoes' => $vinculo->observacoes,
                'created_at' => $vinculo->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $vinculo->updated_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    /**
     * Cria um novo vínculo
     */
    public function store(Processo $processo, ProcessoItem $item, array $data, int $empresaId): ProcessoItemVinculo
    {
        // Validações
        $this->validateEmpresa($item, $empresaId);
        $this->validateItemProcesso($item, $processo);
        $this->validateDocumentoProcesso($data, $processo);
        $this->validateVinculoExists($data);

        $validator = $this->validateVinculoData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $this->validateQuantidade($item, (float) $validated['quantidade']);

        // Calcular valor_total se não fornecido
        if (empty($validated['valor_total']) && !empty($validated['quantidade']) && !empty($validated['valor_unitario'])) {
            $validated['valor_total'] = (float) $validated['quantidade'] * (float) $validated['valor_unitario'];
        }

        return DB::transaction(function () use ($item, $validated, $empresaId) {
            $vinculo = ProcessoItemVinculo::create([
                'empresa_id' => $empresaId,
                'processo_item_id' => $item->id,
                'contrato_id' => $validated['contrato_id'] ?? null,
                'autorizacao_fornecimento_id' => $validated['autorizacao_fornecimento_id'] ?? null,
                'empenho_id' => $validated['empenho_id'] ?? null,
                'quantidade' => (float) $validated['quantidade'],
                'valor_unitario' => (float) $validated['valor_unitario'],
                'valor_total' => (float) $validated['valor_total'],
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            // Atualizar valores financeiros do item
            $item->atualizarValoresFinanceiros();

            return $vinculo->load(['contrato', 'autorizacaoFornecimento', 'empenho']);
        });
    }

    /**
     * Atualiza um vínculo existente
     */
    public function update(Processo $processo, ProcessoItem $item, ProcessoItemVinculo $vinculo, array $data, int $empresaId): ProcessoItemVinculo
    {
        // Validações
        $this->validateEmpresa($item, $empresaId);
        $this->validateItemProcesso($item, $processo);
        
        if ($vinculo->processo_item_id !== $item->id) {
            throw new \Exception('O vínculo não pertence a este item.');
        }

        $this->validateDocumentoProcesso($data, $processo);
        $this->validateVinculoExists($data);

        $validator = $this->validateVinculoData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $this->validateQuantidade($item, (float) $validated['quantidade'], $vinculo);

        // Calcular valor_total se não fornecido
        if (empty($validated['valor_total']) && !empty($validated['quantidade']) && !empty($validated['valor_unitario'])) {
            $validated['valor_total'] = (float) $validated['quantidade'] * (float) $validated['valor_unitario'];
        }

        return DB::transaction(function () use ($vinculo, $item, $validated) {
            $vinculo->update([
                'contrato_id' => $validated['contrato_id'] ?? null,
                'autorizacao_fornecimento_id' => $validated['autorizacao_fornecimento_id'] ?? null,
                'empenho_id' => $validated['empenho_id'] ?? null,
                'quantidade' => (float) $validated['quantidade'],
                'valor_unitario' => (float) $validated['valor_unitario'],
                'valor_total' => (float) $validated['valor_total'],
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            // Atualizar valores financeiros do item
            $item->atualizarValoresFinanceiros();

            return $vinculo->load(['contrato', 'autorizacaoFornecimento', 'empenho']);
        });
    }

    /**
     * Remove um vínculo
     */
    public function delete(Processo $processo, ProcessoItem $item, ProcessoItemVinculo $vinculo, int $empresaId): void
    {
        // Validações
        $this->validateEmpresa($item, $empresaId);
        $this->validateItemProcesso($item, $processo);
        
        if ($vinculo->processo_item_id !== $item->id) {
            throw new \Exception('O vínculo não pertence a este item.');
        }

        DB::transaction(function () use ($vinculo, $item) {
            $vinculo->delete();

            // Atualizar valores financeiros do item
            $item->atualizarValoresFinanceiros();
        });
    }
}

