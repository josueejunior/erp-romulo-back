<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ProcessoItemResource;
use App\Models\Processo;
use App\Models\ProcessoItem;
use Illuminate\Http\Request;

class ProcessoItemController extends BaseApiController
{
    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $itens = $processo->itens()->with([
            'orcamentos.fornecedor',
            'orcamentos.transportadora',
            'formacoesPreco',
            'vinculos.contrato',
            'vinculos.autorizacaoFornecimento',
            'vinculos.empenho',
        ])->get();
        
        // Atualizar valores financeiros de cada item
        foreach ($itens as $item) {
            $item->atualizarValoresFinanceiros();
        }
        
        return ProcessoItemResource::collection($itens);
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível adicionar itens a processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'numero_item' => 'required|integer|min:1',
            'codigo_interno' => 'nullable|string|max:100',
            'quantidade' => 'required|numeric|min:0.01',
            'unidade' => 'required|string|max:50',
            'especificacao_tecnica' => 'required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'observacoes_edital' => 'nullable|string',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'quantidade_atestado_cap_tecnica' => 'nullable|integer|min:0',
            'valor_estimado' => 'nullable|numeric|min:0',
            'valor_estimado_total' => 'nullable|numeric|min:0',
            'fonte_valor' => 'nullable|in:edital,pesquisa',
            'observacoes' => 'nullable|string',
        ]);

        $validated['processo_id'] = $processo->id;
        $validated['exige_atestado'] = $request->has('exige_atestado');
        $validated['status_item'] = 'pendente';
        
        // Calcular valor estimado total se não fornecido
        if (!isset($validated['valor_estimado_total']) && isset($validated['valor_estimado']) && isset($validated['quantidade'])) {
            $validated['valor_estimado_total'] = $validated['valor_estimado'] * $validated['quantidade'];
        }

        $item = ProcessoItem::create($validated);

        return new ProcessoItemResource($item);
    }

    public function show(Processo $processo, ProcessoItem $item)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        $item->load([
            'orcamentos.fornecedor',
            'orcamentos.transportadora',
            'formacoesPreco',
            'vinculos.contrato',
            'vinculos.autorizacaoFornecimento',
            'vinculos.empenho',
        ]);
        
        // Atualizar valores financeiros
        $item->atualizarValoresFinanceiros();
        
        return new ProcessoItemResource($item);
    }

    public function update(Request $request, Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível editar itens de processos em execução.'
            ], 403);
        }

        $validated = $request->validate([
            'numero_item' => 'sometimes|required|integer|min:1',
            'codigo_interno' => 'nullable|string|max:100',
            'quantidade' => 'sometimes|required|numeric|min:0.01',
            'unidade' => 'sometimes|required|string|max:50',
            'especificacao_tecnica' => 'sometimes|required|string',
            'marca_modelo_referencia' => 'nullable|string|max:255',
            'observacoes_edital' => 'nullable|string',
            'exige_atestado' => 'boolean',
            'quantidade_minima_atestado' => 'nullable|integer|min:1|required_if:exige_atestado,1',
            'quantidade_atestado_cap_tecnica' => 'nullable|integer|min:0',
            'valor_estimado' => 'nullable|numeric|min:0',
            'valor_estimado_total' => 'nullable|numeric|min:0',
            'fonte_valor' => 'nullable|in:edital,pesquisa',
            'valor_final_sessao' => 'nullable|numeric|min:0',
            'data_disputa' => 'nullable|date',
            'valor_negociado' => 'nullable|numeric|min:0',
            'classificacao' => 'nullable|integer|min:1',
            'status_item' => 'nullable|in:pendente,aceito,aceito_habilitado,desclassificado,inabilitado',
            'situacao_final' => 'nullable|in:vencido,perdido',
            'chance_arremate' => 'nullable|in:baixa,media,alta',
            'chance_percentual' => 'nullable|integer|min:0|max:100',
            'lembretes' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        if (isset($validated['exige_atestado'])) {
            $validated['exige_atestado'] = $request->boolean('exige_atestado');
        }

        // Recalcular valor estimado total se quantidade ou valor unitário mudou
        if (isset($validated['quantidade']) || isset($validated['valor_estimado'])) {
            $quantidade = $validated['quantidade'] ?? $item->quantidade;
            $valorUnitario = $validated['valor_estimado'] ?? $item->valor_estimado;
            if ($quantidade && $valorUnitario) {
                $validated['valor_estimado_total'] = $quantidade * $valorUnitario;
            }
        }

        $item->update($validated);
        
        // Atualizar valores financeiros após atualização
        $item->atualizarValoresFinanceiros();

        return new ProcessoItemResource($item);
    }

    public function destroy(Processo $processo, ProcessoItem $item)
    {
        if ($item->processo_id !== $processo->id) {
            return response()->json(['message' => 'Item não pertence a este processo.'], 404);
        }

        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível excluir itens de processos em execução.'
            ], 403);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * Importa itens de uma planilha Excel/CSV
     */
    public function importar(Request $request, Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Não é possível importar itens para processos em execução.'
            ], 403);
        }

        $request->validate([
            'planilha' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $arquivo = $request->file('planilha');
            $extensao = $arquivo->getClientOriginalExtension();
            
            // Ler arquivo Excel usando PhpSpreadsheet ou CSV
            if (in_array($extensao, ['xlsx', 'xls'])) {
                // Para Excel, precisaria do PhpSpreadsheet instalado
                // Por enquanto, retornar erro informando que precisa instalar
                return response()->json([
                    'message' => 'Importação de Excel requer PhpSpreadsheet. Por enquanto, use CSV.'
                ], 400);
            } else {
                // Processar CSV
                $handle = fopen($arquivo->getRealPath(), 'r');
                $cabecalhos = fgetcsv($handle, 1000, ',');
                
                // Normalizar cabeçalhos
                $cabecalhos = array_map('trim', array_map('strtolower', $cabecalhos));
                
                // Mapear colunas
                $mapColunas = [
                    'numero_item' => ['numero_item', 'numero', 'item', 'nº item', 'n° item'],
                    'quantidade' => ['quantidade', 'qtd', 'qtd.', 'qtde'],
                    'unidade' => ['unidade', 'un', 'unid'],
                    'especificacao_tecnica' => ['especificacao_tecnica', 'especificacao', 'descricao', 'descrição', 'especificação técnica'],
                    'valor_estimado' => ['valor_estimado', 'valor', 'preco', 'preço', 'valor unitário'],
                    'marca_modelo_referencia' => ['marca_modelo_referencia', 'marca', 'modelo', 'referencia', 'referência', 'marca/modelo'],
                ];
                
                $indices = [];
                foreach ($mapColunas as $campo => $variacoes) {
                    foreach ($variacoes as $variacao) {
                        $indice = array_search($variacao, $cabecalhos);
                        if ($indice !== false) {
                            $indices[$campo] = $indice;
                            break;
                        }
                    }
                }
                
                // Validar campos obrigatórios
                $camposObrigatorios = ['numero_item', 'quantidade', 'unidade', 'especificacao_tecnica'];
                $faltando = array_diff($camposObrigatorios, array_keys($indices));
                if (!empty($faltando)) {
                    return response()->json([
                        'message' => 'Colunas obrigatórias não encontradas: ' . implode(', ', $faltando)
                    ], 400);
                }
                
                $itensCriados = 0;
                $linha = 1;
                
                while (($dados = fgetcsv($handle, 1000, ',')) !== false) {
                    $linha++;
                    
                    // Pular linhas vazias
                    if (empty(array_filter($dados))) {
                        continue;
                    }
                    
                    try {
                        $itemData = [
                            'processo_id' => $processo->id,
                            'numero_item' => (int)($dados[$indices['numero_item']] ?? 0),
                            'quantidade' => (float)($dados[$indices['quantidade']] ?? 0),
                            'unidade' => trim($dados[$indices['unidade']] ?? ''),
                            'especificacao_tecnica' => trim($dados[$indices['especificacao_tecnica']] ?? ''),
                            'valor_estimado' => isset($indices['valor_estimado']) && $dados[$indices['valor_estimado']] 
                                ? (float)str_replace(',', '.', str_replace('.', '', $dados[$indices['valor_estimado']])) 
                                : null,
                            'marca_modelo_referencia' => isset($indices['marca_modelo_referencia']) 
                                ? trim($dados[$indices['marca_modelo_referencia']] ?? '') 
                                : null,
                            'status_item' => 'pendente',
                            'exige_atestado' => false,
                        ];
                        
                        // Validar dados básicos
                        if ($itemData['numero_item'] <= 0 || $itemData['quantidade'] <= 0 || empty($itemData['unidade']) || empty($itemData['especificacao_tecnica'])) {
                            continue; // Pular linha inválida
                        }
                        
                        // Calcular valor total se tiver valor unitário
                        if ($itemData['valor_estimado']) {
                            $itemData['valor_estimado_total'] = $itemData['valor_estimado'] * $itemData['quantidade'];
                        }
                        
                        ProcessoItem::create($itemData);
                        $itensCriados++;
                    } catch (\Exception $e) {
                        // Continuar processando outras linhas mesmo se uma falhar
                        \Log::warning("Erro ao importar item linha {$linha}: " . $e->getMessage());
                    }
                }
                
                fclose($handle);
                
                return response()->json([
                    'message' => "{$itensCriados} item(ns) importado(s) com sucesso.",
                    'itens_criados' => $itensCriados,
                ], 201);
            }
        } catch (\Exception $e) {
            \Log::error('Erro ao importar planilha: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erro ao processar planilha: ' . $e->getMessage()
            ], 500);
        }
    }
}






