<?php

namespace App\Modules\Payment\Controllers;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessarWebhookMercadoPagoJob;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe webhooks do Mercado Pago.
 *
 * Contrato (public, sem auth JWT):
 * 1. Valida HMAC (X-Signature) — rejeita 401 se inválida.
 * 2. Persiste o evento em `webhook_events` (unique: provider+event_type+resource_id).
 * 3. Se já existe registro com status `processed`, retorna 200 sem reprocessar
 *    (idempotência para retentativas do MP).
 * 4. Caso contrário, despacha `ProcessarWebhookMercadoPagoJob` na queue e
 *    responde 200 imediatamente (MP exige resposta em ≤22s).
 */
class WebhookController extends Controller
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
    ) {}

    /**
     * POST /api/v1/webhooks/mercadopago
     */
    public function mercadopago(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Signature');

        Log::info('Webhook recebido do Mercado Pago', [
            'type' => $payload['type'] ?? null,
            'action' => $payload['action'] ?? null,
            'data_id' => $payload['data']['id'] ?? null,
            'has_signature' => !empty($signature),
        ]);

        // 1) Assinatura HMAC obrigatória
        if (!$signature) {
            Log::warning('Webhook sem assinatura (X-Signature)', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Assinatura obrigatória'], 401);
        }

        if (!$this->paymentProvider->validateWebhookSignature($payload, $signature)) {
            Log::warning('Webhook com assinatura inválida', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Assinatura inválida'], 401);
        }

        // 2) Validar formato mínimo
        $eventType = (string) ($payload['type'] ?? 'unknown');
        $resourceId = (string) ($payload['data']['id'] ?? '');
        $action = $payload['action'] ?? null;

        if ($resourceId === '') {
            Log::warning('Webhook sem data.id — ignorando', [
                'payload' => $payload,
            ]);
            // 200 propositalmente: informar ao MP que recebemos, não reenviar.
            return response()->json(['message' => 'Payload sem data.id'], 200);
        }

        // 3) Idempotência: se já existe entrada processada, retornar 200 sem re-enfileirar.
        $existing = WebhookEvent::where('provider', 'mercadopago')
            ->where('event_type', $eventType)
            ->where('resource_id', $resourceId)
            ->first();

        if ($existing && $existing->status === WebhookEvent::STATUS_PROCESSED) {
            Log::info('Webhook duplicado (já processado) — ACK sem reprocessar', [
                'webhook_event_id' => $existing->id,
                'resource_id' => $resourceId,
            ]);
            return response()->json([
                'message' => 'Webhook já processado anteriormente',
                'duplicate' => true,
                'webhook_event_id' => $existing->id,
            ], 200);
        }

        // 4) Persistir (ou reativar) e despachar job assíncrono.
        $event = $existing ?? new WebhookEvent();
        $event->fill([
            'provider' => 'mercadopago',
            'event_type' => $eventType,
            'resource_id' => $resourceId,
            'action' => $action,
            'payload' => $payload,
            'headers' => [
                'user-agent' => $request->header('User-Agent'),
                'x-request-id' => $request->header('X-Request-Id'),
                'x-signature-preview' => substr((string) $signature, 0, 40) . '...',
            ],
            'status' => WebhookEvent::STATUS_RECEIVED,
            'last_error' => null,
        ]);
        $event->save();

        try {
            ProcessarWebhookMercadoPagoJob::dispatch($event->id);
        } catch (\Throwable $e) {
            // Falha ao enfileirar — log e 200 mesmo assim para não causar retry do MP.
            // Um job de "rescue" (scheduled task) pode repescar webhook_events com status 'received'.
            Log::error('Falha ao enfileirar ProcessarWebhookMercadoPagoJob', [
                'webhook_event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Webhook recebido',
            'webhook_event_id' => $event->id,
            'queued' => true,
        ], 200);
    }
}
