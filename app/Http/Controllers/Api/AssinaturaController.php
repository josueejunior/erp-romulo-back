<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller stub para Assinaturas
 * TODO: Implementar funcionalidade completa
 */
class AssinaturaController extends BaseApiController
{
    /**
     * Retorna assinatura atual do tenant
     */
    public function atual(Request $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado'
            ], 404);
        }

        // Buscar assinatura atual do tenant
        $assinatura = null;
        
        // Se o tenant tem assinatura_atual_id, buscar por ele
        if ($tenant->assinatura_atual_id) {
            $assinatura = \App\Modules\Assinatura\Models\Assinatura::with('plano')
                ->where('tenant_id', $tenant->id)
                ->where('id', $tenant->assinatura_atual_id)
                ->first();
        }
        
        // Se não encontrou, buscar a assinatura mais recente do tenant
        if (!$assinatura) {
            $assinatura = \App\Modules\Assinatura\Models\Assinatura::with('plano')
                ->where('tenant_id', $tenant->id)
                ->orderBy('data_fim', 'desc')
                ->orderBy('criado_em', 'desc')
                ->first();
        }

        if (!$assinatura) {
            return response()->json([
                'message' => 'Nenhuma assinatura encontrada',
                'code' => 'NO_SUBSCRIPTION'
            ], 403);
        }

        // Calcular dias restantes
        $diasRestantes = $assinatura->diasRestantes();

        return response()->json([
            'data' => [
                'id' => $assinatura->id,
                'tenant_id' => $assinatura->tenant_id,
                'plano_id' => $assinatura->plano_id,
                'status' => $assinatura->status,
                'data_inicio' => $assinatura->data_inicio ? $assinatura->data_inicio->format('Y-m-d') : null,
                'data_fim' => $assinatura->data_fim ? $assinatura->data_fim->format('Y-m-d') : null,
                'valor_pago' => $assinatura->valor_pago,
                'metodo_pagamento' => $assinatura->metodo_pagamento,
                'transacao_id' => $assinatura->transacao_id,
                'dias_restantes' => $diasRestantes,
                'plano' => $assinatura->plano ? [
                    'id' => $assinatura->plano->id,
                    'nome' => $assinatura->plano->nome,
                    'descricao' => $assinatura->plano->descricao,
                    'preco_mensal' => $assinatura->plano->preco_mensal,
                    'preco_anual' => $assinatura->plano->preco_anual,
                    'limite_processos' => $assinatura->plano->limite_processos,
                    'limite_usuarios' => $assinatura->plano->limite_usuarios,
                    'limite_armazenamento_mb' => $assinatura->plano->limite_armazenamento_mb,
                ] : null,
            ]
        ]);
    }

    /**
     * Retorna status da assinatura com limites utilizados
     */
    public function status(Request $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant não encontrado'
            ], 404);
        }

        // Buscar assinatura atual
        $assinatura = \App\Modules\Assinatura\Models\Assinatura::with('plano')
            ->where('tenant_id', $tenant->id)
            ->where('id', $tenant->assinatura_atual_id)
            ->first();

        if (!$assinatura || !$assinatura->plano) {
            return response()->json([
                'message' => 'Nenhuma assinatura encontrada',
                'code' => 'NO_SUBSCRIPTION'
            ], 403);
        }

        // Contar processos e usuários utilizados
        $processosUtilizados = \App\Modules\Processo\Models\Processo::count();
        $usuariosUtilizados = \App\Modules\Auth\Models\User::whereHas('empresas', function($query) {
            $query->where('empresas.id', $this->getEmpresaAtivaOrFail()->id);
        })->count();

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'status' => $assinatura->status,
                'limite_processos' => $assinatura->plano->limite_processos,
                'limite_usuarios' => $assinatura->plano->limite_usuarios,
                'limite_armazenamento_mb' => $assinatura->plano->limite_armazenamento_mb,
                'processos_utilizados' => $processosUtilizados,
                'usuarios_utilizados' => $usuariosUtilizados,
                'mensagem' => $assinatura->isAtiva() ? 'Assinatura ativa' : 'Assinatura inativa',
            ]
        ]);
    }

    /**
     * Lista assinaturas
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Implementar listagem
        return response()->json([
            'data' => [],
            'message' => 'Funcionalidade em desenvolvimento'
        ]);
    }

    /**
     * Cria nova assinatura
     */
    public function store(Request $request): JsonResponse
    {
        // TODO: Implementar criação
        return response()->json([
            'message' => 'Funcionalidade em desenvolvimento'
        ], 501);
    }

    /**
     * Renova assinatura
     */
    public function renovar(Request $request, $assinatura): JsonResponse
    {
        try {
            $validated = $request->validate([
                'meses' => 'required|integer|in:1,12',
                'card_token' => 'required|string',
                'payer_email' => 'required|email',
                'payer_cpf' => 'nullable|string',
                'installments' => 'nullable|integer|min:1|max:12',
            ]);

            $tenant = tenancy()->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado'], 404);
            }

            // Buscar assinatura
            $assinaturaModel = \App\Modules\Assinatura\Models\Assinatura::where('id', $assinatura)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            // Carregar plano
            $plano = $assinaturaModel->plano;
            if (!$plano) {
                return response()->json(['message' => 'Plano da assinatura não encontrado'], 404);
            }

            // Calcular valor
            $meses = $validated['meses'];
            $valor = $meses === 12 && $plano->preco_anual 
                ? $plano->preco_anual 
                : $plano->preco_mensal * $meses;

            // Criar PaymentRequest
            $paymentRequest = \App\Domain\Payment\ValueObjects\PaymentRequest::fromArray([
                'amount' => $valor,
                'description' => "Renovação de assinatura - Plano {$plano->nome} - {$meses} " . ($meses === 1 ? 'mês' : 'meses'),
                'payer_email' => $validated['payer_email'],
                'payer_cpf' => $validated['payer_cpf'] ?? null,
                'card_token' => $validated['card_token'],
                'installments' => $validated['installments'] ?? 1,
                'payment_method_id' => 'credit_card',
                'external_reference' => "renewal_tenant_{$tenant->id}_assinatura_{$assinaturaModel->id}",
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'assinatura_id' => $assinaturaModel->id,
                    'plano_id' => $plano->id,
                    'meses' => $meses,
                ],
            ]);

            // Processar renovação
            $renovarAssinaturaUseCase = app(\App\Application\Payment\UseCases\RenovarAssinaturaUseCase::class);
            $assinaturaRenovada = $renovarAssinaturaUseCase->executar(
                $assinaturaModel,
                $paymentRequest,
                $meses
            );

            return response()->json([
                'message' => 'Assinatura renovada com sucesso',
                'data' => [
                    'assinatura_id' => $assinaturaRenovada->id,
                    'status' => $assinaturaRenovada->status,
                    'data_fim' => $assinaturaRenovada->data_fim->format('Y-m-d'),
                    'dias_restantes' => $assinaturaRenovada->diasRestantes(),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Assinatura não encontrada',
            ], 404);
        } catch (\App\Domain\Exceptions\DomainException | \App\Domain\Exceptions\BusinessRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Erro ao renovar assinatura', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao renovar assinatura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela assinatura
     */
    public function cancelar(Request $request, $assinatura): JsonResponse
    {
        // TODO: Implementar cancelamento
        return response()->json([
            'message' => 'Funcionalidade em desenvolvimento'
        ], 501);
    }
}

