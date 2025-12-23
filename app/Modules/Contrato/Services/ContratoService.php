<?php

namespace App\Modules\Contrato\Services;

use App\Modules\Processo\Models\Processo;
use App\Models\Contrato;
use App\Services\RedisService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContratoService
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
     * Validar contrato pertence à empresa
     */
    public function validarContratoEmpresa(Contrato $contrato, int $empresaId): void
    {
        if ($contrato->empresa_id !== $empresaId) {
            throw new \Exception('Contrato não encontrado ou não pertence à empresa ativa.');
        }
    }

    /**
     * Validar contrato pertence ao processo
     */
    public function validarContratoPertenceProcesso(Contrato $contrato, Processo $processo): void
    {
        if ($contrato->processo_id !== $processo->id) {
            throw new \Exception('Contrato não pertence a este processo.');
        }
    }

    /**
     * Validar contrato pode ser excluído
     */
    public function validarPodeExcluir(Contrato $contrato): void
    {
        if ($contrato->empenhos()->count() > 0) {
            throw new \Exception('Não é possível excluir um contrato que possui empenhos vinculados.');
        }
    }

    /**
     * Validar dados para criação
     */
    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
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
    }

    /**
     * Processar upload de arquivo
     */
    protected function processarArquivo($request): ?string
    {
        if ($request->hasFile('arquivo_contrato')) {
            $arquivo = $request->file('arquivo_contrato');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $caminho = $arquivo->storeAs('contratos', $nomeArquivo, 'public');
            return $caminho;
        }
        return null;
    }

    /**
     * Normalizar situação do contrato
     */
    protected function normalizarSituacao(array $data, ?Contrato $contrato = null): string
    {
        if (isset($data['situacao'])) {
            return $data['situacao'];
        }

        if (isset($data['status'])) {
            return $data['status'] === 'ativo' ? 'vigente' : 
                   ($data['status'] === 'encerrado' ? 'encerrado' : 'vigente');
        }

        return $contrato?->situacao ?? 'vigente';
    }

    /**
     * Criar contrato
     */
    public function store(Processo $processo, array $data, $request, int $empresaId): Contrato
    {
        $this->validarProcessoEmpresa($processo, $empresaId);

        $validator = $this->validateStoreData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['empresa_id'] = $empresaId;
        $validated['processo_id'] = $processo->id;
        $validated['saldo'] = $validated['valor_total'];
        $validated['situacao'] = $this->normalizarSituacao($validated);

        return DB::transaction(function () use ($validated, $request) {
            $arquivo = $this->processarArquivo($request);
            if ($arquivo) {
                $validated['arquivo_contrato'] = $arquivo;
            }

            $contrato = Contrato::create($validated);
            
            // Limpar cache
            $this->limparCache($validated['empresa_id']);
            
            return $contrato;
        });
    }

    /**
     * Validar dados para atualização
     */
    public function validateUpdateData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return $this->validateStoreData($data);
    }

    /**
     * Atualizar contrato
     */
    public function update(Processo $processo, Contrato $contrato, array $data, $request, int $empresaId): Contrato
    {
        $this->validarProcessoEmpresa($processo, $empresaId);
        $this->validarContratoEmpresa($contrato, $empresaId);
        $this->validarContratoPertenceProcesso($contrato, $processo);

        $validator = $this->validateUpdateData($data);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['situacao'] = $this->normalizarSituacao($validated, $contrato);

        $valorTotalAnterior = $contrato->valor_total;

        DB::transaction(function () use ($contrato, $validated, $request) {
            // Upload de arquivo
            if ($request->hasFile('arquivo_contrato')) {
                if ($contrato->arquivo_contrato && Storage::disk('public')->exists($contrato->arquivo_contrato)) {
                    Storage::disk('public')->delete($contrato->arquivo_contrato);
                }
                $arquivo = $this->processarArquivo($request);
                if ($arquivo) {
                    $validated['arquivo_contrato'] = $arquivo;
                }
            }

            $contrato->update($validated);

            if ($validated['valor_total'] != $valorTotalAnterior) {
                $contrato->atualizarSaldo();
            }
        });

        // Limpar cache
        $this->limparCache($empresaId);

        return $contrato;
    }

    /**
     * Excluir contrato
     */
    public function delete(Processo $processo, Contrato $contrato, int $empresaId): void
    {
        $this->validarProcessoEmpresa($processo, $empresaId);
        $this->validarContratoEmpresa($contrato, $empresaId);
        $this->validarContratoPertenceProcesso($contrato, $processo);
        $this->validarPodeExcluir($contrato);

        $contrato->forceDelete();

        // Limpar cache
        $this->limparCache($empresaId);
    }

    /**
     * Listar contratos de um processo
     */
    public function listByProcesso(Processo $processo, int $empresaId): \Illuminate\Database\Eloquent\Collection
    {
        $this->validarProcessoEmpresa($processo, $empresaId);

        return $processo->contratos()
            ->where('empresa_id', $empresaId)
            ->with(['empenhos', 'autorizacoesFornecimento'])
            ->get();
    }

    /**
     * Buscar contrato
     */
    public function find(Processo $processo, Contrato $contrato, int $empresaId): Contrato
    {
        $this->validarProcessoEmpresa($processo, $empresaId);
        $this->validarContratoEmpresa($contrato, $empresaId);
        $this->validarContratoPertenceProcesso($contrato, $processo);

        $contrato->load(['empenhos', 'autorizacoesFornecimento']);
        return $contrato;
    }

    /**
     * Limpar cache de contratos
     */
    protected function limparCache(int $empresaId): void
    {
        $tenantId = tenancy()->tenant?->id;
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "contratos:{$tenantId}:{$empresaId}:*";
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
    }

    /**
     * Calcular indicadores dos contratos
     */
    public function calcularIndicadores($query): array
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
    protected function calcularMargemMedia($contratos): float
    {
        if ($contratos->isEmpty()) {
            return 0;
        }

        $margens = [];
        
        foreach ($contratos as $contrato) {
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

    /**
     * Aplicar filtros à query de contratos
     */
    public function aplicarFiltros($query, array $filtros, int $empresaId)
    {
        // Filtro: busca (número do contrato, processo, órgão)
        if (!empty($filtros['busca'])) {
            $query->where(function($q) use ($filtros, $empresaId) {
                $q->where('numero', 'like', "%{$filtros['busca']}%")
                  ->orWhereHas('processo', function($p) use ($filtros) {
                      $p->where('numero_modalidade', 'like', "%{$filtros['busca']}%")
                        ->orWhere('numero_processo_administrativo', 'like', "%{$filtros['busca']}%");
                  })
                  ->orWhereHas('processo.orgao', function($o) use ($filtros, $empresaId) {
                      $o->where('empresa_id', $empresaId)
                        ->where(function($q) use ($filtros) {
                            $q->where('razao_social', 'like', "%{$filtros['busca']}%")
                              ->orWhere('uasg', 'like', "%{$filtros['busca']}%");
                        });
                  });
            });
        }

        // Filtro: órgão
        if (!empty($filtros['orgao_id'])) {
            // Validar que o órgão pertence à empresa
            $orgao = \App\Models\Orgao::where('id', $filtros['orgao_id'])
                ->where('empresa_id', $empresaId)
                ->first();
            
            if (!$orgao) {
                throw new \Exception('Órgão não encontrado ou não pertence à empresa ativa.');
            }
            
            $query->whereHas('processo', function($q) use ($filtros) {
                $q->where('orgao_id', $filtros['orgao_id']);
            });
        }

        // Filtro: tipo (SRP ou não)
        if (isset($filtros['srp']) && $filtros['srp'] !== null) {
            $query->whereHas('processo', function($q) use ($filtros) {
                $q->where('srp', $filtros['srp']);
            });
        }

        // Filtro: status
        if (!empty($filtros['situacao'])) {
            $query->where('situacao', $filtros['situacao']);
        }

        // Filtro: vigência
        if (isset($filtros['vigente']) && $filtros['vigente'] !== null) {
            $query->where('vigente', $filtros['vigente']);
        }

        // Filtro: vigência a vencer (30/60/90 dias)
        if (!empty($filtros['vencer_em'])) {
            $dias = (int)$filtros['vencer_em'];
            $dataLimite = Carbon::now()->addDays($dias);
            $query->where('data_fim', '<=', $dataLimite)
                  ->where('data_fim', '>=', Carbon::now())
                  ->where('vigente', true);
        }

        // Filtro: somente com alerta
        if (!empty($filtros['somente_alerta'])) {
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

        return $query;
    }

    /**
     * Listar todos os contratos com filtros, indicadores e paginação
     */
    public function listarTodos(array $filtros, int $empresaId, ?string $ordenacao = 'data_fim', ?string $direcao = 'asc', int $perPage = 15): array
    {
        // Filtrar APENAS contratos da empresa ativa (não incluir NULL)
        $query = Contrato::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->with([
                'processo:id,numero_modalidade,numero_processo_administrativo,orgao_id,setor_id,srp',
                'processo.orgao:id,uasg,razao_social',
                'processo.setor:id,nome',
                'empenhos:id,contrato_id,numero,valor',
                'autorizacoesFornecimento:id,contrato_id,numero'
            ]);

        // Aplicar filtros
        $query = $this->aplicarFiltros($query, $filtros, $empresaId);

        // Calcular indicadores ANTES da paginação
        $totalQuery = clone $query;
        $indicadores = $this->calcularIndicadores($totalQuery);

        // Ordenação
        $query->orderBy($ordenacao, $direcao);

        // Paginação
        $contratos = $query->paginate($perPage);

        return [
            'data' => $contratos->items(),
            'indicadores' => $indicadores,
            'pagination' => [
                'current_page' => $contratos->currentPage(),
                'last_page' => $contratos->lastPage(),
                'per_page' => $contratos->perPage(),
                'total' => $contratos->total(),
            ],
        ];
    }
}


