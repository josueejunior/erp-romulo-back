<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Payment\UseCases\ProcessarAssinaturaPlanoUseCase;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Http\Requests\Payment\ProcessarAssinaturaRequest;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller para processamento de pagamentos
 */
class PaymentController extends BaseApiController
{
    public function __construct(
        private ProcessarAssinaturaPlanoUseCase $processarAssinaturaUseCase,
        private PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Processa assinatura de plano
     * Usa Form Request para validação
     * 
     * POST /api/payments/processar-assinatura
     */
    public function processarAssinatura(ProcessarAssinaturaRequest $request)
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Buscar tenant atual
            $tenant = tenancy()->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado'], 404);
            }

            // Buscar plano usando repository DDD
            $plano = $this->planoRepository->buscarModeloPorId($validated['plano_id']);
            if (!$plano) {
                return response()->json(['message' => 'Plano não encontrado'], 404);
            }
            if (!$plano->isAtivo()) {
                return response()->json(['message' => 'Plano não está ativo'], 400);
            }

            // Calcular valor
            $valor = $validated['periodo'] === 'anual' 
                ? $plano->preco_anual 
                : $plano->preco_mensal;

            $isGratis = ($valor === null || $valor == 0);

            // Se for gratuito, criar assinatura diretamente sem passar pelo gateway
            if ($isGratis) {
                // Buscar ou criar assinatura gratuita
                $assinaturaModel = Assinatura::where('tenant_id', $tenant->id)
                    ->where('plano_id', $plano->id)
                    ->where('status', 'ativa')
                    ->first();

                if (!$assinaturaModel) {
                    // Criar nova assinatura gratuita
                    $dataInicio = now();
                    $dataFim = $dataInicio->copy()->addDays(14); // Trial de 14 dias

                    $assinaturaModel = Assinatura::create([
                        'tenant_id' => $tenant->id,
                        'plano_id' => $plano->id,
                        'status' => 'ativa',
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim,
                        'valor_pago' => 0,
                        'metodo_pagamento' => 'gratuito',
                        'transacao_id' => null,
                        'dias_grace_period' => 0,
                        'observacoes' => 'Assinatura gratuita (Trial)',
                    ]);
                }

                return response()->json([
                    'message' => 'Assinatura gratuita ativada com sucesso',
                    'data' => [
                        'assinatura_id' => $assinaturaModel->id,
                        'status' => $assinaturaModel->status,
                        'data_fim' => $assinaturaModel->data_fim->format('Y-m-d'),
                    ],
                ], 201);
            }

            // Para planos pagos, criar PaymentRequest e processar via gateway
            $paymentRequest = PaymentRequest::fromArray([
                'amount' => $valor,
                'description' => "Plano {$plano->nome} - {$validated['periodo']} - Sistema Rômulo",
                'payer_email' => $validated['payer_email'],
                'payer_cpf' => $validated['payer_cpf'] ?? null,
                'card_token' => $validated['card_token'],
                'installments' => $validated['installments'] ?? 1,
                'payment_method_id' => 'credit_card',
                'external_reference' => "tenant_{$tenant->id}_plano_{$plano->id}",
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plano_id' => $plano->id,
                    'periodo' => $validated['periodo'],
                ],
            ]);

            // Processar assinatura
            $assinatura = $this->processarAssinaturaUseCase->executar(
                $tenant,
                $plano,
                $paymentRequest,
                $validated['periodo']
            );

            return response()->json([
                'message' => 'Assinatura processada com sucesso',
                'data' => [
                    'assinatura_id' => $assinatura->id,
                    'status' => $assinatura->status,
                    'data_fim' => $assinatura->data_fim->format('Y-m-d'),
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao processar assinatura', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar assinatura: ' . $e->getMessage(),
            ], 500);
        }
    }
}


