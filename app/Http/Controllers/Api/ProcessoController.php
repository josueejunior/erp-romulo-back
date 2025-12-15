<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessoResource;
use App\Models\Processo;
use App\Models\Orgao;
use App\Services\ProcessoStatusService;
use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;

class ProcessoController extends Controller
{
    protected ProcessoStatusService $statusService;

    public function __construct(ProcessoStatusService $statusService)
    {
        $this->statusService = $statusService;
    }
    public function index(Request $request)
    {
        $query = Processo::with(['orgao', 'setor']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('numero_modalidade', 'like', "%{$request->search}%")
                  ->orWhere('objeto_resumido', 'like', "%{$request->search}%");
            });
        }

        $processos = $query->orderBy('created_at', 'desc')->paginate(15);

        return ProcessoResource::collection($processos);
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
            'setor_id' => 'required|exists:setors,id',
            'modalidade' => 'required|in:dispensa,pregao',
            'numero_modalidade' => 'required|string',
            'numero_processo_administrativo' => 'nullable|string',
            'srp' => 'boolean',
            'objeto_resumido' => 'required|string',
            'data_hora_sessao_publica' => 'required|date',
            'endereco_entrega' => 'nullable|string',
            'forma_prazo_entrega' => 'nullable|string',
            'prazo_pagamento' => 'nullable|string',
            'validade_proposta' => 'nullable|string',
            'tipo_selecao_fornecedor' => 'nullable|string',
            'tipo_disputa' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        // Com Tenancy, não precisamos mais de empresa_id - cada tenant tem seu próprio banco
        $validated['status'] = 'participacao';
        $validated['srp'] = $request->has('srp');

        $processo = Processo::create($validated);

        return new ProcessoResource($processo->load(['orgao', 'setor']));
    }

    public function show(Processo $processo)
    {
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
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Processos em execução não podem ser editados.'
            ], 403);
        }

        $validated = $request->validate([
            'orgao_id' => 'required|exists:orgaos,id',
            'setor_id' => 'required|exists:setors,id',
            'modalidade' => 'required|in:dispensa,pregao',
            'numero_modalidade' => 'required|string',
            'numero_processo_administrativo' => 'nullable|string',
            'srp' => 'boolean',
            'objeto_resumido' => 'required|string',
            'data_hora_sessao_publica' => 'required|date',
            'endereco_entrega' => 'nullable|string',
            'forma_prazo_entrega' => 'nullable|string',
            'prazo_pagamento' => 'nullable|string',
            'validade_proposta' => 'nullable|string',
            'tipo_selecao_fornecedor' => 'nullable|string',
            'tipo_disputa' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $validated['srp'] = $request->has('srp');

        $processo->update($validated);

        return new ProcessoResource($processo->load(['orgao', 'setor']));
    }

    public function destroy(Processo $processo)
    {
        if ($processo->isEmExecucao()) {
            return response()->json([
                'message' => 'Processos em execução não podem ser excluídos.'
            ], 403);
        }

        $processo->delete();

        return response()->json(null, 204);
    }

    public function marcarVencido(Request $request, Processo $processo)
    {
        // Verificar permissão
        if (!PermissionHelper::canMarkProcessStatus()) {
            return response()->json([
                'message' => 'Você não tem permissão para marcar processos como vencidos.'
            ], 403);
        }

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

    public function marcarPerdido(Request $request, Processo $processo)
    {
        // Verificar permissão
        if (!PermissionHelper::canMarkProcessStatus()) {
            return response()->json([
                'message' => 'Você não tem permissão para marcar processos como perdidos.'
            ], 403);
        }

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
