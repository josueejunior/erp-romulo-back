<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Processo;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\RedisService;

class ContratoController extends BaseApiController
{
    /**
     * Lista todos os contratos (não apenas de um processo)
     * Com filtros, indicadores e paginação
     */
    public function listarTodos(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        // Criar chave de cache baseada nos filtros
        $filters = [
            'busca' => $request->busca,
            'orgao_id' => $request->orgao_id,
            'srp' => $request->has('srp') ? $request->boolean('srp') : null,
            'situacao' => $request->situacao,
            'vigente' => $request->has('vigente') ? $request->boolean('vigente') : null,
            'vencer_em' => $request->vencer_em,
            'somente_alerta' => $request->boolean('somente_alerta'),
            'page' => $request->page ?? 1,
        ];
        $cacheKey = "contratos:{$tenantId}:{$empresa->id}:" . md5(json_encode($filters));
        
        // Tentar obter do cache
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }
        
        // Filtrar APENAS contratos da empresa ativa (não incluir NULL)
        $query = Contrato::where('empresa_id', $empresa->id)
            ->whereNotNull('empresa_id')
            ->with([
            'processo:id,numero_modalidade,numero_processo_administrativo,orgao_id,setor_id,srp',
            'processo.orgao:id,uasg,razao_social',
            'processo.setor:id,nome',
            'empenhos:id,contrato_id,numero,valor',
            'autorizacoesFornecimento:id,contrato_id,numero'
        ]);

        // Filtro: busca (número do contrato, processo, órgão)
        if ($request->busca) {
            $query->where(function($q) use ($request) {
                $q->where('numero', 'like', "%{$request->busca}%")
                  ->orWhereHas('processo', function($p) use ($request) {
                      $p->where('numero_modalidade', 'like', "%{$request->busca}%")
                        ->orWhere('numero_processo_administrativo', 'like', "%{$request->busca}%");
                  })
                  ->orWhereHas('processo.orgao', function($o) use ($request, $empresa) {
                      $o->where('empresa_id', $empresa->id)
                        ->where(function($q) use ($request) {
                            $q->where('razao_social', 'like', "%{$request->busca}%")
                              ->orWhere('uasg', 'like', "%{$request->busca}%");
                        });
                  });
            });
        }

        // Filtro: órgão
        if ($request->orgao_id) {
            // Validar que o órgão pertence à empresa
            $orgao = \App\Models\Orgao::where('id', $request->orgao_id)
                ->where('empresa_id', $empresa->id)
                ->first();
            
            if (!$orgao) {
                return response()->json([
                    'message' => 'Órgão não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }
            
            $query->whereHas('processo', function($q) use ($request) {
                $q->where('orgao_id', $request->orgao_id);
            });
        }

        // Filtro: tipo (SRP ou não)
        if ($request->has('srp')) {
            $query->whereHas('processo', function($q) use ($request) {
                $q->where('srp', $request->boolean('srp'));
            });
        }

        // Filtro: status
        if ($request->situacao) {
            $query->where('situacao', $request->situacao);
        }

        // Filtro: vigência
        if ($request->vigente !== null) {
            $query->where('vigente', $request->boolean('vigente'));
        }

        // Filtro: vigência a vencer (30/60/90 dias)
        if ($request->vencer_em) {
            $dias = (int)$request->vencer_em;
            $dataLimite = Carbon::now()->addDays($dias);
            $query->where('data_fim', '<=', $dataLimite)
                  ->where('data_fim', '>=', Carbon::now())
                  ->where('vigente', true);
        }

        // Filtro: somente com alerta
        if ($request->boolean('somente_alerta')) {
            $hoje = Carbon::now();
            $query->where(function($q) use ($hoje) {
                // Vigência vencendo em até 30 dias
                $q->where(function($sub) use ($hoje) {
                    $sub->where('data_fim', '<=', $hoje->copy()->addDays(30))
                        ->where('data_fim', '>=', $hoje)
                        ->where('vigente', true);
                })
                // Saldo baixo (menor que 10% do valor total)
                ->orWhereRaw('saldo < (valor_total * 0.1)')
                // Contrato vencido mas ainda com saldo
                ->orWhere(function($sub) use ($hoje) {
                    $sub->where('data_fim', '<', $hoje)
                        ->where('saldo', '>', 0);
                });
            });
        }

        // Calcular indicadores ANTES da paginação
        $totalQuery = clone $query;
        $indicadores = $this->calcularIndicadores($totalQuery);

        // Ordenação
        $ordenacao = $request->ordenacao ?? 'data_fim';
        $direcao = $request->direcao ?? 'asc';
        $query->orderBy($ordenacao, $direcao);

        // Paginação
        $perPage = $request->per_page ?? 15;
        $contratos = $query->paginate($perPage);

        $response = [
            'data' => $contratos->items(),
            'indicadores' => $indicadores,
            'pagination' => [
                'current_page' => $contratos->currentPage(),
                'last_page' => $contratos->lastPage(),
                'per_page' => $contratos->perPage(),
                'total' => $contratos->total(),
            ],
        ];

        // Salvar no cache (5 minutos)
        if ($tenantId && RedisService::isAvailable()) {
            RedisService::set($cacheKey, $response, 300);
        }

        return response()->json($response);
    }

    /**
     * Calcula indicadores dos contratos
     */
    private function calcularIndicadores($query)
    {
        $hoje = Carbon::now();
        $trintaDias = $hoje->copy()->addDays(30);

        $todos = $query->get();

        return [
            'contratos_ativos' => $todos->where('vigente', true)->count(),
            'contratos_a_vencer' => $todos->where('vigente', true)
                ->filter(function($c) use ($trintaDias, $hoje) {
                    return $c->data_fim && 
                           $c->data_fim->between($hoje, $trintaDias);
                })->count(),
            'saldo_total_contratado' => $todos->sum('valor_total'),
            'saldo_ja_faturado' => $todos->sum('valor_empenhado'),
            'saldo_restante' => $todos->sum('saldo'),
            'margem_media' => $this->calcularMargemMedia($todos),
        ];
    }

    /**
     * Calcula margem média dos contratos
     */
    private function calcularMargemMedia($contratos)
    {
        if ($contratos->isEmpty()) {
            return 0;
        }

        $margens = [];
        
        foreach ($contratos as $contrato) {
            // Buscar notas fiscais de entrada (custos) e saída (receitas) vinculadas
            $notasEntrada = \App\Models\NotaFiscal::whereHas('empenho', function($q) use ($contrato) {
                $q->where('contrato_id', $contrato->id);
            })->orWhereHas('contrato', function($q) use ($contrato) {
                $q->where('id', $contrato->id);
            })->where('tipo', 'entrada')->get();
            
            $notasSaida = \App\Models\NotaFiscal::whereHas('empenho', function($q) use ($contrato) {
                $q->where('contrato_id', $contrato->id);
            })->orWhereHas('contrato', function($q) use ($contrato) {
                $q->where('id', $contrato->id);
            })->where('tipo', 'saida')->get();

            $custoTotal = $notasEntrada->sum('custo_total') ?? $notasEntrada->sum('custo_produto') ?? 0;
            $receitaTotal = $notasSaida->sum('valor') ?? 0;

            // Se não tiver notas fiscais, usar valor do contrato como receita
            if ($receitaTotal == 0 && $contrato->valor_total > 0) {
                $receitaTotal = $contrato->valor_total;
            }

            if ($receitaTotal > 0 && $custoTotal > 0) {
                $lucro = $receitaTotal - $custoTotal;
                $margem = ($lucro / $receitaTotal) * 100;
                $margens[] = $margem;
            }
        }

        if (empty($margens)) {
            return 0;
        }

        return round(array_sum($margens) / count($margens), 2);
    }

    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $contratos = $processo->contratos()->where('empresa_id', $empresa->id)->with(['empenhos', 'autorizacoesFornecimento'])->get();
        return response()->json($contratos);
    }

    public function store(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        // Verificar permissão usando Policy
        $this->authorize('create', [\App\Models\Contrato::class, $processo]);

        $validated = $request->validate([
            'numero' => 'required|string|max:255',
            'data_assinatura' => 'nullable|date',
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after:data_inicio',
            'valor_total' => 'required|numeric|min:0',
            'status' => 'nullable|in:ativo,encerrado,suspenso',
            'situacao' => 'nullable|in:vigente,encerrado,cancelado',
            'observacoes' => 'nullable|string',
            'arquivo_contrato' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'numero_cte' => 'nullable|string|max:255',
        ]);

        $validated['empresa_id'] = $empresa->id;
        $validated['processo_id'] = $processo->id;
        $validated['saldo'] = $validated['valor_total'];
        
        // Se não tiver situacao, usar status ou default
        if (!isset($validated['situacao']) && isset($validated['status'])) {
            $validated['situacao'] = $validated['status'] === 'ativo' ? 'vigente' : 
                                    ($validated['status'] === 'encerrado' ? 'encerrado' : 'vigente');
        } elseif (!isset($validated['situacao'])) {
            $validated['situacao'] = 'vigente';
        }

        $contrato = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request) {
            // Upload de arquivo
            if ($request->hasFile('arquivo_contrato')) {
                $arquivo = $request->file('arquivo_contrato');
                $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
                $caminho = $arquivo->storeAs('contratos', $nomeArquivo, 'public');
                $validated['arquivo_contrato'] = $caminho;
            }

            $contrato = Contrato::create($validated);
            
            return $contrato;
        });

        // Limpar cache de contratos
        $tenantId = tenancy()->tenant?->id;
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "contratos:{$tenantId}:{$empresa->id}:*";
            try {
                $cursor = 0;
                do {
                    $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        \Illuminate\Support\Facades\Redis::del($keys);
                    }
                } while ($cursor != 0);
            } catch (\Exception $e) {
                \Log::warning('Erro ao limpar cache de contratos: ' . $e->getMessage());
            }
        }

        return response()->json($contrato, 201);
    }

    public function show(Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $contrato->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Contrato não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        if ($contrato->processo_id !== $processo->id) {
            return response()->json(['message' => 'Contrato não pertence a este processo.'], 404);
        }

        $contrato->load(['empenhos', 'autorizacoesFornecimento']);
        return response()->json($contrato);
    }

    public function update(Request $request, Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $contrato->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Contrato não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        if ($contrato->processo_id !== $processo->id) {
            return response()->json(['message' => 'Contrato não pertence a este processo.'], 404);
        }

        // Verificar permissão usando Policy
        $this->authorize('update', $contrato);

        $validated = $request->validate([
            'numero' => 'required|string|max:255',
            'data_assinatura' => 'nullable|date',
            'data_inicio' => 'required|date',
            'data_fim' => 'nullable|date|after:data_inicio',
            'valor_total' => 'required|numeric|min:0',
            'status' => 'nullable|in:ativo,encerrado,suspenso',
            'situacao' => 'nullable|in:vigente,encerrado,cancelado',
            'observacoes' => 'nullable|string',
            'arquivo_contrato' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'numero_cte' => 'nullable|string|max:255',
        ]);

        // Se não tiver situacao, usar status ou manter atual
        if (!isset($validated['situacao']) && isset($validated['status'])) {
            $validated['situacao'] = $validated['status'] === 'ativo' ? 'vigente' : 
                                    ($validated['status'] === 'encerrado' ? 'encerrado' : 'vigente');
        }

        // Upload de arquivo
        if ($request->hasFile('arquivo_contrato')) {
            // Deletar arquivo antigo se existir
            if ($contrato->arquivo_contrato && \Storage::disk('public')->exists($contrato->arquivo_contrato)) {
                \Storage::disk('public')->delete($contrato->arquivo_contrato);
            }
            
            $arquivo = $request->file('arquivo_contrato');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $caminho = $arquivo->storeAs('contratos', $nomeArquivo, 'public');
            $validated['arquivo_contrato'] = $caminho;
        }

        $valorTotalAnterior = $contrato->valor_total;
        
        DB::transaction(function () use ($contrato, $validated, $valorTotalAnterior) {
            $contrato->update($validated);

            if ($validated['valor_total'] != $valorTotalAnterior) {
                $contrato->atualizarSaldo();
            }
        });

        // Limpar cache de contratos
        $tenantId = tenancy()->tenant?->id;
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "contratos:{$tenantId}:{$empresa->id}:*";
            try {
                $cursor = 0;
                do {
                    $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        \Illuminate\Support\Facades\Redis::del($keys);
                    }
                } while ($cursor != 0);
            } catch (\Exception $e) {
                \Log::warning('Erro ao limpar cache de contratos: ' . $e->getMessage());
            }
        }

        return response()->json($contrato);
    }

    public function destroy(Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id || $contrato->empresa_id !== $empresa->id) {
            return response()->json(['message' => 'Contrato não encontrado ou não pertence à empresa ativa.'], 404);
        }
        
        if ($contrato->processo_id !== $processo->id) {
            return response()->json(['message' => 'Contrato não pertence a este processo.'], 404);
        }

        // Verificar permissão usando Policy
        $this->authorize('delete', $contrato);

        if ($contrato->empenhos()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um contrato que possui empenhos vinculados.'
            ], 403);
        }

        $contrato->forceDelete();

        // Limpar cache de contratos
        $tenantId = tenancy()->tenant?->id;
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "contratos:{$tenantId}:{$empresa->id}:*";
            try {
                $cursor = 0;
                do {
                    $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        \Illuminate\Support\Facades\Redis::del($keys);
                    }
                } while ($cursor != 0);
            } catch (\Exception $e) {
                \Log::warning('Erro ao limpar cache de contratos: ' . $e->getMessage());
            }
        }

        return response()->json(null, 204);
    }
}








