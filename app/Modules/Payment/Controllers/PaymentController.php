<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Application\Payment\UseCases\ProcessarAssinaturaPlanoUseCase;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use App\Modules\Assinatura\Models\Plano;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller para processamento de pagamentos
 */
class PaymentController extends BaseApiController
{
    public function __construct(
        private ProcessarAssinaturaPlanoUseCase $processarAssinaturaUseCase,
    ) {}

    /**
     * Processa assinatura de plano
     * 
     * POST /api/payments/processar-assinatura
     */
    public function processarAssinatura(Request $request)
    {
        try {
            $validated = $request->validate([
                'plano_id' => 'required|integer|exists:planos,id',
                'periodo' => 'required|string|in:mensal,anual',
                'card_token' => 'required|string', // Token do MercadoPago.js
                'payer_email' => 'required|email',
                'payer_cpf' => 'nullable|string',
                'installments' => 'nullable|integer|min:1|max:12',
            ]);

            // Buscar tenant atual
            $tenant = tenancy()->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Tenant não encontrado'], 404);
            }

            // Buscar plano
            $plano = Plano::findOrFail($validated['plano_id']);
            if (!$plano->isAtivo()) {
                return response()->json(['message' => 'Plano não está ativo'], 400);
            }

            // Calcular valor
            $valor = $validated['periodo'] === 'anual' 
                ? $plano->preco_anual 
                : $plano->preco_mensal;

            // Criar PaymentRequest
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

