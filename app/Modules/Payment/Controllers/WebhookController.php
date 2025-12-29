<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Modules\Assinatura\Models\Assinatura;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Controller para receber webhooks do Mercado Pago
 * 
 * IMPORTANTE: Este endpoint deve ser público (sem autenticação)
 * mas deve validar a assinatura do webhook
 */
class WebhookController extends Controller
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
    ) {}

    /**
     * Recebe webhook do Mercado Pago
     * 
     * POST /api/webhooks/mercadopago
     */
    public function mercadopago(Request $request)
    {
        try {
            $payload = $request->all();
            
            Log::info('Webhook recebido do Mercado Pago', [
                'payload' => $payload,
                'headers' => $request->headers->all(),
            ]);

            // Validar assinatura (se configurado)
            $signature = $request->header('X-Signature');
            if ($signature && !$this->paymentProvider->validateWebhookSignature($payload, $signature)) {
                Log::warning('Webhook com assinatura inválida', [
                    'signature' => $signature,
                ]);
                return response()->json(['message' => 'Assinatura inválida'], 401);
            }

            // Processar webhook
            $paymentResult = $this->paymentProvider->processWebhook($payload);

            // Buscar assinatura pelo external_id
            $assinatura = Assinatura::where('transacao_id', $paymentResult->externalId)->first();

            if (!$assinatura) {
                Log::warning('Assinatura não encontrada para webhook', [
                    'external_id' => $paymentResult->externalId,
                ]);
                return response()->json(['message' => 'Assinatura não encontrada'], 404);
            }

            // Atualizar assinatura baseado no status
            DB::transaction(function () use ($assinatura, $paymentResult) {
                if ($paymentResult->isApproved() && $assinatura->status !== 'ativa') {
                    // Ativar assinatura
                    $assinatura->update([
                        'status' => 'ativa',
                        'data_inicio' => $paymentResult->approvedAt ?? now(),
                    ]);

                    // Atualizar tenant
                    $assinatura->tenant->update([
                        'plano_atual_id' => $assinatura->plano_id,
                        'assinatura_atual_id' => $assinatura->id,
                    ]);

                    Log::info('Assinatura ativada via webhook', [
                        'assinatura_id' => $assinatura->id,
                        'external_id' => $paymentResult->externalId,
                    ]);
                } elseif ($paymentResult->isRejected()) {
                    // Marcar como suspensa se rejeitado
                    $assinatura->update([
                        'status' => 'suspensa',
                        'observacoes' => "Pagamento rejeitado: {$paymentResult->errorMessage}",
                    ]);

                    Log::warning('Assinatura suspensa via webhook (pagamento rejeitado)', [
                        'assinatura_id' => $assinatura->id,
                        'external_id' => $paymentResult->externalId,
                        'error' => $paymentResult->errorMessage,
                    ]);
                }

                // Atualizar log de pagamento
                $paymentLog = PaymentLog::where('external_id', $paymentResult->externalId)->first();
                if ($paymentLog) {
                    $paymentLog->update([
                        'status' => $paymentResult->status,
                        'dados_resposta' => array_merge($paymentLog->dados_resposta ?? [], [
                            'webhook_status' => $paymentResult->status,
                            'webhook_received_at' => now()->toIso8601String(),
                        ]),
                    ]);
                }
            });

            return response()->json(['message' => 'Webhook processado com sucesso'], 200);

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do Mercado Pago', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // Retornar 200 mesmo em caso de erro para evitar retentativas excessivas
            return response()->json(['message' => 'Erro ao processar webhook'], 200);
        }
    }
}

