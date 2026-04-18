<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Models\ProcessoItemVinculo;
use App\Modules\Processo\Models\Processo;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcessoItemVinculoService
{
    public function __construct(
        private ContratoRepositoryInterface $contratoRepository,
        private AutorizacaoFornecimentoRepositoryInterface $autorizacaoRepository,
        private EmpenhoRepositoryInterface $empenhoRepository,
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {}
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
            'nota_fiscal_id' => 'nullable|exists:notas_fiscais,id',
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
            'nota_fiscal_id.exists' => 'A nota fiscal não foi encontrada.',
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
        if (empty($data['contrato_id']) && empty($data['autorizacao_fornecimento_id']) && empty($data['empenho_id']) && empty($data['nota_fiscal_id'])) {
            throw new \Exception('É necessário informar pelo menos um vínculo (Contrato, AF, Empenho ou Nota Fiscal).');
        }
    }

    /**
     * Valida se a quantidade não excede a disponível
     */
    /**
     * Valida se a quantidade não excede a disponível
     */
    public function validateQuantidade(ProcessoItem $item, float $quantidade, array $data, ?ProcessoItemVinculo $vinculoExcluir = null): void
    {
        \Log::info('ProcessoItemVinculoService::validateQuantidade', [
            'data' => $data,
            'ignore_flag' => $data['ignore_quantity_check'] ?? 'NULL',
            'ignore_is_true' => (!empty($data['ignore_quantity_check']) && $data['ignore_quantity_check'] === true),
            'nota_fiscal_id' => $data['nota_fiscal_id'] ?? 'NULL'
        ]);

        // 1. Se a flag de ignorar estiver presente (vinda do Controller para Entradas), retornar imediatamente.
        if (!empty($data['ignore_quantity_check']) && $data['ignore_quantity_check'] === true) {
            \Log::info('Ignorando validação de quantidade via flag explícita');
            return;
        }

        // 2. Fallback: Se for uma Nota Fiscal de ENTRADA (Custo), não validar limite de quantidade do item.
        if (!empty($data['nota_fiscal_id'])) {
            $notaFiscal = $this->notaFiscalRepository->buscarPorId($data['nota_fiscal_id']);
            
            // Log do resultado do Repository
            \Log::info('Checando Nota Fiscal via Repository', [
                'found' => (bool) $notaFiscal,
                'tipo' => $notaFiscal ? $notaFiscal->tipo : 'N/A'
            ]);
            
            if ($notaFiscal && strtolower($notaFiscal->tipo) === 'entrada') {
                \Log::info('Ignorando validação de quantidade via tipo entrada (repository)');
                return; 
            }

            // FALLBACK ROBUSTO: Se o repository falhar (ex: escopo de tenant), tenta direto no banco.
            try {
                $tipoDb = DB::table('notas_fiscais')->where('id', $data['nota_fiscal_id'])->value('tipo');
                \Log::info('Checando Nota Fiscal via DB', ['tipo_db' => $tipoDb]);
                
                if ($tipoDb && strtolower($tipoDb) === 'entrada') {
                    \Log::info('Ignorando validação de quantidade via tipo entrada (DB Raw)');
                    return; 
                }
            } catch (\Exception $e) {
                \Log::error('Erro ao checar tipo via DB: ' . $e->getMessage());
            }
        }

        // Validar por tipo de documento para permitir fluxo (Contrato -> AF -> Empenho -> NF)
        // Cada nível pode ter até a quantidade total do item.
        $tipos = [
            'contrato_id' => 'Contrato',
            'autorizacao_fornecimento_id' => 'AF',
            'empenho_id' => 'Empenho',
            'nota_fiscal_id' => 'Nota Fiscal'
        ];
        
        foreach ($tipos as $campo => $label) {
            if (!empty($data[$campo])) {
                
                // 🔥 LÓGICA DE HIERARQUIA: Evitar dupla contagem contra o saldo do ITEM.
                // Se estamos criando/editando uma Nota Fiscal, não devemos validar se o Empenho/Contrato pai cabe no Item.
                // Eles já cabem (foram validados quando criados). A NF apenas consome o saldo deles (que seria outra validação).
                
                // 1. Se tem NF, ignorar validação de limites globais dos pais (Empenho, AF, Contrato)
                if (!empty($data['nota_fiscal_id']) && $campo !== 'nota_fiscal_id') {
                    continue; 
                }

                // 2. Se tem Empenho (e não tem NF), ignorar validação de limites dos pais (AF, Contrato)
                if (!empty($data['empenho_id']) && empty($data['nota_fiscal_id']) && ($campo === 'contrato_id' || $campo === 'autorizacao_fornecimento_id')) {
                    continue;
                }
                
                // 3. Se tem AF (e não tem NF nem Empenho), ignorar validação de limites do pai (Contrato)
                if (!empty($data['autorizacao_fornecimento_id']) && empty($data['nota_fiscal_id']) && empty($data['empenho_id']) && $campo === 'contrato_id') {
                    continue;
                }

                // Ao calcular o que já foi consumido, IGNORAR notas de entrada
                $quantidadeVinculada = $item->vinculos()
                    ->whereNotNull($campo)
                    ->when($vinculoExcluir, function ($query) use ($vinculoExcluir) {
                        return $query->where('id', '!=', $vinculoExcluir->id);
                    })
                    ->where(function($q) {
                        // Considerar se NÃO tem NF (é empenho/contrato puro) 
                        // OU se tem NF, ela NÃO pode ser de entrada
                        $q->whereNull('nota_fiscal_id')
                          ->orWhereHas('notaFiscal', function($nf) {
                              $nf->where('tipo', '!=', 'entrada');
                          });
                    })
                    ->sum('quantidade');

                $disponivel = $item->quantidade - $quantidadeVinculada;

                if ($quantidade > $disponivel) {
                    throw new \Exception(
                        "Quantidade solicitada ({$quantidade}) excede a quantidade disponível para {$label} ({$disponivel}). " .
                        "Quantidade total do item: {$item->quantidade}, já vinculada a {$label}: {$quantidadeVinculada}."
                    );
                }
            }
        }
        
        // Se não houver documento específico, validar contra o saldo geral (fallback)
        if (empty($data['contrato_id']) && empty($data['autorizacao_fornecimento_id']) && 
            empty($data['empenho_id']) && empty($data['nota_fiscal_id'])) {
            $quantidadeVinculada = $item->vinculos()
                ->when($vinculoExcluir, function ($query) use ($vinculoExcluir) {
                    return $query->where('id', '!=', $vinculoExcluir->id);
                })
                ->where(function($q) {
                     $q->whereNull('nota_fiscal_id')
                       ->orWhereHas('notaFiscal', function($nf) {
                           $nf->where('tipo', '!=', 'entrada');
                       });
                })
                ->sum('quantidade');
            
            $disponivel = $item->quantidade - $quantidadeVinculada;
            if ($quantidade > $disponivel) {
                throw new \Exception("Quantidade indisponível: {$quantidade} solicitada, {$disponivel} disponível.");
            }
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
            $contrato = $this->contratoRepository->buscarPorId($data['contrato_id']);
            if (!$contrato || $contrato->processoId !== $processo->id) {
                throw new \Exception('O contrato não pertence a este processo.');
            }
        }

        if (!empty($data['autorizacao_fornecimento_id'])) {
            $af = $this->autorizacaoRepository->buscarPorId($data['autorizacao_fornecimento_id']);
            if (!$af || $af->processoId !== $processo->id) {
                throw new \Exception('A autorização de fornecimento não pertence a este processo.');
            }
        }

        if (!empty($data['empenho_id'])) {
            $empenho = $this->empenhoRepository->buscarPorId($data['empenho_id']);
            if (!$empenho || $empenho->processoId !== $processo->id) {
                throw new \Exception('O empenho não pertence a este processo.');
            }
        }

        if (!empty($data['nota_fiscal_id'])) {
            $notaFiscal = $this->notaFiscalRepository->buscarPorId($data['nota_fiscal_id']);
            if (!$notaFiscal || $notaFiscal->processoId !== $processo->id) {
                throw new \Exception('A nota fiscal não pertence a este processo.');
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
            ->with(['contrato', 'autorizacaoFornecimento', 'empenho', 'notaFiscal'])
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
                'nota_fiscal_id' => $vinculo->nota_fiscal_id,
                'nota_fiscal' => $vinculo->notaFiscal ? [
                    'id' => $vinculo->notaFiscal->id,
                    'numero' => $vinculo->notaFiscal->numero,
                    'tipo' => $vinculo->notaFiscal->tipo,
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
        $this->validateQuantidade($item, (float) $validated['quantidade'], $data);

        // Calcular valor_total se não fornecido
        if (empty($validated['valor_total']) && !empty($validated['quantidade']) && !empty($validated['valor_unitario'])) {
            $validated['valor_total'] = (float) $validated['quantidade'] * (float) $validated['valor_unitario'];
        }

        return DB::transaction(function () use ($item, $validated, $empresaId) {
            // Se houver empenho mas não houver contrato/AF, tentar herdar do empenho
            if (!empty($validated['empenho_id'])) {
                $empenhoModel = \App\Modules\Empenho\Models\Empenho::find($validated['empenho_id']);
                if ($empenhoModel) {
                    if (empty($validated['contrato_id'])) {
                        $validated['contrato_id'] = $empenhoModel->contrato_id;
                    }
                    if (empty($validated['autorizacao_fornecimento_id'])) {
                        $validated['autorizacao_fornecimento_id'] = $empenhoModel->autorizacao_fornecimento_id;
                    }
                }
            }

            $vinculo = ProcessoItemVinculo::create([
                'empresa_id' => $empresaId,
                'processo_item_id' => $item->id,
                'contrato_id' => $validated['contrato_id'] ?? null,
                'autorizacao_fornecimento_id' => $validated['autorizacao_fornecimento_id'] ?? null,
                'empenho_id' => $validated['empenho_id'] ?? null,
                'nota_fiscal_id' => $validated['nota_fiscal_id'] ?? null,
                'quantidade' => (float) $validated['quantidade'],
                'valor_unitario' => (float) $validated['valor_unitario'],
                'valor_total' => (float) $validated['valor_total'],
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            // Atualizar valores financeiros do item
            $item->atualizarValoresFinanceiros();

            return $vinculo->load(['contrato', 'autorizacaoFornecimento', 'empenho', 'notaFiscal']);
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
        $this->validateQuantidade($item, (float) $validated['quantidade'], $data, $vinculo);

        // Calcular valor_total se não fornecido
        if (empty($validated['valor_total']) && !empty($validated['quantidade']) && !empty($validated['valor_unitario'])) {
            $validated['valor_total'] = (float) $validated['quantidade'] * (float) $validated['valor_unitario'];
        }

        return DB::transaction(function () use ($vinculo, $item, $validated) {
            $vinculo->update([
                'contrato_id' => $validated['contrato_id'] ?? null,
                'autorizacao_fornecimento_id' => $validated['autorizacao_fornecimento_id'] ?? null,
                'empenho_id' => $validated['empenho_id'] ?? null,
                'nota_fiscal_id' => $validated['nota_fiscal_id'] ?? null,
                'quantidade' => (float) $validated['quantidade'],
                'valor_unitario' => (float) $validated['valor_unitario'],
                'valor_total' => (float) $validated['valor_total'],
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            // Atualizar valores financeiros do item
            $item->atualizarValoresFinanceiros();

            return $vinculo->load(['contrato', 'autorizacaoFornecimento', 'empenho', 'notaFiscal']);
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

