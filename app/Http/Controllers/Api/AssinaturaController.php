<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Tenant;
use App\Models\Plano;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AssinaturaController extends Controller
{
    /**
     * Listar assinaturas do tenant atual
     */
    public function index()
    {
        $tenantId = tenancy()->tenant?->id;
        
        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant ID não fornecido.'
            ], 400);
        }

        $assinaturas = Assinatura::where('tenant_id', $tenantId)
            ->with('plano')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $assinaturas
        ]);
    }

    /**
     * Obter assinatura atual do tenant
     */
    public function atual()
    {
        $tenant = tenancy()->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado.'
            ], 404);
        }

        // Buscar assinatura atual diretamente
        // Se tenant tem assinatura_atual_id, buscar por ele, senão buscar a mais recente ativa
        $assinatura = null;
        
        if ($tenant->assinatura_atual_id) {
            $assinatura = Assinatura::where('tenant_id', $tenant->id)
                ->where('id', $tenant->assinatura_atual_id)
                ->with('plano')
                ->first();
        }
        
        // Se não encontrou pela assinatura_atual_id, buscar a mais recente ativa
        if (!$assinatura) {
            $assinatura = Assinatura::where('tenant_id', $tenant->id)
                ->where('status', 'ativa')
                ->with('plano')
                ->orderBy('created_at', 'desc')
                ->first();
        }
        
        // Se ainda não encontrou, buscar qualquer assinatura mais recente
        if (!$assinatura) {
            $assinatura = Assinatura::where('tenant_id', $tenant->id)
                ->with('plano')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$assinatura) {
            return response()->json([
                'message' => 'Nenhuma assinatura encontrada.'
            ], 404);
        }
        
        // Calcular dias restantes
        $diasRestantes = $assinatura->diasRestantes();

        return response()->json([
            ...$assinatura->toArray(),
            'dias_restantes' => $diasRestantes,
        ]);
    }

    /**
     * Criar nova assinatura
     */
    public function store(Request $request)
    {
        $tenant = tenancy()->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'plano_id' => 'required|exists:planos,id',
            'periodo' => 'required|in:mensal,anual',
        ]);

        $plano = Plano::find($validated['plano_id']);

        if (!$plano->isAtivo()) {
            return response()->json([
                'message' => 'Este plano não está disponível.'
            ], 400);
        }

        // Cancelar assinatura anterior se existir
        if ($tenant->assinaturaAtual) {
            $tenant->assinaturaAtual->cancelar();
        }

        // Calcular valor e data fim
        $valor = $validated['periodo'] === 'anual' 
            ? $plano->preco_anual ?? ($plano->preco_mensal * 12)
            : $plano->preco_mensal;
        
        $meses = $validated['periodo'] === 'anual' ? 12 : 1;

        $assinatura = Assinatura::create([
            'tenant_id' => $tenant->id,
            'plano_id' => $plano->id,
            'status' => 'ativa',
            'data_inicio' => Carbon::now(),
            'data_fim' => Carbon::now()->addMonths($meses),
            'valor_pago' => $valor,
            'dias_grace_period' => 7,
        ]);

        // Atualizar tenant
        $tenant->plano_atual_id = $plano->id;
        $tenant->assinatura_atual_id = $assinatura->id;
        $tenant->limite_processos = $plano->limite_processos;
        $tenant->limite_usuarios = $plano->limite_usuarios;
        $tenant->save();

        $assinatura->load('plano');

        return response()->json([
            'message' => 'Assinatura criada com sucesso!',
            'data' => $assinatura
        ], 201);
    }

    /**
     * Renovar assinatura
     */
    public function renovar(Request $request, Assinatura $assinatura)
    {
        $tenant = tenancy()->tenant;
        
        if ($assinatura->tenant_id !== $tenant->id) {
            return response()->json([
                'message' => 'Assinatura não pertence a este tenant.'
            ], 403);
        }

        $validated = $request->validate([
            'meses' => 'required|integer|min:1|max:12',
        ]);

        $assinatura->renovar($validated['meses']);

        // Atualizar tenant se for a assinatura atual
        if ($tenant->assinatura_atual_id === $assinatura->id) {
            $tenant->save();
        }

        $assinatura->load('plano');

        return response()->json([
            'message' => 'Assinatura renovada com sucesso!',
            'data' => $assinatura
        ]);
    }

    /**
     * Cancelar assinatura
     */
    public function cancelar(Assinatura $assinatura)
    {
        $tenant = tenancy()->tenant;
        
        if ($assinatura->tenant_id !== $tenant->id) {
            return response()->json([
                'message' => 'Assinatura não pertence a este tenant.'
            ], 403);
        }

        $assinatura->cancelar();

        return response()->json([
            'message' => 'Assinatura cancelada com sucesso!',
            'data' => $assinatura
        ]);
    }

    /**
     * Obter status da assinatura com limites utilizados
     */
    public function status()
    {
        $tenant = tenancy()->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado.'
            ], 404);
        }

        // Buscar assinatura atual diretamente
        $assinatura = Assinatura::where('tenant_id', $tenant->id)
            ->where('id', $tenant->assinatura_atual_id)
            ->with('plano')
            ->first();

        if (!$assinatura) {
            return response()->json([
                'message' => 'Nenhuma assinatura encontrada.'
            ], 404);
        }

        $plano = $assinatura->plano;

        // Contar processos no tenant (já estamos no contexto do tenant)
        $processosUtilizados = \App\Models\Processo::count();

        // Contar usuários vinculados ao tenant
        // empresa_user está no banco do tenant, então podemos contar diretamente
        $usuariosUtilizados = 0;
        try {
            // Contar via empresa_user no tenant (já estamos no contexto do tenant)
            $usuariosUtilizados = \DB::table('empresa_user')
                ->distinct('user_id')
                ->count('user_id');
        } catch (\Exception $e) {
            // Se falhar, usar método alternativo via Empresa
            \Log::warning('Erro ao contar usuários do tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
            // Tentar via model Empresa
            try {
                $empresa = \App\Models\Empresa::first();
                if ($empresa) {
                    $usuariosUtilizados = $empresa->users()->count();
                }
            } catch (\Exception $e2) {
                \Log::warning('Erro ao contar usuários via Empresa', [
                    'error' => $e2->getMessage()
                ]);
            }
        }

        return response()->json([
            'assinatura' => [
                'id' => $assinatura->id,
                'status' => $assinatura->status,
                'data_fim' => $assinatura->data_fim,
                'dias_restantes' => $assinatura->diasRestantes(),
            ],
            'plano' => [
                'nome' => $plano->nome,
                'limite_processos' => $plano->limite_processos,
                'limite_usuarios' => $plano->limite_usuarios,
            ],
            'processos_utilizados' => $processosUtilizados,
            'usuarios_utilizados' => $usuariosUtilizados,
            'limite_processos' => $plano->limite_processos,
            'limite_usuarios' => $plano->limite_usuarios,
        ]);
    }
}
