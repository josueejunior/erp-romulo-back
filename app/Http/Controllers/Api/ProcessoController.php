<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ProcessoResource;
use App\Models\Processo;
use App\Models\Orgao;
use App\Services\ProcessoStatusService;
use App\Services\ProcessoValidationService;
use App\Services\RedisService;
use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessoController extends BaseApiController
{
    protected ProcessoStatusService $statusService;
    protected ProcessoValidationService $validationService;

    public function __construct(ProcessoStatusService $statusService, ProcessoValidationService $validationService)
    {
        $this->statusService = $statusService;
        $this->validationService = $validationService;
    }
    public function index(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        $query = Processo::where('empresa_id', $empresa->id)->with([
            'orgao',
            'setor',
            'itens.formacoesPreco',
            'itens.orcamentos.fornecedor',
            'itens.orcamentos.transportadora',
            'itens.orcamentos.formacaoPreco',
            'documentos.documentoHabilitacao',
            'empenhos',
            'contratos',
        ]);

        // Filtro de status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filtro de modalidade
        if ($request->modalidade) {
            $query->where('modalidade', $request->modalidade);
        }

        // Filtro de órgão
        if ($request->orgao_id) {
            $query->where('orgao_id', $request->orgao_id);
        }

        // Filtro de período (sessão)
        if ($request->periodo_sessao_inicio) {
            $query->where('data_hora_sessao_publica', '>=', $request->periodo_sessao_inicio);
        }
        if ($request->periodo_sessao_fim) {
            $query->where('data_hora_sessao_publica', '<=', $request->periodo_sessao_fim);
        }

        // Filtro: somente com alerta
        if ($request->boolean('somente_alerta')) {
            $query->where(function($q) {
                $q->where(function($q2) {
                    $q2->where('status', 'participacao')
                       ->where('data_hora_sessao_publica', '<=', now());
                })
                ->orWhere(function($q2) {
                    $q2->where('status', 'julgamento_habilitacao')
                       ->where('updated_at', '<=', now()->subDays(7));
                })
                ->orWhereHas('empenhos', function($q2) {
                    $q2->where('situacao', 'atrasado');
                })
                ->orWhereHas('documentos.documentoHabilitacao', function($q2) {
                    $q2->where('data_validade', '<', now());
                });
            });
        }

        // Busca livre
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('numero_modalidade', 'like', "%{$request->search}%")
                  ->orWhere('numero_processo_administrativo', 'like', "%{$request->search}%")
                  ->orWhere('objeto_resumido', 'like', "%{$request->search}%")
                  ->orWhereHas('orgao', function($q2) use ($request) {
                      $q2->where('uasg', 'like', "%{$request->search}%")
                         ->orWhere('razao_social', 'like', "%{$request->search}%");
                  });
            });
        }

        // Ordenação
        $orderBy = $request->order_by ?? 'created_at';
        $orderDir = $request->order_dir ?? 'desc';
        $query->orderBy($orderBy, $orderDir);

        $processos = $query->paginate($request->per_page ?? 15);

        $tenantId = tenancy()->tenant?->id;
        $filters = $request->only(['status', 'modalidade', 'orgao_id', 'busca', 'periodo_sessao_inicio', 'periodo_sessao_fim']);

        // Cache apenas para listagens sem paginação ou com primeira página
        if ($tenantId && RedisService::isAvailable() && ($request->per_page ?? 15) <= 20 && ($request->page ?? 1) == 1) {
            $cached = RedisService::getProcessos($tenantId, $filters);
            if ($cached !== null) {
                return \App\Http\Resources\ProcessoListResource::collection($cached);
            }
            
            // Salvar no cache
            RedisService::cacheProcessos($tenantId, $filters, $processos->items(), 180);
        }

        return \App\Http\Resources\ProcessoListResource::collection($processos);
    }

    /**
     * Retorna resumo estatístico dos processos
     */
    public function resumo(Request $request)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $query = Processo::where('empresa_id', $empresa->id);

        // Aplicar mesmos filtros da listagem (exceto paginação)
        if ($request->modalidade) {
            $query->where('modalidade', $request->modalidade);
        }
        if ($request->orgao_id) {
            $query->where('orgao_id', $request->orgao_id);
        }
        if ($request->periodo_sessao_inicio) {
            $query->where('data_hora_sessao_publica', '>=', $request->periodo_sessao_inicio);
        }
        if ($request->periodo_sessao_fim) {
            $query->where('data_hora_sessao_publica', '<=', $request->periodo_sessao_fim);
        }

        $totalParticipacao = (clone $query)->where('status', 'participacao')->count();
        $totalJulgamento = (clone $query)->where('status', 'julgamento_habilitacao')->count();
        $totalExecucao = (clone $query)->where('status', 'execucao')->count();
        $totalPagamento = (clone $query)->where('status', 'pagamento')->count();
        $totalEncerramento = (clone $query)->where('status', 'encerramento')->count();
        
        // Processos com alerta
        $totalComAlerta = (clone $query)->where(function($q) {
            $q->where(function($q2) {
                $q2->where('status', 'participacao')
                   ->where('data_hora_sessao_publica', '<=', now());
            })
            ->orWhere(function($q2) {
                $q2->where('status', 'julgamento_habilitacao')
                   ->where('updated_at', '<=', now()->subDays(7));
            })
            ->orWhereHas('empenhos', function($q2) {
                $q2->where('situacao', 'atrasado');
            })
            ->orWhereHas('documentos.documentoHabilitacao', function($q2) {
                $q2->where('data_validade', '<', now());
            });
        })->count();

        // Valor total em execução
        $processosExecucao = (clone $query)
            ->where('status', 'execucao')
            ->with(['itens' => function($q) {
                $q->whereIn('status_item', ['aceito', 'aceito_habilitado']);
            }])
            ->get();
        
        $valorTotalExecucao = $processosExecucao->sum(function($processo) {
            return $processo->itens->sum(function($item) {
                return $item->valor_negociado ?? $item->valor_final_sessao ?? $item->valor_estimado ?? 0;
            });
        });

        // Lucro estimado (simplificado - receita - custos diretos)
        $lucroEstimado = 0;
        foreach ($processosExecucao as $processo) {
            $receita = $processo->itens->sum(function($item) {
                return $item->valor_negociado ?? $item->valor_final_sessao ?? $item->valor_estimado ?? 0;
            });
            $custos = $processo->notasFiscais()->where('tipo', 'entrada')->sum('valor') ?? 0;
            $lucroEstimado += ($receita - $custos);
        }

        return response()->json([
            'participacao' => $totalParticipacao,
            'julgamento' => $totalJulgamento,
            'execucao' => $totalExecucao,
            'pagamento' => $totalPagamento,
            'encerramento' => $totalEncerramento,
            'com_alerta' => $totalComAlerta,
            'valor_total_execucao' => round($valorTotalExecucao, 2),
            'lucro_estimado' => round($lucroEstimado, 2),
        ]);
    }

    public function store(Request $request)
    {
        // Verificar permissão
        if (!PermissionHelper::canCreateProcess()) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $roles = $user ? $user->getRoleNames()->toArray() : [];
            
            // Limpar cache de permissões e tentar novamente
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            
            if (!PermissionHelper::canCreateProcess()) {
                return response()->json([
                    'message' => 'Você não tem permissão para criar processos. É necessário ter a role "Administrador" ou "Operacional".',
                    'user_roles' => $roles,
                    'user_email' => $user ? $user->email : null,
                    'help' => 'Use POST /api/user/fix-role com {"role": "Administrador"} para corrigir'
                ], 403);
            }
        }

        $validated = $request->validate([
            'orgao_id' => 'required|exists:orgaos,id',
            'setor_id' => 'nullable|exists:setors,id',
            'modalidade' => 'required|in:dispensa,pregao',
            'numero_modalidade' => 'required|string',
            'numero_processo_administrativo' => 'nullable|string',
            'srp' => 'boolean',
            'objeto_resumido' => 'required|string',
            'data_hora_sessao_publica' => 'required|date',
            'endereco_entrega' => 'nullable|string',
            'local_entrega_detalhado' => 'nullable|string',
            'forma_entrega' => 'nullable|string|in:parcelado,remessa_unica',
            'prazo_entrega' => 'nullable|string',
            'prazos_detalhados' => 'nullable|string',
            'prazo_pagamento' => 'nullable|string',
            'validade_proposta' => 'nullable|string',
            'tipo_selecao_fornecedor' => 'nullable|string|in:menor_preco_item,menor_preco_lote',
            'tipo_disputa' => 'nullable|string|in:aberto,aberto_fechado',
            'status_participacao' => 'nullable|string|in:normal,adiado,suspenso,cancelado',
            'observacoes' => 'nullable|string',
        ]);

        $empresa = $this->getEmpresaAtivaOrFail();
        $validated['empresa_id'] = $empresa->id;
        $validated['status'] = 'participacao';
        $validated['srp'] = $request->has('srp');

        $processo = DB::transaction(function () use ($validated, $request) {
            $processo = Processo::create($validated);

            // Salvar documentos de habilitação selecionados
            if ($request->has('documentos_habilitacao')) {
                $documentos = $request->input('documentos_habilitacao', []);
                foreach ($documentos as $docId => $docData) {
                    $processo->documentos()->create([
                        'documento_habilitacao_id' => $docId,
                        'exigido' => $docData['exigido'] ?? true,
                        'disponivel_envio' => $docData['disponivel_envio'] ?? false,
                        'observacoes' => $docData['observacoes'] ?? null,
                    ]);
                }
            }

            return $processo;
        });

        return new ProcessoResource($processo->load(['orgao', 'setor', 'documentos.documentoHabilitacao']));
    }

    public function show(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        $processo->load([
            'orgao',
            'setor',
            'itens.orcamentos.fornecedor',
            'itens.formacoesPreco',
            'documentos.documentoHabilitacao',
            'contratos',
            'autorizacoesFornecimento',
            'empenhos',
        ]);

        return new ProcessoResource($processo);
    }

    public function update(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        // Verificar permissão usando Policy
        $this->authorize('update', $processo);

        $validated = $request->validate([
            'orgao_id' => 'required|exists:orgaos,id',
            'setor_id' => 'nullable|exists:setors,id',
            'modalidade' => 'required|in:dispensa,pregao',
            'numero_modalidade' => 'required|string',
            'numero_processo_administrativo' => 'nullable|string',
            'srp' => 'boolean',
            'objeto_resumido' => 'required|string',
            'data_hora_sessao_publica' => 'required|date',
            'endereco_entrega' => 'nullable|string',
            'local_entrega_detalhado' => 'nullable|string',
            'forma_entrega' => 'nullable|string|in:parcelado,remessa_unica',
            'prazo_entrega' => 'nullable|string',
            'prazos_detalhados' => 'nullable|string',
            'prazo_pagamento' => 'nullable|string',
            'validade_proposta' => 'nullable|string',
            'tipo_selecao_fornecedor' => 'nullable|string|in:menor_preco_item,menor_preco_lote',
            'tipo_disputa' => 'nullable|string|in:aberto,aberto_fechado',
            'status_participacao' => 'nullable|string|in:normal,adiado,suspenso,cancelado',
            'data_recebimento_pagamento' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ]);

        $validated['srp'] = $request->has('srp');

        $processo->update($validated);

        // Atualizar documentos de habilitação selecionados
        if ($request->has('documentos_habilitacao')) {
            // Remover documentos não selecionados
            $documentosSelecionados = array_keys($request->input('documentos_habilitacao', []));
            $processo->documentos()->whereNotIn('documento_habilitacao_id', $documentosSelecionados)->delete();

            // Adicionar/atualizar documentos selecionados
            $documentos = $request->input('documentos_habilitacao', []);
            foreach ($documentos as $docId => $docData) {
                $processo->documentos()->updateOrCreate(
                    ['documento_habilitacao_id' => $docId],
                    [
                        'exigido' => $docData['exigido'] ?? true,
                        'disponivel_envio' => $docData['disponivel_envio'] ?? false,
                        'observacoes' => $docData['observacoes'] ?? null,
                    ]
                );
            }
        }

        return new ProcessoResource($processo->load(['orgao', 'setor', 'documentos.documentoHabilitacao']));
    }

    public function destroy(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        if ($processo->empresa_id !== $empresa->id) {
            return response()->json([
                'message' => 'Processo não encontrado ou não pertence à empresa ativa.'
            ], 404);
        }
        
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Processos em execução não podem ser excluídos.'
            ], 403);
        }

        $processo->forceDelete();

        return response()->json(null, 204);
    }

    /**
     * Exporta lista de processos em CSV
     */
    public function exportar(Request $request)
    {
        // Aplicar os mesmos filtros do index
        $query = Processo::with([
            'orgao',
            'setor',
            'itens',
        ]);

        // Filtro de status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filtro de modalidade
        if ($request->modalidade) {
            $query->where('modalidade', $request->modalidade);
        }

        // Filtro de órgão
        if ($request->orgao_id) {
            $query->where('orgao_id', $request->orgao_id);
        }

        // Filtro de período (sessão)
        if ($request->periodo_sessao_inicio) {
            $query->where('data_hora_sessao_publica', '>=', $request->periodo_sessao_inicio);
        }
        if ($request->periodo_sessao_fim) {
            $query->where('data_hora_sessao_publica', '<=', $request->periodo_sessao_fim);
        }

        // Filtro: somente com alerta
        if ($request->boolean('somente_alerta')) {
            $query->where(function($q) {
                $q->where(function($q2) {
                    $q2->where('status', 'participacao')
                       ->where('data_hora_sessao_publica', '<=', now());
                })
                ->orWhere(function($q2) {
                    $q2->where('status', 'julgamento_habilitacao')
                       ->where('updated_at', '<=', now()->subDays(7));
                })
                ->orWhereHas('empenhos', function($q2) {
                    $q2->where('situacao', 'atrasado');
                });
            });
        }

        // Busca livre
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('numero_modalidade', 'like', "%{$request->search}%")
                  ->orWhere('numero_processo_administrativo', 'like', "%{$request->search}%")
                  ->orWhere('objeto_resumido', 'like', "%{$request->search}%")
                  ->orWhereHas('orgao', function($q2) use ($request) {
                      $q2->where('uasg', 'like', "%{$request->search}%")
                         ->orWhere('razao_social', 'like', "%{$request->search}%");
                  });
            });
        }

        // Buscar todos os processos (sem paginação)
        $processos = $query->orderBy('created_at', 'desc')->get();

        // Gerar CSV
        $filename = 'processos_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($processos) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8 (Excel)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalhos
            fputcsv($file, [
                'ID',
                'Nº Modalidade',
                'Nº Processo Admin',
                'Modalidade',
                'Órgão',
                'UASG',
                'Setor',
                'Objeto',
                'Status',
                'SRP',
                'Data Sessão Pública',
                'Horário Sessão',
                'Link Edital',
                'Portal',
                'Nº Edital',
                'Valor Total Estimado',
                'Data Criação',
                'Data Atualização',
            ], ';');

            // Dados
            foreach ($processos as $processo) {
                // Calcular valor total estimado
                $valorTotal = $processo->itens->sum(function($item) {
                    return $item->valor_estimado_total ?? ($item->valor_estimado * $item->quantidade ?? 0);
                });

                fputcsv($file, [
                    $processo->id,
                    $processo->numero_modalidade,
                    $processo->numero_processo_administrativo ?? '',
                    ucfirst($processo->modalidade),
                    $processo->orgao->razao_social ?? '',
                    $processo->orgao->uasg ?? '',
                    $processo->setor->nome ?? '',
                    $processo->objeto_resumido,
                    ucfirst(str_replace('_', ' ', $processo->status)),
                    $processo->srp ? 'Sim' : 'Não',
                    $processo->data_hora_sessao_publica 
                        ? $processo->data_hora_sessao_publica->format('d/m/Y') 
                        : '',
                    $processo->horario_sessao_publica 
                        ? $processo->horario_sessao_publica->format('H:i') 
                        : '',
                    $processo->link_edital ?? '',
                    $processo->portal ?? '',
                    $processo->numero_edital ?? '',
                    number_format($valorTotal, 2, ',', '.'),
                    $processo->created_at->format('d/m/Y H:i'),
                    $processo->updated_at->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function marcarVencido(Request $request, Processo $processo)
    {
        // Verificar permissão usando Policy
        $this->authorize('markVencido', $processo);

        // Validar transição de status
        $validacao = $this->statusService->podeAlterarStatus($processo, 'vencido');
        if (!$validacao['pode']) {
            return response()->json([
                'message' => $validacao['motivo']
            ], 400);
        }

        // Alterar para vencido
        $resultado = $this->statusService->alterarStatus($processo, 'vencido');
        
        // Se confirmado vencido, mudar para execucao
        if ($request->boolean('confirmar_execucao', true)) {
            $resultado = $this->statusService->alterarStatus($processo, 'execucao');
        }

        return response()->json([
            'message' => 'Processo marcado como vencido com sucesso!',
            'processo' => new ProcessoResource($processo->fresh()),
        ]);
    }

    public function moverParaJulgamento(Request $request, Processo $processo)
    {
        // Verificar permissão usando Policy
        $this->authorize('changeStatus', $processo);

        // Validar transição de status
        $validacao = $this->statusService->podeAlterarStatus($processo, 'julgamento_habilitacao');
        if (!$validacao['pode']) {
            return response()->json([
                'message' => $validacao['motivo']
            ], 400);
        }

        // Validar pré-requisitos
        $validacaoPreRequisitos = $this->validationService->podeAvançarFase($processo, 'julgamento_habilitacao');
        if (!$validacaoPreRequisitos['pode']) {
            return response()->json([
                'message' => 'Não é possível avançar: ' . implode(' ', $validacaoPreRequisitos['erros']),
                'avisos' => $validacaoPreRequisitos['avisos'] ?? []
            ], 400);
        }

        // Alterar para julgamento_habilitacao
        $resultado = $this->statusService->alterarStatus($processo, 'julgamento_habilitacao');

        return response()->json([
            'message' => 'Processo movido para fase de Julgamento e Habilitação com sucesso!',
            'processo' => new ProcessoResource($processo->fresh()->load(['orgao', 'setor', 'itens'])),
            'avisos' => $validacaoPreRequisitos['avisos'] ?? []
        ]);
    }

    public function marcarPerdido(Request $request, Processo $processo)
    {
        // Verificar permissão usando Policy
        $this->authorize('markPerdido', $processo);

        // Validar transição de status
        $validacao = $this->statusService->podeAlterarStatus($processo, 'perdido');
        if (!$validacao['pode']) {
            return response()->json([
                'message' => $validacao['motivo']
            ], 400);
        }

        // Alterar para perdido
        $resultado = $this->statusService->alterarStatus($processo, 'perdido');
        
        // Se confirmado perdido, arquivar
        if ($request->boolean('arquivar', true)) {
            $resultado = $this->statusService->alterarStatus($processo, 'arquivado');
        }

        return response()->json([
            'message' => 'Processo marcado como perdido com sucesso!',
            'processo' => new ProcessoResource($processo->fresh()),
        ]);
    }

    /**
     * Sugerir próximo status baseado nas regras de negócio
     */
    public function sugerirStatus(Processo $processo)
    {
        $sugestao = $this->statusService->sugerirProximoStatus($processo);
        
        return response()->json([
            'sugerir_status' => $sugestao,
            'status_atual' => $processo->status,
            'deve_sugerir_julgamento' => $this->statusService->deveSugerirJulgamento($processo),
            'deve_sugerir_perdido' => $this->statusService->deveSugerirPerdido($processo),
        ]);
    }
}
