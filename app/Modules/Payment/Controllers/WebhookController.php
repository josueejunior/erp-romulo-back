<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Application\Assinatura\UseCases\AtualizarAssinaturaViaWebhookUseCase;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        private AtualizarAssinaturaViaWebhookUseCase $atualizarAssinaturaViaWebhookUseCase,
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

            // Atualizar assinatura usando Use Case DDD
            $this->atualizarAssinaturaViaWebhookUseCase->executar(
                $paymentResult->externalId,
                $paymentResult
            );

            return response()->json(['message' => 'Webhook processado com sucesso'], 200);

        } catch (NotFoundException $e) {
            Log::warning('Assinatura não encontrada para webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);
            // Retornar 200 para evitar retentativas excessivas
            return response()->json(['message' => 'Assinatura não encontrada'], 200);
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


